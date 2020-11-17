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

  /**
   * Data for the recurring contribution.
   *
   * @var array
   */
  private $recurringContribution;

  public function testSingleInstallmentPlanWithMembershipWithZeroFeeAutoRenew() {
    $startDate = '2018-01-01';
    $calculatedEndDate = '2018-12-31';
    $endDateAfterRenewal = '2019-12-31';

    $this->setupRenewableSingleInstallmentPlanWithZeroMembershipFeeTestCase($startDate);

    $contributions = $this->getPaymentPlanContributions($this->recurringContribution['id']);
    $this->assertEquals(1, count($contributions));
    $this->assertEquals('2018-01-01 00:00:00', $contributions[0]['receive_date']);

    $memberships = $this->getPaymentPlanMemberships();
    foreach ($memberships as $membership) {
      $this->assertEquals($calculatedEndDate, $membership['end_date']);
    }

    $renewer = new CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlan();
    $renewer->run();

    $contributions = $this->getPaymentPlanContributions($this->recurringContribution['id']);
    $this->assertEquals(2, count($contributions));
    $this->assertEquals('2019-01-01 00:00:00', $contributions[1]['receive_date']);

    $memberships = $this->getPaymentPlanMemberships();
    foreach ($memberships as $membership) {
      $this->assertEquals($endDateAfterRenewal, $membership['end_date']);
    }
  }

  /**
   * Sets up a scenario with a single installment plan with zero value fee.
   *
   * @param string $startDate
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setupRenewableSingleInstallmentPlanWithZeroMembershipFeeTestCase($startDate) {
    $this->contact = ContactFabricator::fabricate();

    $zeroFeeMembershipType = $this->createZeroFeeMembershipType();
    $this->createPriceFieldForMembershipType($zeroFeeMembershipType);
    $this->setUpDefaultPaymentPlanParameters($startDate);

    $priceFieldValue = $this->getDefaultPriceFieldValueID($zeroFeeMembershipType->id);
    $this->lineItemsParams = [
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $priceFieldValue['price_field_id'],
        'label' => 'Membership subscription',
        'qty' => 1,
        'unit_price' => 0,
        'line_total' => 0,
        'price_field_value_id' => $priceFieldValue['id'],
        'financial_type_id' => $this->memberDuesFinancialType['id'],
        'non_deductible_amount' => 0,
        'start_date' => $startDate,
        'join_date' => $startDate,
        'end_date' => '',
      ],
      [
        'entity_table' => '',
        'price_field_id' => $priceFieldValue['price_field_id'],
        'label' => 'Other fee',
        'qty' => 1,
        'unit_price' => 120,
        'line_total' => 120,
        'financial_type_id' => $this->memberDuesFinancialType['id'],
        'non_deductible_amount' => 0,
      ],
    ];

    $this->recurringContribution = PaymentPlanOrderFabricator::fabricate(
      $this->recurringContributionParams,
      $this->lineItemsParams,
      $this->contributionParams
    );
  }

  /**
   * Creates a zero fee membership type.
   */
  private function createZeroFeeMembershipType() {
    return MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Rolling Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 0,
        'duration_unit' => 'year',
        'duration_interval' => 1,
      ],
      TRUE
    );
  }

  /**
   * Create a price field and price field value for the membership type.
   *
   * @param $membershipType
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function createPriceFieldForMembershipType($membershipType) {
    $priceField = PriceFieldFabricator::fabricate([
      'name' => 'test_field_' . time(),
      'label' => 'Field ' . time(),
      'price_set_id' => $this->defaultMembershipsPriceSet['id'],
    ]);
    PriceFieldValueFabricator::fabricate([
      'label' => $membershipType->name,
      'amount' => $membershipType->minimum_fee,
      'price_field_id' => $priceField['id'],
      'membership_type_id' => $membershipType->id,
      'financial_type_id' => $this->memberDuesFinancialType['id'],
    ]);
  }

  /**
   * Builds default parameters that will be used to create the payment plan.
   *
   * @param string $startDate
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setUpDefaultPaymentPlanParameters($startDate) {
    $this->recurringContributionParams = CRM_MembershipExtras_Test_Fabricator_RecurringContribution::getDefaultParameters();
    $this->recurringContributionParams['contact_id'] = $this->contact['id'];
    $this->recurringContributionParams['start_date'] = $startDate;

    $this->contributionParams = CRM_MembershipExtras_Test_Fabricator_Contribution::getDefaultParameters();
    $this->contributionParams['contact_id'] = $this->contact['id'];
    $this->contributionParams['receive_date'] = $startDate;
  }

  /**
   * Returns list of memberships associated to the payment plan.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
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

  public function testDatesOfRenewalOfFixedMemberships() {
    $startDate = '2019-07-01';
    $endDate = '2019-11-30';
    $fixedStartDate = '1201';
    $fixedEndDate = '1130';
    $receiveDateAfterRenewal = '2019-12-01';
    $endDateAfterRenewal = '2020-11-30';

    $this->setupRenewableSingleInstallmentPlanWithFixedMembership($startDate, $fixedStartDate, $fixedEndDate);

    $contributions = $this->getPaymentPlanContributions($this->recurringContribution['id']);
    $this->assertEquals(1, count($contributions));
    $this->assertEquals($startDate . ' 00:00:00', $contributions[0]['receive_date']);

    $memberships = $this->getPaymentPlanMemberships();
    foreach ($memberships as $membership) {
      $this->assertEquals($startDate, $membership['join_date']);
      $this->assertEquals($startDate, $membership['start_date']);
      $this->assertEquals($endDate, $membership['end_date']);
    }

    $renewer = new CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlan();
    $renewer->run();

    $contributions = $this->getPaymentPlanContributions($this->recurringContribution['id']);
    $this->assertEquals(2, count($contributions));
    $this->assertEquals($receiveDateAfterRenewal . ' 00:00:00', $contributions[1]['receive_date']);

    $memberships = $this->getPaymentPlanMemberships();
    foreach ($memberships as $membership) {
      $this->assertEquals($startDate, $membership['join_date']);
      $this->assertEquals($startDate, $membership['start_date']);
      $this->assertEquals($endDateAfterRenewal, $membership['end_date']);
    }
  }

  /**
   * Sets up a single installment plan with a fixed membership.
   *
   * @param string $startDate
   * @param string $fixedStartDate
   * @param string $fixedEndDate
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setupRenewableSingleInstallmentPlanWithFixedMembership($startDate, $fixedStartDate, $fixedEndDate) {
    $this->contact = ContactFabricator::fabricate();

    $fixedMembershipType = $this->createFixedMembershipType($fixedStartDate, $fixedEndDate);
    $this->createPriceFieldForMembershipType($fixedMembershipType);
    $this->setUpDefaultPaymentPlanParameters($startDate);

    $priceFieldValue = $this->getDefaultPriceFieldValueID($fixedMembershipType->id);
    $this->lineItemsParams = [
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $priceFieldValue['price_field_id'],
        'label' => 'Membership subscription',
        'qty' => 1,
        'unit_price' => 0,
        'line_total' => 0,
        'price_field_value_id' => $priceFieldValue['id'],
        'financial_type_id' => $this->memberDuesFinancialType['id'],
        'non_deductible_amount' => 0,
        'start_date' => $startDate,
        'join_date' => $startDate,
        'end_date' => '',
      ],
    ];

    $this->recurringContribution = PaymentPlanOrderFabricator::fabricate(
      $this->recurringContributionParams,
      $this->lineItemsParams,
      $this->contributionParams
    );
  }

  /**
   * Creates a fixed membership type within the given start and end dates.
   *
   * @param $from
   * @param $to
   *
   * @return \CRM_Member_BAO_MembershipType
   */
  private function createFixedMembershipType($from, $to) {
    return MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Rolling Membership',
        'period_type' => 'fixed',
        'minimum_fee' => 0,
        'duration_unit' => 'year',
        'duration_interval' => 1,
        'fixed_period_start_day' => $from,
        'fixed_period_rollover_day' => $to,
      ],
      TRUE
    );
  }

}
