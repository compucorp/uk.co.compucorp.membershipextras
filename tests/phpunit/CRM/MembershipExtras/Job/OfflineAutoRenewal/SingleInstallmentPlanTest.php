<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_AutoMembershipUpgradeRule as AutoMembershipUpgradeRuleFabricator;
use CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlan as SingleInstallmentRenewalJob;

/**
 * Class CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlanTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlanTest extends BaseHeadlessTest {

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
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithMembershipEndDateEqualTodayDateWillRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +1 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year'));
  }

  public function testRenewalWithMembershipEndDateLargerThanTodayDateWillNotRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +2 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

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
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithNonCompletedInstallmentWillRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithCancelledPaymentPlanWillNotRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithMembershipEndDateLargerThanDateToRenewInAdvanceWillRenew() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_days_to_renew_in_advance' => 10,
    ]);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +2 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year +1 day'));
  }

  public function testRenewalWithMembershipEndDateLessThanDateToRenewInAdvanceWillNotRenew() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_days_to_renew_in_advance' => 5,
    ]);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +15 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year +14 day'));
  }

  public function testRenewalWithUpdateStartDateOnRenewalSettingOffWillNotUpdateMembershipStartDate() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_update_start_date_renewal' => 0,
    ]);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +1 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

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
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

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
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

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
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $subscriptionLineItems = $this->getSubscriptionLineItems($paymentPlan['id']);

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $paymentPlan['id'],
    ]);
    $expectedFirstLineItemValues = [
      'start_date' => $paymentPlanMembershipOrder->membershipStartDate . ' 00:00:00',
      'end_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year  -1 day')),
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membershipId,
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
    ];
    $expectedSecondLineItemValues = [
      'start_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year')),
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membershipId,
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
    ];

    $correctSubscriptionLineItems = FALSE;
    if (count($subscriptionLineItems) == 2) {
      $isCorrectFirstLineItem = $expectedFirstLineItemValues == $subscriptionLineItems[0];
      $isCorrectSecondLineItem = $expectedSecondLineItemValues == $subscriptionLineItems[1];
      if ($isCorrectFirstLineItem && $isCorrectSecondLineItem) {
        $correctSubscriptionLineItems = TRUE;
      }
    }

    $this->assertTrue($correctSubscriptionLineItems);
  }

  public function testRenewingMembershipWithEndDateLargerThanDateToRenewInAdvanceWillCreateCorrectSubscriptionLineItems() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_days_to_renew_in_advance' => 10,
    ]);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +2 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $subscriptionLineItems = $this->getSubscriptionLineItems($paymentPlan['id']);

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $paymentPlan['id'],
    ]);
    $expectedFirstLineItemValues = [
      'start_date' => $paymentPlanMembershipOrder->membershipStartDate . ' 00:00:00',
      'end_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year  -1 day')),
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membershipId,
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
    ];
    $expectedSecondLineItemValues = [
      'start_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year')),
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membershipId,
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
    ];

    $correctSubscriptionLineItems = FALSE;
    if (count($subscriptionLineItems) == 2) {
      $isCorrectFirstLineItem = $expectedFirstLineItemValues == $subscriptionLineItems[0];
      $isCorrectSecondLineItem = $expectedSecondLineItemValues == $subscriptionLineItems[1];
      if ($isCorrectFirstLineItem && $isCorrectSecondLineItem) {
        $correctSubscriptionLineItems = TRUE;
      }
    }

    $this->assertTrue($correctSubscriptionLineItems);
  }

  public function testRenewingMembershipWillCreateCorrectMembershipPaymentRecords() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $membershipId = (int) civicrm_api3('Membership', 'getvalue', [
      'return' => 'id',
      'contribution_recur_id' => $paymentPlan['id'],
    ]);
    $membershipPaymentRecordsCount = civicrm_api3('MembershipPayment', 'getcount', [
      'membership_id' => $membershipId,
    ]);

    $this->assertEquals(2, $membershipPaymentRecordsCount);
  }

  public function testRenewingUpgradableMembershipWithWillCreateUpgradedMembershipAndEndCurrentOne() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $createdMemberships = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ])['values'];

    $isFirstMembershipEnded = $createdMemberships[0]['end_date'] == date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +1 year -1 day'));

    $expectedUpgradedMembershipValues = [
      'start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +1 year')),
      'end_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +2 year -1 day')),
      'membership_type_id' => $testUpgradeMembershipType['id'],
    ];

    $isCorrectUpgradedMembershipValues = TRUE;
    foreach ($expectedUpgradedMembershipValues as $testParam => $expectedValue) {
      if ($createdMemberships[1][$testParam] != $expectedValue) {
        $isCorrectUpgradedMembershipValues = FALSE;
      }
    }

    $isCorrectlyUpgradedMembership = $isCorrectUpgradedMembershipValues && $isFirstMembershipEnded;
    $this->assertTrue($isCorrectlyUpgradedMembership);
  }

  public function testRenewingUpgradableMembershipWillCreateCorrectMembershipPaymentRecords() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $createdMemberships = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ])['values'];

    $isAllMembershipPaymentRecordsCreated = TRUE;
    foreach ($createdMemberships as $membership) {
      $membershipPaymentsCount = civicrm_api3('MembershipPayment', 'getcount', [
        'sequential' => 1,
        'membership_id' => $membership['id'],
      ]);

      if ($membershipPaymentsCount != 1) {
        $isAllMembershipPaymentRecordsCreated = FALSE;
      }
    }

    $this->assertTrue($isAllMembershipPaymentRecordsCreated);
  }

  public function testRenewingUpgradableMembershipWithWillCreateCorrectSubscriptionLineItems() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $testUpgradeMembershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Test Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 120,
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

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $subscriptionLineItems = $this->getSubscriptionLineItems($paymentPlan['id']);

    $testUpgradeMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $testUpgradeMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $memberships = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ])['values'];

    $expectedFirstLineItemValues = [
      'start_date' => $paymentPlanMembershipOrder->membershipStartDate . ' 00:00:00',
      'end_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year  -1 day')),
      'entity_table' => 'civicrm_membership',
      'entity_id' => $memberships[0]['id'],
      'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
      'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
    ];
    $expectedSecondLineItemValues = [
      'start_date' => date('Y-m-d 00:00:00', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' + 1 year')),
      'end_date' => '',
      'entity_table' => 'civicrm_membership',
      'entity_id' => $memberships[1]['id'],
      'price_field_id' => $testUpgradeMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $testUpgradeMembershipTypePriceFieldValue['id'],
      'unit_price' => $testUpgradeMembershipTypePriceFieldValue['amount'],
    ];

    $correctSubscriptionLineItems = FALSE;
    if (count($subscriptionLineItems) == 2) {
      $isCorrectFirstLineItem = $expectedFirstLineItemValues == $subscriptionLineItems[0];
      $isCorrectSecondLineItem = $expectedSecondLineItemValues == $subscriptionLineItems[1];
      if ($isCorrectFirstLineItem && $isCorrectSecondLineItem) {
        $correctSubscriptionLineItems = TRUE;
      }
    }

    $this->assertTrue($correctSubscriptionLineItems);
  }

  public function testRenewalWithMembershipFeeEqualZeroWillRenew() {
    $zeroFeeMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Rolling Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 0,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);

    $zeroFeeMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $zeroFeeMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $zeroFeeMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $zeroFeeMembershipTypePriceFieldValue['id'],
      'label' => $zeroFeeMembershipType['name'],
      'qty' => 1,
      'unit_price' => $zeroFeeMembershipTypePriceFieldValue['amount'],
      'line_total' => $zeroFeeMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $singleInstallmentRenewal = new SingleInstallmentRenewalJob();
    $singleInstallmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  /**
   * Checks if the membership is renewed
   * by checking the contibutions count
   * assoisated with it as well as the expected
   * end date after renewal.
   *
   * @param int $paymentPlanId
   * @param String $expectedNewEndDateOffset
   *
   * @return bool
   */
  private function isPaymentPlanMembershipRenewed($paymentPlanId, $expectedNewEndDateOffset) {
    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanId,
    ])['values'][0];

    $contributionCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $paymentPlanId,
    ]);

    if ((date('Y-m-d', strtotime($expectedNewEndDateOffset)) == $membership['end_date']) && ($contributionCount == 2)) {
      return TRUE;
    }

    return FALSE;
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
        li.unit_price 
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

}
