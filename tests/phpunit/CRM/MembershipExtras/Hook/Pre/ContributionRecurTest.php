<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;

/**
 * Class CRM_MembershipExtras_Hook_Pre_ContributionRecurTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_Pre_ContributionRecurTest extends BaseHeadlessTest {

  public function testPreventUpdatingNextScheduledContributionDateForSupportedRecurContribution() {
    $offlinePaymentProcessorId = civicrm_api3('PaymentProcessor', 'getvalue', [
      'return' => 'id',
      'name' => 'Offline Recurring Contribution',
      'is_test' => 0,
    ]);

    $recurringContributionID = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => 1,
      'amount' => 10,
      'frequency_interval' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'next_sched_contribution_date' => date('Y-m-d', strtotime('+1 month')),
    ])['id'];

    $paymentPlanParams = [
      'next_sched_contribution_date' => date('Y-m-d', strtotime('+1 month')),
      'payment_processor_id' => $offlinePaymentProcessorId,
    ];

    $hook = new CRM_MembershipExtras_Hook_Pre_ContributionRecur('edit', $recurringContributionID, $paymentPlanParams);
    $hook->preProcess();

    $this->assertFalse(array_key_exists('next_sched_contribution_date', $paymentPlanParams));
  }

  public function testKeepingNextScheduledContributionDateForNonSupportedRecurContribution() {
    $dummyPaymentProcessorId = civicrm_api3('PaymentProcessor', 'create', [
      'payment_processor_type_id' => 'Dummy',
      'financial_account_id' => 'Payment Processor Account',
      'name' => 'dummy_processor',
    ])['id'];

    $recurringContributionID = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => 1,
      'amount' => 10,
      'frequency_interval' => 1,
      'payment_processor_id' => 'dummy_processor',
      'next_sched_contribution_date' => date('Y-m-d', strtotime('+1 month')),
    ])['id'];

    $paymentPlanParams = [
      'next_sched_contribution_date' => date('Y-m-d', strtotime('+1 month')),
      'payment_processor_id' => $dummyPaymentProcessorId,
    ];

    $hook = new CRM_MembershipExtras_Hook_Pre_ContributionRecur('edit', $recurringContributionID, $paymentPlanParams);
    $hook->preProcess();

    $this->assertTrue(array_key_exists('next_sched_contribution_date', $paymentPlanParams));
  }

  /**
   * CIVIMM-593: a plan's status comes from the recurring contribution status
   * options. On CiviCRM 6.x the contribution status options have no
   * "In Progress", which these tests reproduce on the 5.x CI.
   */
  public function testMonthlyPlanIsInProgressAfterFirstPayment() {
    $this->removeInProgressFromContributionStatuses();
    $paymentPlan = $this->createPendingPaymentPlan('Monthly');
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);

    $this->recordPayment($contributions[0]);

    $this->assertEquals('In Progress', $this->getPaymentPlanStatus($paymentPlan['id']));
  }

  public function testMonthlyPlanIsCompletedAfterLastPayment() {
    $this->removeInProgressFromContributionStatuses();
    $paymentPlan = $this->createPendingPaymentPlan('Monthly');

    foreach ($this->getPaymentPlanContributions($paymentPlan['id']) as $contribution) {
      $this->recordPayment($contribution);
    }

    $this->assertEquals('Completed', $this->getPaymentPlanStatus($paymentPlan['id']));
  }

  public function testYearlyPlanIsInProgressAfterItsPayment() {
    $this->removeInProgressFromContributionStatuses();
    $paymentPlan = $this->createPendingPaymentPlan('Yearly');
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);

    $this->recordPayment($contributions[0]);

    $this->assertEquals('In Progress', $this->getPaymentPlanStatus($paymentPlan['id']));
  }

  /**
   * The hook sets the plan status ID from the recurring contribution status
   * options, by name.
   */
  public function testHookSetsPlanStatusFromRecurringContributionStatuses() {
    $this->removeInProgressFromContributionStatuses();
    $paymentPlan = $this->createPendingPaymentPlan('Monthly');
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $this->recordPayment($contributions[0]);

    $params = ['contribution_status_id' => CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', 'Completed')];
    $hook = new CRM_MembershipExtras_Hook_Pre_ContributionRecur('edit', $paymentPlan['id'], $params);
    $hook->preProcess();

    $this->assertEquals(
      CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', 'In Progress'),
      $params['contribution_status_id']
    );
  }

  /**
   * Removes "In Progress" from the contribution status options, as on
   * CiviCRM 6.x, where it is only a recurring contribution status.
   */
  private function removeInProgressFromContributionStatuses() {
    $optionValueID = CRM_Core_DAO::singleValueQuery("
      SELECT ov.id FROM civicrm_option_value ov
      INNER JOIN civicrm_option_group og ON og.id = ov.option_group_id
      WHERE og.name = 'contribution_status' AND ov.name = 'In Progress'
    ");
    if ($optionValueID) {
      CRM_Core_DAO::executeQuery('DELETE FROM civicrm_option_value WHERE id = %1', [1 => [$optionValueID, 'Integer']]);
    }
    CRM_Core_PseudoConstant::flush();
    Civi::cache('metadata')->clear();

    $this->assertNotContains('In Progress', CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name'));
    $this->assertNotEmpty(CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', 'In Progress'));
  }

  /**
   * @param string $frequency
   *   Monthly (12 instalments) or Yearly (1 instalment).
   *
   * @return array
   */
  private function createPendingPaymentPlan($frequency) {
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Plan Status Membership ' . $frequency,
      'period_type' => 'rolling',
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);
    $priceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $order = new PaymentPlanMembershipOrder();
    $order->membershipStartDate = date('Y-m-d');
    $order->paymentPlanFrequency = $frequency;
    $order->paymentPlanStatus = 'Pending';
    $order->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $priceFieldValue['price_field_id'],
      'price_field_value_id' => $priceFieldValue['id'],
      'label' => $membershipType['name'],
      'qty' => 1,
      'unit_price' => $priceFieldValue['amount'],
      'line_total' => $priceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 1,
    ];

    return PaymentPlanOrderFabricator::fabricate($order);
  }

  /**
   * @param int $paymentPlanID
   *
   * @return array
   */
  private function getPaymentPlanContributions($paymentPlanID) {
    return civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanID,
      'options' => ['limit' => 0, 'sort' => 'id ASC'],
    ])['values'];
  }

  /**
   * @param array $contribution
   */
  private function recordPayment($contribution) {
    civicrm_api3('Payment', 'create', [
      'contribution_id' => $contribution['id'],
      'total_amount' => $contribution['total_amount'],
      'trxn_date' => date('Y-m-d'),
    ]);
  }

  /**
   * @param int $paymentPlanID
   *
   * @return string
   */
  private function getPaymentPlanStatus($paymentPlanID) {
    $statusID = civicrm_api3('ContributionRecur', 'getvalue', [
      'id' => $paymentPlanID,
      'return' => 'contribution_status_id',
    ]);

    return (string) CRM_Core_PseudoConstant::getName('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', $statusID);
  }

}
