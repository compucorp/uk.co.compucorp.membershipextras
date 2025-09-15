<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_AutoMembershipUpgradeRule as AutoMembershipUpgradeRuleFabricator;
use CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstalmentPlan as MultipleInstalmentRenewalJob;

/**
 * Class CRM_MembershipExtras_Job_OfflineAutoRenewal_MultiInstalmentPlanTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_MultiInstalmentPlanTest extends CRM_MembershipExtras_Job_OfflineAutoRenewal_BaseRenewalJobTestHelper {

  public function testRenewalWithMembershipEndDateLessThanTodayDateWillRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day'));
  }

  public function testRenewalWithMembershipEndDateEqualTodayDateWillRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +1 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year'));
  }

  public function testRenewalWithMembershipEndDateLargerThanTodayDateWillNotRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +2 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day'));
  }

  public function testRenewalWithNonOfflinePaymentProcessorPaymentPlanWillNotRenew() {
    civicrm_api3('PaymentProcessor', 'create', [
      'payment_processor_type_id' => 'Dummy',
      'financial_account_id' => 'Payment Processor Account',
      'title' => 'Dummy Processor',
      'name' => 'Dummy Processor',
    ]);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->paymentProcessor = 'Dummy Processor';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithNonCompletedInstalmentWillRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day'));
  }

  public function testRenewalWithCancelledPaymentPlanWillNotRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Cancelled';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithMembershipEndDateLargerThanDateToRenewInAdvanceWillRenew() {
    Civi::settings()->set('membershipextras_paymentplan_days_to_renew_in_advance', 10);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +2 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year +1 day'));
  }

  public function testRenewalWithMembershipEndDateLessThanDateToRenewInAdvanceWillNotRenew() {
    Civi::settings()->set('membershipextras_paymentplan_days_to_renew_in_advance', 5);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +15 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year +14 day'));
  }

  public function testRenewalWithUpdateStartDateOnRenewalSettingOffWillNotUpdateMembershipStartDate() {
    Civi::settings()->set('membershipextras_paymentplan_update_start_date_renewal', 0);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +1 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $nextPeriodId,
    ])['values'][0];
    $this->assertEquals($paymentPlanMembershipOrder->membershipStartDate, $membership['start_date']);
  }

  public function testRenewalWithUpdateStartDateOnRenewalSettingOnWillUpdateMembershipStartDate() {
    Civi::settings()->set('membershipextras_paymentplan_update_start_date_renewal', 1);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +1 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $nextPeriodId,
    ])['values'][0];
    $expectedNewMembershipStartDate = date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +1 year'));
    $this->assertEquals($expectedNewMembershipStartDate, $membership['start_date']);
  }

  public function testNewCopyOfLineItemsWillBeCreatedAfterRenewalInsteadOfReusingTheExistingOnes() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $nextPeriodId,
    ]);

    $copyLineItemsCount = civicrm_api3('LineItem', 'getcount', [
      'sequential' => 1,
      'entity_id' => $membershipId,
      'entity_table' => 'civicrm_membership',
      'contribution_id' => ['IS NULL' => 1],
      'options' => ['limit' => 0],
    ]);

    $this->assertEquals(2, $copyLineItemsCount);
  }

  public function testRenewalWillCreateCorrectSubscriptionLineItems() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $currentPeriodSubscriptionLineItems = $this->getSubscriptionLineItems($paymentPlan['id']);
    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);
    $nextPeriodSubscriptionLineItems = $this->getSubscriptionLineItems($nextPeriodId);

    // only two subscription line item is created for multi-instalment payment plans same
    // as in single-instalment (one for current period and one for next period.
    $isCorrectLineItemsCount = FALSE;
    if (count($currentPeriodSubscriptionLineItems) == 1 && count($nextPeriodSubscriptionLineItems) == 1) {
      $isCorrectLineItemsCount = TRUE;
    }

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $nextPeriodId,
    ]);
    $expectedFirstLineItemValues = [
      'start_date' => $paymentPlanMembershipOrder->membershipStartDate . ' 00:00:00',
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membershipId,
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'line_total' => '120.00',
      'label' => 'Test Rolling Membership',
    ];
    $expectedSecondLineItemValues = [
      'start_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year')),
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membershipId,
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'line_total' => '120.00',
      'label' => 'Test Rolling Membership',
    ];

    $correctSubscriptionLineItems = FALSE;
    if ($isCorrectLineItemsCount) {
      $isCorrectFirstLineItem = $expectedFirstLineItemValues == $currentPeriodSubscriptionLineItems[0];
      $isCorrectSecondLineItem = $expectedSecondLineItemValues == $nextPeriodSubscriptionLineItems[0];
      if ($isCorrectFirstLineItem && $isCorrectSecondLineItem) {
        $correctSubscriptionLineItems = TRUE;
      }
    }

    $this->assertTrue($correctSubscriptionLineItems);
  }

  public function testRenewingMembershipWillCreateCorrectMembershipPaymentRecords() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $nextPeriodId,
    ]);
    $membershipPaymentRecordsCount = civicrm_api3('MembershipPayment', 'getcount', [
      'membership_id' => $membershipId,
      'options' => ['limit' => 0],
    ]);

    $this->assertEquals(24, $membershipPaymentRecordsCount);
  }

  public function testRenewingUpgradableMembershipWithWillCreateUpgradedMembershipAndEndCurrentOne() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $testUpgradeMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Upgrade Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 100,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testRollingMembershipType['id'],
      'to_membership_type_id' => $testUpgradeMembershipType['id'],
      'upgrade_trigger_date_type' => CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit::YEARS,
    ]);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $currentMembership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ])['values'][0];
    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);
    $upgradedMembership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $nextPeriodId,
    ])['values'][0];

    $isFirstMembershipEnded = $currentMembership['end_date'] == date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +1 year -1 day'));

    $expectedUpgradedMembershipValues = [
      'start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +1 year')),
      'end_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +2 year -1 day')),
      'membership_type_id' => $testUpgradeMembershipType['id'],
    ];

    $isCorrectUpgradedMembershipValues = TRUE;
    foreach ($expectedUpgradedMembershipValues as $testParam => $expectedValue) {
      if ($upgradedMembership[$testParam] != $expectedValue) {
        $isCorrectUpgradedMembershipValues = FALSE;
      }
    }

    $isCorrectlyUpgradedMembership = $isCorrectUpgradedMembershipValues && $isFirstMembershipEnded;
    $this->assertTrue($isCorrectlyUpgradedMembership);
  }

  public function testRenewingUpgradableMembershipWillCreateCorrectMembershipPaymentRecords() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $testUpgradeMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Upgrade Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 100,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testRollingMembershipType['id'],
      'to_membership_type_id' => $testUpgradeMembershipType['id'],
      'upgrade_trigger_date_type' => CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit::YEARS,
    ]);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $createdMemberships = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => ['IN' => [$paymentPlan['id'], $nextPeriodId]],
    ])['values'];

    $isAllMembershipPaymentRecordsCreated = TRUE;
    foreach ($createdMemberships as $membership) {
      $membershipPaymentsCount = civicrm_api3('MembershipPayment', 'getcount', [
        'sequential' => 1,
        'membership_id' => $membership['id'],
      ]);

      if ($membershipPaymentsCount != 12) {
        $isAllMembershipPaymentRecordsCreated = FALSE;
      }
    }

    $isAllMembershipPaymentRecordsCreated = $isAllMembershipPaymentRecordsCreated && count($createdMemberships) == 2;

    $this->assertTrue($isAllMembershipPaymentRecordsCreated);
  }

  private function isPaymentPlanMembershipRenewed($paymentPlanId, $expectedNewEndDateOffset) {
    $contributionCount = 0;

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlanId);

    // Membership should be linked to the new recur contribution.
    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $nextPeriodId,
    ])['values'][0];

    if (!empty($nextPeriodId)) {
      $contributionCount = civicrm_api3('Contribution', 'getcount', [
        'contribution_recur_id' => $nextPeriodId,
      ]);
    }

    if ((date('Y-m-d', strtotime($expectedNewEndDateOffset)) == $membership['end_date']) && ($contributionCount == 12)) {
      return TRUE;
    }

    return FALSE;
  }

  public function testRenewingUpgradableMembershipWithWillCreateCorrectSubscriptionLineItems() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $testUpgradeMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Upgrade Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 100,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testRollingMembershipType['id'],
      'to_membership_type_id' => $testUpgradeMembershipType['id'],
      'upgrade_trigger_date_type' => CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit::YEARS,
    ]);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $currentPeriodSubscriptionLineItems = $this->getSubscriptionLineItems($paymentPlan['id']);
    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);
    $nextPeriodSubscriptionLineItems = $this->getSubscriptionLineItems($nextPeriodId);

    $isCorrectLineItemsCount = FALSE;
    if (count($currentPeriodSubscriptionLineItems) == 1 && count($nextPeriodSubscriptionLineItems) == 1) {
      $isCorrectLineItemsCount = TRUE;
    }

    $testUpgradeMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $testUpgradeMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $memberships = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => ['IN' => [$paymentPlan['id'], $nextPeriodId]],
    ])['values'];

    $expectedFirstLineItemValues = [
      'start_date' => $paymentPlanMembershipOrder->membershipStartDate . ' 00:00:00',
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $memberships[0]['id'],
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'line_total' => '120.00',
      'label' => 'Test Rolling Membership',
    ];
    $expectedSecondLineItemValues = [
      'start_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year')),
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $memberships[1]['id'],
      'price_field_id' => $testUpgradeMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $testUpgradeMembershipTypePriceFieldValue['id'],
      'line_total' => '8.33',
      'label' => 'Test Upgrade Membership',
    ];

    $correctSubscriptionLineItems = FALSE;
    if ($isCorrectLineItemsCount) {
      $isCorrectFirstLineItem = $expectedFirstLineItemValues == $currentPeriodSubscriptionLineItems[0];
      $isCorrectSecondLineItem = $expectedSecondLineItemValues == $nextPeriodSubscriptionLineItems[0];
      if ($isCorrectFirstLineItem && $isCorrectSecondLineItem) {
        $correctSubscriptionLineItems = TRUE;
      }
    }

    $this->assertTrue($correctSubscriptionLineItems);
  }

  public function testRenewalWithNonRenewableLineOnCurrentPeriodAndNewMembershipForNextPeriod() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 years'));
    $paymentPlanMembershipOrder->nextContributionDate = date('Y-m-d', strtotime('-1 years'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
    $this->addRenewableNewMembershipToNextPeriodOnly($paymentPlan, [
      'name' => 'Test New Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 1200,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);
    $this->assertPaymentPlanStructureIsOk($nextPeriodID, [
      'total_amount' => 1200,
      'plan_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_item_count' => 1,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
      'installments' => 12,
    ]);
  }

  public function testRenewalWithMultipleLinesNotRenewingOnCurrentPeriodAndNewMembershipForNextPeriod() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 6,
      'duration_unit' => 'month',
    ]);
    $addOnMembershipType = $this->createMembershipType([
      'name' => 'Add-on Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 120,
      'duration_interval' => 18,
      'duration_unit' => 'month',
    ]);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 years'));
    $paymentPlanMembershipOrder->nextContributionDate = date('Y-m-d', strtotime('-1 years'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems = [
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $mainMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $mainMembershipType->priceFieldValue['id'],
        'label' => $mainMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $mainMembershipType->priceFieldValue['amount'] / 12,
        'line_total' => $mainMembershipType->priceFieldValue['amount'] / 12,
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 0,
        'end_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +6 months -1 day')),
      ],
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $addOnMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $addOnMembershipType->priceFieldValue['id'],
        'label' => $addOnMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $addOnMembershipType->priceFieldValue['amount'] / 12,
        'line_total' => $addOnMembershipType->priceFieldValue['amount'] / 12,
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 0,
        'end_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +18 months -1 day')),
      ],
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
    $this->addRenewableNewMembershipToNextPeriodOnly($paymentPlan, [
      'name' => 'New Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 240,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $this->assertPaymentPlanStructureIsOk($nextPeriodID, [
      'total_amount' => 240,
      'plan_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +18 months')),
      'line_item_count' => 1,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +18 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
      'installments' => 12,
    ]);
  }

  public function testRenewalWithMultipleLinesSomeRenewingOnCurrentPeriodAndNewMembershipForNextPeriod() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 18,
      'duration_unit' => 'month',
    ]);
    $addOnMembershipType = $this->createMembershipType([
      'name' => 'Add-on Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 120,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);
    $secondAddOnMembershipType = $this->createMembershipType([
      'name' => 'Second Add-on Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 180,
      'duration_interval' => 6,
      'duration_unit' => 'month',
    ]);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 years'));
    $paymentPlanMembershipOrder->nextContributionDate = date('Y-m-d', strtotime('-1 years'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems = [
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $mainMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $mainMembershipType->priceFieldValue['id'],
        'label' => $mainMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $mainMembershipType->priceFieldValue['amount'] / 12,
        'line_total' => $mainMembershipType->priceFieldValue['amount'] / 12,
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 0,
        'end_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +18 months -1 day')),
      ],
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $addOnMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $addOnMembershipType->priceFieldValue['id'],
        'label' => $addOnMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $addOnMembershipType->priceFieldValue['amount'] / 12,
        'line_total' => $addOnMembershipType->priceFieldValue['amount'] / 12,
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 1,
        'end_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months -1 day')),
      ],
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $secondAddOnMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $secondAddOnMembershipType->priceFieldValue['id'],
        'label' => $secondAddOnMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $secondAddOnMembershipType->priceFieldValue['amount'] / 12,
        'line_total' => $secondAddOnMembershipType->priceFieldValue['amount'] / 12,
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 0,
        'end_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +6 months -1 day')),
      ],
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
    $this->addRenewableNewMembershipToNextPeriodOnly($paymentPlan, [
      'name' => 'New Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 240,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $this->assertPaymentPlanStructureIsOk($nextPeriodID, [
      'total_amount' => 360,
      'plan_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_item_count' => 2,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
      'installments' => 12,
    ]);
  }

  public function testRenewalWillUpdateNextScheduledContributionDateToOneMonthAfterLastContributionDate() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $lastContributionReceiveDate = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $nextPeriodID,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0]['receive_date'];
    $expectedNextDate = date('Y-m-d 00:00:00', strtotime('+1 month', strtotime($lastContributionReceiveDate)));

    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $nextPeriodID,
    ]);

    $this->assertEquals($expectedNextDate, $nextDate);
  }

  public function testRenewalWillNotCopyContributionFeeAmountFromPreviousTerm() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +1 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    // updating the fees for the previous term last contribution
    $previousTermLastContributionId = $this->getPaymentPlanContributions($paymentPlan['id'])[11]['id'];
    civicrm_api3('Contribution', 'create', [
      'id' => $previousTermLastContributionId,
      'fee_amount' => 10,
    ]);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $newTermContributionFees = $this->getPaymentPlanContributions($nextPeriodId)[0]['fee_amount'];
    $this->assertEquals('0.00', $newTermContributionFees);
  }

  public function testSecondTermContributionsAfterRenewalAreCreatedFromRecurringContributionDataInsteadOfLastTermContribution() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    $paymentPlanMembershipOrder->financialType = 'Member Dues';
    $paymentPlanMembershipOrder->paymentMethod = 'EFT';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $firstTermLastContributionId = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['id'],
      'contribution_recur_id' => $paymentPlan['id'],
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0]['id'];

    // Update first-term last contribution data
    civicrm_api3('Contribution', 'create', [
      'id' => $firstTermLastContributionId,
      'payment_instrument_id' => 'Cash',
      'financial_type_id' => 'Donation',
    ]);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);
    $secondTermFirstContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['payment_instrument_id', 'financial_type_id'],
      'contribution_recur_id' => $nextPeriodID,
      'options' => ['limit' => 1, 'sort' => 'id ASC'],
    ])['values'][0];

    $this->assertEquals('EFT', $secondTermFirstContribution['payment_instrument']);

    $memberDuesFinancialTypeId = 2;
    $this->assertEquals($memberDuesFinancialTypeId, $secondTermFirstContribution['financial_type_id']);
  }

  public function testWillNotRenewPaymentPlansLinkedToPaymentScheme() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->paymentSchemeId = $this->createPaymentScheme()->id;
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $multipleInstalmentRenewal = new MultipleInstalmentRenewalJob();
    $multipleInstalmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day'));
  }

  /**
   * Checks the structure of the payment plan follows the given expected values.
   *
   * @param int $nextPeriodID
   * @param array $expectedValues
   *   Must have the following structure:
   *   [
   *     'total_amount' => 240,
   *     'plan_start_date' => '2020-11-23',
   *     'line_start_date' => '2020-11-23',
   *     'line_item_count' => 1,
   *     'membership_start_date' => '2020-11-23',
   *     'membership_end_date_offset' => ' +1 year -1 day',
   *     'first_receive_date' => '2020-11-23',
   *     'installments' => 12,
   *   ]
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function assertPaymentPlanStructureIsOk($nextPeriodID, $expectedValues) {
    $newRecurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $nextPeriodID,
    ])['values'][0];
    $this->assertEquals(
      $expectedValues['total_amount'] / $expectedValues['installments'],
      $newRecurringContribution['amount']
    );
    $this->assertEquals(
      $expectedValues['plan_start_date'],
      date('Y-m-d', strtotime($newRecurringContribution['start_date']))
    );

    $lineItems = $this->getSubscriptionLineItems($nextPeriodID);
    $this->assertEquals($expectedValues['line_item_count'], count($lineItems));

    $line = array_pop($lineItems);
    $this->assertEquals(
      $expectedValues['line_start_date'],
      date('Y-m-d', strtotime($line['start_date']))
    );

    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => $line['entity_id'],
    ])['values'][0];
    $this->assertEquals(
      $expectedValues['membership_start_date'],
      date('Y-m-d', strtotime($membership['start_date']))
    );
    $this->assertEquals(
      date('Y-m-d', strtotime($line['start_date'] . ' ' . $expectedValues['membership_end_date_offset'])),
      date('Y-m-d', strtotime($membership['end_date']))
    );

    $contributions = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $nextPeriodID,
      'options' => ['limit' => 0],
    ]);
    $this->assertEquals($expectedValues['installments'], $contributions['count']);
    $this->assertEquals(
      $expectedValues['first_receive_date'],
      date('Y-m-d', strtotime($contributions['values'][0]['receive_date']))
    );

    foreach ($contributions['values'] as $payment) {
      $this->assertEquals(
        $expectedValues['total_amount'] / $expectedValues['installments'],
        $payment['total_amount']
      );
    }
  }

}
