<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_AutoMembershipUpgradeRule as AutoMembershipUpgradeRuleFabricator;
use CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstalmentPlan as SingleInstalmentRenewalJob;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringLineItem as RecurringLineItemFabricator;

/**
 * Class CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstalmentPlanTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstalmentPlanTest extends BaseHeadlessTest {

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
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day'));
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '+1 year -1 month -1 day'));
  }

  public function testRenewalWithNonCompletedInstalmentWillRenew() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day'));
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $membership = $this->getPaymentPlanAutorenewableMemberships($paymentPlan['id'])[0];
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $membership = $this->getPaymentPlanAutorenewableMemberships($paymentPlan['id'])[0];
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

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

  public function testRenewalWillCreateCorrectSubscriptionLineItems() {
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $createdMemberships = $this->getPaymentPlanAutorenewableMemberships($paymentPlan['id']);
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $isAllMembershipPaymentRecordsCreated = TRUE;
    $createdMemberships = $this->getPaymentPlanAutorenewableMemberships($paymentPlan['id']);
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $subscriptionLineItems = $this->getSubscriptionLineItems($paymentPlan['id']);

    $testUpgradeMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $testUpgradeMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $memberships = $this->getPaymentPlanAutorenewableMemberships($paymentPlan['id']);

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
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day'));
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
    $membership = $this->getPaymentPlanAutorenewableMemberships($paymentPlanId)[0];
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

  public function testRenewalWithNonRenewableLineOnCurrentPeriodAndNewMembershipForNextPeriod() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-3 year'));
    $paymentPlanMembershipOrder->nextContributionDate = date('Y-m-d', strtotime('-2 years'));
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $this->assertPaymentPlanStructureIsOk($paymentPlan['id'], [
      'total_amount' => 1200,
      'plan_start_date' => $paymentPlanMembershipOrder->membershipStartDate,
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_item_count' => 3,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
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
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-3 years'));
    $paymentPlanMembershipOrder->nextContributionDate = date('Y-m-d', strtotime('-2 years'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems = [
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $mainMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $mainMembershipType->priceFieldValue['id'],
        'label' => $mainMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $mainMembershipType->priceFieldValue['amount'],
        'line_total' => $mainMembershipType->priceFieldValue['amount'],
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
        'unit_price' => $addOnMembershipType->priceFieldValue['amount'],
        'line_total' => $addOnMembershipType->priceFieldValue['amount'],
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 0,
        'end_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months -1 day')),
      ],
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
    $this->addRenewableNewMembershipToNextPeriodOnly($paymentPlan, [
      'name' => 'New Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 480,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $this->assertPaymentPlanStructureIsOk($paymentPlan['id'], [
      'total_amount' => 480,
      'plan_start_date' => $paymentPlanMembershipOrder->membershipStartDate,
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_item_count' => 4,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
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
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-3 years'));
    $paymentPlanMembershipOrder->nextContributionDate = date('Y-m-d', strtotime('-2 years'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems = [
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $mainMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $mainMembershipType->priceFieldValue['id'],
        'label' => $mainMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $mainMembershipType->priceFieldValue['amount'],
        'line_total' => $mainMembershipType->priceFieldValue['amount'],
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
        'unit_price' => $addOnMembershipType->priceFieldValue['amount'],
        'line_total' => $addOnMembershipType->priceFieldValue['amount'],
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
        'unit_price' => $secondAddOnMembershipType->priceFieldValue['amount'],
        'line_total' => $secondAddOnMembershipType->priceFieldValue['amount'],
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
      'minimum_fee' => 1200,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $this->assertPaymentPlanStructureIsOk($paymentPlan['id'], [
      'total_amount' => 1320,
      'plan_start_date' => $paymentPlanMembershipOrder->membershipStartDate,
      'line_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'line_item_count' => 6,
      'membership_start_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->membershipStartDate . ' +12 months')),
      'membership_end_date_offset' => ' +1 year -1 day',
      'first_receive_date' => date('Y-m-d', strtotime($paymentPlanMembershipOrder->nextContributionDate)),
    ]);
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
      'line_total' => $membershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ]);
    RecurringLineItemFabricator::fabricate([
      'contribution_recur_id' => $paymentPlan['id'],
      'line_item_id' => $newLineItem['id'],
      'auto_renew' => 1,
    ]);
  }

  /**
   * Checks the structure of the payment plan follows the given expected values.
   *
   * @param int $paymentPlanID
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
   *   ]
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function assertPaymentPlanStructureIsOk($paymentPlanID, $expectedValues) {
    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $paymentPlanID,
    ])['values'][0];
    $this->assertEquals(
      $expectedValues['total_amount'],
      $recurringContribution['amount']
    );
    $this->assertEquals(
      $expectedValues['plan_start_date'],
      date('Y-m-d', strtotime($recurringContribution['start_date']))
    );

    $lineItems = $this->getSubscriptionLineItems($paymentPlanID);
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
      'contribution_recur_id' => $paymentPlanID,
      'contribution_status_id' => 'Pending',
      'options' => ['limit' => 0],
    ]);
    $this->assertEquals(1, $contributions['count']);
    $this->assertEquals(
      $expectedValues['first_receive_date'],
      date('Y-m-d', strtotime($contributions['values'][0]['receive_date']))
    );
    $this->assertEquals(
      $expectedValues['total_amount'],
      $contributions['values'][0]['total_amount']
    );
  }

  public function testRenewalOfFixedMembershipWillResultInCorrectDates() {
    $startDate = '2019-07-01';
    $endDate = '2019-11-30';
    $endDateAfterRenewal = '2020-11-30';

    $fixedMembershipType = $this->createMembershipType([
      'name' => 'Test Rolling Membership',
      'period_type' => 'fixed',
      'minimum_fee' => 0,
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'fixed_period_start_day' => '1201',
      'fixed_period_rollover_day' => '1130',
    ]);
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = $startDate;
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems = [
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $fixedMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $fixedMembershipType->priceFieldValue['id'],
        'label' => $fixedMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $fixedMembershipType->priceFieldValue['amount'],
        'line_total' => $fixedMembershipType->priceFieldValue['amount'],
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 1,
      ],
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $this->assertEquals(2, count($contributions));

    $memberships = $this->getPaymentPlanAutorenewableMemberships($paymentPlan['id']);
    foreach ($memberships as $membership) {
      $this->assertEquals($startDate, $membership['join_date']);
      $this->assertEquals($startDate, $membership['start_date']);
      $this->assertEquals($endDateAfterRenewal, $membership['end_date']);
    }
  }

  public function testRenewalWillNotCopyContributionFeeAmountFromPreviousTerm() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
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

    // updating the fees for the previous term contribution
    $previousTermContributionId = $this->getPaymentPlanContributions($paymentPlan['id'])[0]['id'];
    civicrm_api3('Contribution', 'create', [
      'id' => $previousTermContributionId,
      'fee_amount' => 10,
    ]);

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $newTermContributionFees = $this->getPaymentPlanContributions($paymentPlan['id'])[1]['fee_amount'];
    $this->assertEquals('0.00', $newTermContributionFees);
  }

  public function testRenewalWillUpdateNextScheduledContributionAmountToOneYearAfterLastContributionDate() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $lastContributionReceiveDate = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $paymentPlan['id'],
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0]['receive_date'];
    $expectedNextDate = date('Y-m-d 00:00:00', strtotime('+1 year', strtotime($lastContributionReceiveDate)));

    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $paymentPlan['id'],
    ]);

    $this->assertEquals($expectedNextDate, $nextDate);
  }

  public function testSecondTermContributionsAfterRenewalAreCreatedFromRecurringContributionDataInsteadOfLastTermContribution() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +1 day'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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

    $singleInstalmentRenewal = new SingleInstalmentRenewalJob();
    $singleInstalmentRenewal->run();

    $secondTermFirstContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['payment_instrument_id', 'financial_type_id'],
      'contribution_recur_id' => $paymentPlan['id'],
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0];

    $this->assertEquals('EFT', $secondTermFirstContribution['payment_instrument']);

    $memberDuesFinancialTypeId = 2;
    $this->assertEquals($memberDuesFinancialTypeId, $secondTermFirstContribution['financial_type_id']);
  }

  /**
   * Obtains list of memberships set to auto-rnew with the payment plan.
   *
   * @param int $paymentPlanID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getPaymentPlanAutorenewableMemberships($paymentPlanID) {
    return civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanID,
    ])['values'];
  }

  /**
   * Returns list of contributions associated to the given payment plan ID.
   * @param int $paymentPlanID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getPaymentPlanContributions($paymentPlanID) {
    return civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanID,
      'options' => [
        'limit' => 0,
        'sort' => 'id',
      ],
    ])['values'];
  }

}
