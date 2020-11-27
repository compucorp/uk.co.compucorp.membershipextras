<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_AutoMembershipUpgradeRule as AutoMembershipUpgradeRuleFabricator;
use CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstallmentPlan as MultipleInstallmentRenewalJob;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringLineItem as RecurringLineItemFabricator;

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
        li.entity_id, li.price_field_id, li.price_field_value_id,
        li.line_total, li.label
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

  public function testRenewalWithNonRenewableLineOnCurrentPeriodAndNewMembershipForNextPeriod() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 years'));
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);
    $this->assertPaymentPlanStructureIsOk($nextPeriodID, [
      'total_amount' => 1200,
      'plan_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_item_count' => 1,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'installments' => 12,
    ]);
  }

  /**
   * Adds a new membership line item to the next period of the given payment plan.
   *
   * @param array $paymentPlan
   * @param array $membershipParams
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function addRenewableNewMembershipToNextPeriodOnly($paymentPlan, $membershipParams) {
    $membershipTypeObject = $this->createMembershipType($membershipParams);
    $membershipType = $membershipTypeObject->membershipType;
    $membershipTypePriceFieldValue = $membershipTypeObject->priceFieldValue;

    $newLineItem = LineItemFabricator::fabricate([
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id' => $paymentPlan['id'],
      'price_field_id' => $membershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $membershipTypePriceFieldValue['id'],
      'label' => $membershipType['name'],
      'qty' => 1,
      'unit_price' => $membershipTypePriceFieldValue['amount'] / $paymentPlan['installments'],
      'line_total' => $membershipTypePriceFieldValue['amount'] / $paymentPlan['installments'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ]);
    RecurringLineItemFabricator::fabricate([
      'contribution_recur_id' => $paymentPlan['id'],
      'line_item_id' => $newLineItem['id'],
      'auto_renew' => 1,
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $this->assertPaymentPlanStructureIsOk($nextPeriodID, [
      'total_amount' => 240,
      'plan_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +18 months')),
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +18 months')),
      'line_item_count' => 1,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +18 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +18 months')),
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

    $multipleInstallmentRenewal = new MultipleInstallmentRenewalJob();
    $multipleInstallmentRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $this->assertPaymentPlanStructureIsOk($nextPeriodID, [
      'total_amount' => 360,
      'plan_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_item_count' => 2,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'installments' => 12,
    ]);
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

  /**
   * Helper function to create memberships and its default price field value.
   *
   * @param array $params
   *
   * @return \stdClass
   * @throws \CiviCRM_API3_Exception
   */
  private function createMembershipType($params) {
    $membershipType = MembershipTypeFabricator::fabricate($params);
    $priceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $result = new stdClass();
    $result->membershipType = $membershipType;
    $result->priceFieldValue = $priceFieldValue;

    return $result;
  }

}
