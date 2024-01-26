<?php

use CRM_MembershipExtras_Service_MembershipTypeSwitcher as MembershipTypeSwitcher;
use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeSwitcherTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipTypeSwitcherTest extends BaseHeadlessTest {

  private $testGoldRollingMembershipType;
  private $testGoldRollingMembershipTypePriceFieldValue;
  private $testSilverRollingMembershipType;
  private $testSilverRollingMembershipTypePriceFieldValue;

  private $defaultOneOffFeeParams;

  public function setUp(): void {
    $this->createGoldRollingMembershipType();
    $this->createSilverRollingMembershipType();
    $this->defaultOneOffFeeParams = [
      'scheduled_charge_date' => '2022-08-01',
      'amount_exc_tax' => 100,
      'amount_inc_tax' => 100,
      'financial_type_id' => 2,
      'send_confirmation_email' => 0,
    ];
  }

  private function createGoldRollingMembershipType() {
    $this->testGoldRollingMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Gold Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 120,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);

    $this->testGoldRollingMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->testGoldRollingMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];
  }

  private function createSilverRollingMembershipType() {
    $this->testSilverRollingMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Silver Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 100,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);

    $this->testSilverRollingMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->testSilverRollingMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];
  }

  public function testSwitchWithUpdatePendingInstalmentsPaymentTypeWillSetCurrentMembershipEndDateToTheSwitchDate() {
    $switchDate = '2022-07-01';
    $paymentPlan = $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS);

    $currentMembership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contact_id' => $paymentPlan['contact_id'],
    ])['values'][0];

    $this->assertEquals($switchDate, $currentMembership['end_date']);
  }

  public function testSwitchWithOneOffFeeWillSetCurrentMembershipEndDateToTheSwitchDate() {
    $switchDate = '2022-07-01';
    $paymentPlan = $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_ONE_OFF_PAYMENT, $this->defaultOneOffFeeParams);

    $currentMembership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contact_id' => $paymentPlan['contact_id'],
    ])['values'][0];

    $this->assertEquals($switchDate, $currentMembership['end_date']);
  }

  public function testSwitchWithUpdatePendingInstalmentsPaymentTypeWillMarkTheSubscriptionLineItemAsRemovedAndNonRenewable() {
    $switchDate = '2022-07-01';
    $paymentPlan = $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS);

    $currentMembershipSubscriptionLineItem = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ])['values'][0];

    $this->assertEquals(0, $currentMembershipSubscriptionLineItem['auto_renew']);
    $this->assertEquals(1, $currentMembershipSubscriptionLineItem['is_removed']);
  }

  public function testSwitchWithOneOffFeeWillMarkTheSubscriptionLineItemAsRemovedAndNonRenewable() {
    $switchDate = '2022-07-01';
    $paymentPlan = $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_ONE_OFF_PAYMENT, $this->defaultOneOffFeeParams);

    $currentMembershipSubscriptionLineItem = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ])['values'][0];

    $this->assertEquals(0, $currentMembershipSubscriptionLineItem['auto_renew']);
    $this->assertEquals(1, $currentMembershipSubscriptionLineItem['is_removed']);
  }

  public function testSwitchWithUpdatePendingInstalmentsPaymentTypeWillCreateNewMembershipWithStartEqualsSwitchDateAndEndDateEqualsPeriodEndDate() {
    $switchDate = '2022-07-01';
    $paymentPlan = $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS);

    $newMembership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contact_id' => $paymentPlan['contact_id'],
    ])['values'][1];

    $this->assertEquals($this->testSilverRollingMembershipType['id'], $newMembership['membership_type_id']);
    $this->assertEquals('2022-07-01', $newMembership['start_date']);
    $this->assertEquals('2022-12-31', $newMembership['end_date']);
  }

  public function testSwitchWithOneOffFeeWillCreateNewMembershipWithStartEqualsSwitchDateAndEndDateEqualsPeriodEndDate() {
    $switchDate = '2022-07-01';
    $paymentPlan = $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_ONE_OFF_PAYMENT, $this->defaultOneOffFeeParams);

    $newMembership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contact_id' => $paymentPlan['contact_id'],
    ])['values'][1];

    $this->assertEquals($this->testSilverRollingMembershipType['id'], $newMembership['membership_type_id']);
    $this->assertEquals('2022-07-01', $newMembership['start_date']);
    $this->assertEquals('2022-12-31', $newMembership['end_date']);
  }

  public function testSwitchWithUpdatePendingInstalmentsPaymentTypeWillAdjustTheContributionsStartingFromTheSwitchDate() {
    $switchDate = '2022-07-01';
    $paymentPlan = $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS);

    $contributions = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
      'options' => ['limit' => 0],
    ])['values'];

    $contributionsNumber = 1;
    foreach ($contributions as $contribution) {
      if ($contributionsNumber <= 6) {
        $this->assertNotEquals(8.40, $contribution['total_amount']);
      }
      else {
        $this->assertEquals(8.40, $contribution['total_amount']);
      }

      $contributionsNumber++;
    }
  }

  public function testSwitchWithUpdatePendingInstalmentsPaymentTypeWithoutHavingFuturePendingInstalmentsWillThrowAnError() {
    $this->expectException(CRM_Core_Exception::class);

    $switchDate = '2023-01-01';
    $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS);
  }

  public function testSwitchWithOneOffFeeWillCreateNewExtraContribution() {
    $switchDate = '2022-07-01';
    $paymentPlan = $this->createPaymentPlanAndSwitchType($switchDate, MembershipTypeSwitcher::PAYMENT_TYPE_ONE_OFF_PAYMENT, $this->defaultOneOffFeeParams);

    $oneOffContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
      'options' => ['limit' => 0],
    ])['values'][12];

    $this->assertEquals($this->defaultOneOffFeeParams['amount_inc_tax'], $oneOffContribution['total_amount']);
    $this->assertEquals($this->defaultOneOffFeeParams['scheduled_charge_date'] . ' 00:00:00', $oneOffContribution['receive_date']);
  }

  private function createPaymentPlanAndSwitchType($switchDate, $paymentType, $oneOffFeeParams = NULL) {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = '2022-01-01';
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->testGoldRollingMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->testGoldRollingMembershipTypePriceFieldValue['id'],
      'label' => $this->testGoldRollingMembershipType['name'],
      'qty' => 1,
      'unit_price' => $this->testGoldRollingMembershipTypePriceFieldValue['amount'],
      'line_total' => $this->testGoldRollingMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $recurLineItem = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ])['values'][0];
    $subscriptionLinkedLineItemId = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'id' => $recurLineItem['line_item_id'],
    ])['values'][0]['id'];

    $membershipTypeSwitcher = new MembershipTypeSwitcher($subscriptionLinkedLineItemId, $this->testSilverRollingMembershipType['id'], $switchDate, $paymentType, $oneOffFeeParams);
    $membershipTypeSwitcher->switchType();

    return $paymentPlan;
  }

}
