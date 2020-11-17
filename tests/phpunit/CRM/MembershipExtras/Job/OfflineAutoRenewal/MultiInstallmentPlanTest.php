<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_AutoMembershipUpgradeRule as AutoMembershipUpgradeRuleFabricator;
use CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstallmentPlan as MultipleInstallmentRenewalJob;

/**
 * Class CRM_MembershipExtras_Job_OfflineAutoRenewal_MultiInstallmentPlanTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_MultiInstallmentPlanTest extends BaseHeadlessTest {

  /**
   * A rolling membership type that we
   * will use for creating payment plan orders.
   *
   * @var array
   */
  private $testRollingMembershipType;

  /**
   * The PriceFieldValue entity for the
   * test rolling membership type.
   *
   * @var array
   */
  private $testRollingMembershipTypePriceFieldValue;

  public function setUp() {
    $this->setDefaultPaymentPlanSettings();
    $this->createTestRollingMembershipType();
  }

  private function setDefaultPaymentPlanSettings() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_days_to_renew_in_advance' => 0,
    ]);

    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_update_start_date_renewal' => 0,
    ]);
  }

  private function createTestRollingMembershipType() {
    $this->testRollingMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Rolling Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 120,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);

    $this->testRollingMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->testRollingMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];
  }

  public function testRenewalWithMembershipEndDateLessThanTodayDateWillRenew() {
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year +1 day'));
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithNonCompletedInstallmentWillRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithMembershipEndDateLargerThanDateToRenewInAdvanceWillRenew() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_days_to_renew_in_advance' => 10,
    ]);

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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year +1 day'));
  }

  public function testRenewalWithMembershipEndDateLessThanDateToRenewInAdvanceWillNotRenew() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_days_to_renew_in_advance' => 5,
    ]);

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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year +14 day'));
  }

  public function testRenewalWithUpdateStartDateOnRenewalSettingOffWillNotUpdateMembershipStartDate() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_update_start_date_renewal' => 0,
    ]);

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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ])['values'][0];
    $this->assertEquals($paymentPlanMembershipOrder->membershipStartDate, $membership['start_date']);
  }

  public function testRenewalWithUpdateStartDateOnRenewalSettingOnWillUpdateMembershipStartDate() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_update_start_date_renewal' => 1,
    ]);

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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $paymentPlan['id'],
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

  public function testRenewingMembershipWithEndDateLessThanTodayDateWillCreateCorrectSubscriptionLineItems() {
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $currentPeriodSubscriptionLineItems = $this->getSubscriptionLineItems($paymentPlan['id']);
    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);
    $nextPeriodSubscriptionLineItems = $this->getSubscriptionLineItems($nextPeriodId);

    // only two subscription line item is created for multi-installment payment plans same
    // as in single-installment (one for current period and one for next period.
    $isCorrectLineItemsCount = FALSE;
    if (count($currentPeriodSubscriptionLineItems) == 1 && count($nextPeriodSubscriptionLineItems) == 1) {
      $isCorrectLineItemsCount = TRUE;
    }

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $paymentPlan['id'],
    ]);
    $expectedFirstLineItemValues = [
      'start_date' => $paymentPlanMembershipOrder->membershipStartDate . ' 00:00:00',
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membershipId,
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
    ];
    $expectedSecondLineItemValues = [
      'start_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year')),
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membershipId,
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $paymentPlan['id'],
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

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
    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanId,
    ])['values'][0];

    $contributionCount = 0;

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlanId);
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

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
    ];
    $expectedSecondLineItemValues = [
      'start_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year')),
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $memberships[1]['id'],
      'price_field_id' => $testUpgradeMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $testUpgradeMembershipTypePriceFieldValue['id'],
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

  /**
   * Gets the subscription line items
   * (along with the related line item
   * for each one) for the payment plan.
   *
   * @param int $recurringContributionID
   *
   * @return array
   */
  private function getSubscriptionLineItems($recurringContributionID) {
    $q = '
      SELECT msl.start_date, msl.end_date, li.entity_table,
        li.entity_id, li.price_field_id, li.price_field_value_id 
      FROM membershipextras_subscription_line msl 
      INNER JOIN civicrm_line_item li ON msl.line_item_id = li.id 
        WHERE msl.contribution_recur_id = %1 
        AND li.contribution_id IS NULL 
        ORDER BY msl.id ASC
      ';
    $dbResultSet = CRM_Core_DAO::executeQuery($q, [
      1 => [$recurringContributionID, 'Integer'],
    ]);

    $lineItems = [];
    while ($dbResultSet->fetch()) {
      $lineItems[] = $dbResultSet->toArray();
    }

    return $lineItems;
  }

  private function getTheNewRecurContributionIdFromCurrentOne($currentRecurContributionId) {
    $nextPeriodCustomFieldId = $this->getCustomFieldId('related_payment_plan_periods', 'next_period');

    $nextCustomPeriodDetails = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'return' => 'custom_' . $nextPeriodCustomFieldId,
      'id' => $currentRecurContributionId,
    ]);
    if (!empty($nextCustomPeriodDetails['values'][0]['custom_' . $nextPeriodCustomFieldId])) {
      return $nextCustomPeriodDetails['values'][0]['custom_' . $nextPeriodCustomFieldId];
    }

    return NULL;
  }

  private function getCustomFieldId($customGroupName, $customFieldName) {
    return civicrm_api3('CustomField', 'getvalue', [
      'return' => 'id',
      'custom_group_id' => $customGroupName,
      'name' => $customFieldName,
    ]);
  }

}
