<?php

/**
 * Class CRM_MembershipExtras_Hook_Pre_ContributionRecurTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_Pre_ContributionRecurTest extends BaseHeadlessTest {

  public function testPreventUpdatingNextScheduledContributionDateForManualRecurContribution() {
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

  public function testKeepingNextScheduledContributionDateForNonManualRecurContribution() {
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

}
