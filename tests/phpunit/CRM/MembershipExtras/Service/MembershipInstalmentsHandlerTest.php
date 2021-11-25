<?php

use CRM_MembershipExtras_Service_MembershipInstalmentsHandler as MembershipInstalmentsHandler;
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contribution as ContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringLineItem as RecurringLineItemFabricator;

/**
 * CRM_MembershipExtras_Service_MembershipInstalmentsHandlerTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipInstalmentsHandlerTest extends BaseHeadlessTest {

  /**
   * @var mixed
   */
  private $recurringContribution;
  /**
   * @var mixed
   */
  private $membership;
  /**
   * @var array
   */
  private $membershipTypeDates;
  /**
   * @var mixed
   */
  private $membershipTypePriceFieldValue;
  /**
   * @var CRM_Member_BAO_MembershipType|mixed
   */
  private $membershipType;

  /**
   * Tests create remaining instalment contributions
   */
  public function testCreateRemainingInstalmentContributionsUpfront() {
    $this->mockPaymentPlanMembershipOrder('rolling');
    $mockedMembershipPayments = $this->getMembershipPayment();

    $this->assertEquals(1, $mockedMembershipPayments['count']);

    $expectedContribution = $mockedMembershipPayments['values'][0]['api.Contribution.get']['values'][0];
    unset($expectedContribution['id']);

    $handler = new MembershipInstalmentsHandler($this->recurringContribution['id']);
    $handler->createRemainingInstalmentContributionsUpfront();

    $processesMembershipPayments = $this->getMembershipPayment();
    $this->assertEquals(12, $processesMembershipPayments['count']);

    $processedContributions = $processesMembershipPayments['values'][0]['api.Contribution.get']['values'];
    foreach ($processedContributions as $contribution) {
      unset($contribution['id']);
      $this->assertTrue($expectedContribution == $contribution);
    }

  }

  /**
   * Tests create remaining instalment contribution with setting instalment count
   */
  public function testCreateRemainingInstalmentContributionsUpfrontWithSettingInstalmentCount() {
    $this->mockPaymentPlanMembershipOrder('fixed');
    $mockedMembershipPayments = $this->getMembershipPayment();

    $this->assertEquals(1, $mockedMembershipPayments['count']);

    $handler = new MembershipInstalmentsHandler($this->recurringContribution['id']);
    $handler->setInstalmentsCount(9);
    $handler->createRemainingInstalmentContributionsUpfront();

    $expectedContribution = $mockedMembershipPayments['values'][0]['api.Contribution.get']['values'][0];
    unset($expectedContribution['id']);

    $processesMembershipPayments = $this->getMembershipPayment();

    $processedContributions = $processesMembershipPayments['values'][0]['api.Contribution.get']['values'];
    foreach ($processedContributions as $contribution) {
      unset($contribution['id']);
      $this->assertTrue($expectedContribution == $contribution);
    }
  }

  private function getMembershipPayment() {
    $params = [
      'sequential' => 1,
      'membership_id' => $this->membership['id'],
      'api.Contribution.get' => [],
    ];

    return civicrm_api3('MembershipPayment', 'get', $params);
  }

  /**
   * Fabricates payment plan membership order
   *
   * @param $periodType
   * @throws CiviCRM_API3_Exception
   */
  private function mockPaymentPlanMembershipOrder($periodType) {
    $this->membershipType = CRM_MembershipExtras_Test_Fabricator_MembershipType::fabricate([
      'name' => 'Test Membership',
      'period_type' => $periodType,
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => 'year',
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
    ]);

    $startDate = new DateTime();
    $joinDate = $startDate;
    $endDate = new DateTime();
    $endDate->add(new DateInterval('P1Y'));
    $endDate->modify("-1 day");

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $this->membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $this->membershipType['id'],
      $startDate,
      $endDate,
      $joinDate
    );

    $this->membershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $this->createRecurringContribution();
    $this->createdLineItems();

  }

  private function createRecurringContribution() {
    $contact = ContactFabricator::fabricate();
    $recurringContributionParams = [
      'sequential' => 1,
      'contact_id' => $contact['id'],
    //120 (Membership fee)  / 12 (instalments)
      'amount' => 10,
      'frequency_unit' => 'year',
      'frequency_interval' => '1',
      'installments' => 12,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'financial_type_id' => 'Member Dues',
      'payment_instrument_id' => 'EFT',
      'start_date' => $this->membershipTypeDates['start_date'],
    ];

    $this->recurringContribution = RecurringContributionFabricator::fabricate($recurringContributionParams);
  }

  private function createdLineItems() {
    $lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->membershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->membershipTypePriceFieldValue['id'],
      'label' => $this->membershipType['name'],
      'qty' => 1,
      'unit_price' => $this->membershipTypePriceFieldValue['amount'],
      'line_total' => $this->membershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 0,
    ];

    $createdLines = [];

    foreach ($lineItems as $lineItem) {
      if ($lineItem['entity_table'] == 'civicrm_membership') {
        $this->membership = MembershipFabricator::fabricate([
          'contact_id' => $this->recurringContribution['contact_id'],
          'membership_type_id' => $this->membershipType['id'],
          'join_date' => CRM_Utils_Array::value('join_date', $lineItem, $this->membershipTypeDates['join_date']),
          'start_date' => CRM_Utils_Array::value('start_date', $lineItem, $this->membershipTypeDates['start_date']),
          'end_date' => CRM_Utils_Array::value('end_date', $lineItem, $this->membershipTypeDates['end_date']),
          'contribution_recur_id' => $this->recurringContribution['id'],
          'financial_type_id' => $lineItem['financial_type_id'],
          'skipLineItem' => 1,
        ]);

        $lineItem['entity_id'] = $this->membership['id'];
        $lineItem['entity_table'] = 'civicrm_membership';
      }

      $newLineItem = LineItemFabricator::fabricate($lineItem);
      $recurringLineItem = RecurringLineItemFabricator::fabricate([
        'contribution_recur_id' => $this->recurringContribution['id'],
        'line_item_id' => $newLineItem['id'],
        'start_date' => $this->membershipTypeDates['start_date'],
        'auto_renew' => isset($lineItem['auto_renew']) ? $lineItem['auto_renew'] : 1,
      ]);

      $createdLines[] = ['line_item' => $newLineItem, 'recurring_line' => $recurringLineItem];
    }

    $this->createContribution($createdLines);
  }

  private function createContribution($createdLines) {
    //Create contribution
    $params = [
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => $this->membershipTypeDates['join_date'],
      'contribution_recur_id' => $this->recurringContribution['id'],
      'contact_id' => $this->recurringContribution['contact_id'],
      'fee_amount' => 0,
      'net_amount' => "{$this->recurringContribution['amount']}",
      'total_amount' => "{$this->recurringContribution['amount']}",
      'payment_instrument_id' => 'EFT',
      'financial_type_id' => 'Member Dues',
      'contribution_status_id' => 'Pending',
    ];
    $contribution = ContributionFabricator::fabricate($params);

    foreach ($createdLines as &$line) {
      unset($line['line_item']['id']);
      $line['line_item']['contribution_id'] = $contribution['id'];

      if ($line['line_item']['entity_table'] === 'civicrm_contribution') {
        $line['line_item']['entity_id'] = $contribution['id'];
      }

      $this->createLineItemForContribution($line);
    }
  }

  /**
   * Creates the line item for the contribution identified by the given ID.
   *
   * @param array $line
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function createLineItemForContribution($line) {
    $contribution = CRM_Contribute_BAO_Contribution::findById($line['line_item']['contribution_id']);
    $newLineItem = CRM_Price_BAO_LineItem::create($line['line_item']);
    CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution);

    if (!empty($contribution->tax_amount) && !empty($newLineItem->tax_amount)) {
      CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution, TRUE);
    }
    if ($line['line_item']['entity_table'] == 'civicrm_membership') {
      CRM_Member_BAO_MembershipPayment::create([
        'membership_id' => $line['line_item']['entity_id'],
        'contribution_id' => $contribution->id,
      ]);
    }
  }

}
