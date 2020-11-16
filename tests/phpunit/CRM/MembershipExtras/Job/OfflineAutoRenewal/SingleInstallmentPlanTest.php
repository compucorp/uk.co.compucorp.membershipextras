<?php

use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;

/**
 * Class CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlanTest.
 *
 * @group headless
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlanTest extends BasePaymentPlanTest {
  private $zeroFeeMembershipType;
  private $recurringContribution;

  public function testSingleInstallmentPlanWithMembershipWithZeroFeeAutoRenew() {
    $this->setupRenewableSingleInstallmentPlanWithZeroMembershipFeeTestCase();

    $contributions = $this->getPaymentPlanContributions($this->recurringContribution['id']);
    $this->assertEquals(1, count($contributions));

    $memberships = $this->getPaymentPlanMemberships();
    foreach ($memberships as $membership) {
      $this->assertEquals('2018-12-31', $membership['end_date']);
    }

    $renewer = new CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlan();
    $renewer->run();

    $contributions = $this->getPaymentPlanContributions($this->recurringContribution['id']);
    $this->assertEquals(2, count($contributions));

    $memberships = $this->getPaymentPlanMemberships();
    foreach ($memberships as $membership) {
      $this->assertEquals('2019-12-31', $membership['end_date']);
    }
  }

  /**
   * Sets up a scenario with a single installment plan with zero value fee.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setupRenewableSingleInstallmentPlanWithZeroMembershipFeeTestCase() {
    $this->contact = ContactFabricator::fabricate();

    $this->createRequiredEntities();
    $this->setUpDefaultPaymentPlanParameters();

    $this->recurringContribution = PaymentPlanOrderFabricator::fabricate(
      $this->recurringContributionParams,
      $this->lineItemsParams,
      $this->contributionParams
    );
  }

  private function createRequiredEntities() {
    $this->zeroFeeMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Rolling Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 0,
        'duration_unit' => 'year',
        'duration_interval' => 1,
      ],
      TRUE
    );
    $priceField = PriceFieldFabricator::fabricate([
      'name' => 'default_price_set',
      'label' => 'Member Dues',
      'price_set_id' => $this->defaultMembershipsPriceSet['id'],
    ]);
    PriceFieldValueFabricator::fabricate([
      'label' => $this->zeroFeeMembershipType->name,
      'amount' => $this->zeroFeeMembershipType->minimum_fee,
      'price_field_id' => $priceField['id'],
      'membership_type_id' => $this->zeroFeeMembershipType->id,
      'financial_type_id' => $this->memberDuesFinancialType['id'],
    ]);
  }

  /**
   * Builds default parameters that will be used to create the payment plan.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setUpDefaultPaymentPlanParameters() {
    $this->recurringContributionParams = [
      'sequential' => 1,
      'contact_id' => $this->contact['id'],
      'amount' => 0,
      'frequency_unit' => 'year',
      'frequency_interval' => 1,
      'installments' => 1,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => $this->getPayLaterProcessorID()['id'],
      'financial_type_id' => $this->memberDuesFinancialType['id'],
      'payment_instrument_id' => $this->eftPaymentInstrumentID,
      'start_date' => '2018-01-01',
    ];

    $this->contributionParams = [
      'contact_id' => $this->contact['id'],
      'fee_amount' => 0,
      'net_amount' => 120,
      'total_amount' => 120,
      'receive_date' => '2018-01-01',
      'payment_instrument_id' => $this->eftPaymentInstrumentID,
      'financial_type_id' => $this->memberDuesFinancialType['id'],
      'contribution_status_id' => $this->contributionPendingStatusValue,
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
    ];

    $priceFieldValue = $this->getDefaultPriceFieldValueID($this->zeroFeeMembershipType->id);
    $this->lineItemsParams[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $priceFieldValue['price_field_id'],
      'label' => 'Membership subscription',
      'qty' => 1,
      'unit_price' => 0,
      'line_total' => 0,
      'price_field_value_id' => $priceFieldValue['id'],
      'financial_type_id' => $this->memberDuesFinancialType['id'],
      'non_deductible_amount' => 0,
      'start_date' => '2018-01-01',
      'join_date' => '2018-01-01',
      'end_date' => '',
    ];
    $this->lineItemsParams[] = [
      'entity_table' => '',
      'price_field_id' => $priceFieldValue['price_field_id'],
      'label' => 'Other fee',
      'qty' => 1,
      'unit_price' => 120,
      'line_total' => 120,
      'financial_type_id' => $this->memberDuesFinancialType['id'],
      'non_deductible_amount' => 0,
    ];
  }

  private function getPaymentPlanMemberships() {
    $lineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'is_removed' => 0,
      'end_date' => ['IS NULL' => 1],
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
      'options' => ['limit' => 0],
    ]);

    $memberships = [];
    foreach ($lineItems['values'] as $line) {
      if ($line['api.LineItem.getsingle']['entity_table'] === 'civicrm_membership') {
        $memberships[] = civicrm_api3('Membership', 'getsingle', [
          'id' => $line['api.LineItem.getsingle']['entity_id'],
        ]);;
      }
    }

    return $memberships;
  }

}
