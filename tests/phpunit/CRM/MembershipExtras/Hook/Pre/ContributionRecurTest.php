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

  /**
   * The "In Progress" contribution status option a test removed.
   *
   * @var array|null
   */
  private $removedInProgressStatus = NULL;

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
   * Puts back the "In Progress" contribution status a test removed.
   *
   * The test transaction rolls the delete back, but not the cached status
   * lists, so they are flushed here for the next test.
   */
  protected function tearDown(): void {
    if (is_array($this->removedInProgressStatus)) {
      \Civi\Api4\OptionValue::create(FALSE)
        ->setValues($this->removedInProgressStatus)
        ->execute();
      $this->removedInProgressStatus = NULL;
    }
    CRM_Core_PseudoConstant::flush();
    Civi::cache('metadata')->clear();

    parent::tearDown();
  }

  /**
   * Removes "In Progress" from the contribution status options, as on
   * CiviCRM 6.x, where it is only a recurring contribution status.
   */
  private function removeInProgressFromContributionStatuses() {
    $inProgress = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('option_group_id', 'label', 'value', 'name', 'weight', 'is_active', 'is_reserved', 'filter')
      ->addWhere('option_group_id:name', '=', 'contribution_status')
      ->addWhere('name', '=', 'In Progress')
      ->execute()
      ->first();
    if (is_array($inProgress)) {
      \Civi\Api4\OptionValue::delete(FALSE)
        ->addWhere('id', '=', $inProgress['id'])
        ->execute();
      unset($inProgress['id']);
      $this->removedInProgressStatus = $inProgress;
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

  public function testPaymentOnCancelledMembershipPlanKeepsThePlanCancelled() {
    $paymentPlan = $this->createMembershipPaymentPlan('Yearly');
    $this->forcePaymentPlanStatus($paymentPlan['id'], 'Cancelled');
    $planBeforePayment = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $paymentPlan['id']]);

    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'contribution_recur_id' => $paymentPlan['id'],
    ]);
    civicrm_api3('Payment', 'create', [
      'contribution_id' => $contribution['id'],
      'total_amount' => $contribution['total_amount'],
      'trxn_date' => date('Y-m-d H:i:s'),
      'is_send_contribution_notification' => 0,
    ]);

    $contributionStatus = civicrm_api3('Contribution', 'getvalue', [
      'id' => $contribution['id'],
      'return' => 'contribution_status_id',
    ]);
    $planAfterPayment = civicrm_api3('ContributionRecur', 'getsingle', ['id' => $paymentPlan['id']]);

    $this->assertEquals($this->getContributionStatusId('Completed'), $contributionStatus);
    $this->assertEquals($this->getContributionStatusId('Cancelled'), $planAfterPayment['contribution_status_id']);
    $this->assertEquals($planBeforePayment['end_date'] ?? NULL, $planAfterPayment['end_date'] ?? NULL);
    $this->assertEquals($planBeforePayment['next_sched_contribution_date'] ?? NULL, $planAfterPayment['next_sched_contribution_date'] ?? NULL);
  }

  public function testCancelledMembershipPlanIsNotMarkedAsCompleted() {
    $paymentPlan = $this->createMembershipPaymentPlan('Monthly');
    $this->forcePaymentPlanStatus($paymentPlan['id'], 'Cancelled');

    $paymentPlanParams = [
      'contribution_status_id' => $this->getContributionStatusId('Completed'),
      'end_date' => 'now',
      'next_sched_contribution_date' => NULL,
    ];

    $hook = new CRM_MembershipExtras_Hook_Pre_ContributionRecur('edit', $paymentPlan['id'], $paymentPlanParams);
    $hook->preProcess();

    $this->assertArrayNotHasKey('contribution_status_id', $paymentPlanParams);
    $this->assertArrayNotHasKey('end_date', $paymentPlanParams);
    $this->assertArrayNotHasKey('next_sched_contribution_date', $paymentPlanParams);
  }

  public function testCancelledRecurringContributionNotLinkedToMembershipCanBeMarkedAsCompleted() {
    $recurringContributionID = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => 1,
      'amount' => 10,
      'frequency_interval' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'contribution_status_id' => 'Cancelled',
    ])['id'];

    $completedStatusId = $this->getContributionStatusId('Completed');
    $paymentPlanParams = ['contribution_status_id' => $completedStatusId];

    $hook = new CRM_MembershipExtras_Hook_Pre_ContributionRecur('edit', $recurringContributionID, $paymentPlanParams);
    $hook->preProcess();

    $this->assertEquals($completedStatusId, $paymentPlanParams['contribution_status_id']);
  }

  private function createMembershipPaymentPlan($frequency) {
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Test Rolling Membership',
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

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->paymentPlanFrequency = $frequency;
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $priceFieldValue['price_field_id'],
      'price_field_value_id' => $priceFieldValue['id'],
      'label' => $membershipType['name'],
      'qty' => 1,
      'unit_price' => $priceFieldValue['amount'],
      'line_total' => $priceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];

    return PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
  }

  /**
   * The pre-hook rectifies statuses set through the API, so this uses SQL.
   */
  private function forcePaymentPlanStatus($recurringContributionId, $status) {
    CRM_Core_DAO::executeQuery('UPDATE civicrm_contribution_recur SET contribution_status_id = %1 WHERE id = %2', [
      1 => [$this->getContributionStatusId($status), 'Integer'],
      2 => [$recurringContributionId, 'Integer'],
    ]);
  }

  private function getContributionStatusId($name) {
    return CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $name);
  }

}
