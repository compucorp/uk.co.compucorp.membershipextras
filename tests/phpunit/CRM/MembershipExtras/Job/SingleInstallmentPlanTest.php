<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
    $this->assertEquals($paymentPlanMembershipOrder->membershipStartDate ,$membership['start_date']);
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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
    $this->assertEquals($expectedNewMembershipStartDate ,$membership['start_date']);
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
      'unit_price' =>  $this->testRollingMembershipTypePriceFieldValue['amount'],
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

}
