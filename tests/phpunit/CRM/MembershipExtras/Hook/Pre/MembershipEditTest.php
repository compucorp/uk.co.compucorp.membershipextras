<?php

use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contribution as ContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Hook_Pre_MembershipEdit as MembershipEditHook;

/**
 * Class CRM_MembershipExtras_Hook_Pre_MembershipEditTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_Pre_MembershipEditTest extends BaseHeadlessTest {

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
   * Creates a payment plan with the given mebership type.
   *
   * @param $membershipType
   * @param string $frequency
   *
   * @return array
   */
  private function createPaymentPlan($membershipType, $frequency = 'Monthly') {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = $frequency;
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $membershipType->priceFieldValue['price_field_id'],
      'price_field_value_id' => $membershipType->priceFieldValue['id'],
      'label' => $membershipType->membershipType['name'],
      'qty' => 1,
      'unit_price' => $membershipType->priceFieldValue['amount'],
      'line_total' => $membershipType->priceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 1,
    ];

    return PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
  }

  /**
   * Creates a payment plan with Pending contributions for integration testing.
   */
  private function createPendingPaymentPlan($membershipType) {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    $paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $membershipType->priceFieldValue['price_field_id'],
      'price_field_value_id' => $membershipType->priceFieldValue['id'],
      'label' => $membershipType->membershipType['name'],
      'qty' => 1,
      'unit_price' => $membershipType->priceFieldValue['amount'],
      'line_total' => $membershipType->priceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 1,
    ];

    return PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
  }

  /**
   * Obtains memberships set to auto-renew for payment plan.
   *
   * @param int $paymentPlanID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getPaymentPlanRenewableMemberships($paymentPlanID) {
    return civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanID,
    ])['values'];
  }

  /**
   * Returns list of contributions associated to given recurring contribution.
   *
   * @param int $paymentPlanID
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  private function getPaymentPlanContributions($paymentPlanID) {
    return civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanID,
      'options' => ['limit' => 0],
    ])['values'];
  }

  public function testPreventExtendingPaymentPlanMembershipAfterContributionEdit() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipParams['end_date'] = date('Y-m-d');
    $paymentType = 'owed';

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, $contributions[0]['id'], $paymentType);
    $hook->preProcess();

    $this->assertFalse(array_key_exists('end_date', $membershipParams));
  }

  public function testPreventExtendingPaymentPlanMembershipOnRecordingPayment() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipParams['end_date'] = date('Y-m-d');
    $paymentType = 'owed';

    $tmpGlobals = [];
    $tmpGlobals['_REQUEST']['entryURL'] = '?action=add&id=' . $contributions[0]['id'];
    CRM_Utils_GlobalStack::singleton()->push($tmpGlobals);

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, NULL, $paymentType);
    $hook->preProcess();

    $this->assertFalse(array_key_exists('end_date', $membershipParams));
    CRM_Utils_GlobalStack::singleton()->pop();
  }

  public function testPreventExtendingPaymentPlanMembershipOnBulkStatusUpdate() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipParams['end_date'] = date('Y-m-d');
    $paymentType = 'owed';

    $tmpGlobals = [];
    $tmpGlobals['_GET']['q'] = 'civicrm/contribute/search';
    $tmpGlobals['_REQUEST']['_qf_Status_next'] = 'Update Pending Status';
    $tmpGlobals['_REQUEST']['contribution_status_id'] = '1';
    CRM_Utils_GlobalStack::singleton()->push($tmpGlobals);

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, NULL, $paymentType);
    $hook->preProcess();

    $this->assertFalse(array_key_exists('end_date', $membershipParams));
    CRM_Utils_GlobalStack::singleton()->pop();
  }

  public function testPreventExtendingPaymentPlanMembershipWhenCallingPaymentCreateAPI() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $firstContributionId = $contributions[0]['id'];
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipParams['end_date'] = date('Y-m-d');

    civicrm_api3('Payment', 'create', [
      'contribution_id' => $firstContributionId,
      'total_amount' => 60,
      'trxn_date' => date('Y-m-d'),
    ]);

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, NULL, NULL);
    $hook->preProcess();

    $this->assertFalse(array_key_exists('end_date', $membershipParams));
  }

  public function testMembershipNotInPaymentPlanIsNotPreventedFromExtending() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);
    $contact = ContactFabricator::fabricate();
    $membership = MembershipFabricator::fabricate([
      'contact_id' => $contact['id'],
      'membership_type_id' => $mainMembershipType->membershipType['id'],
      'join_date' => date('Y-m-d'),
      'start_date' => date('Y-m-d'),
      'financial_type_id' => 'Member Dues',
      'skipLineItem' => 0,
    ]);
    $payment = ContributionFabricator::fabricate([
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => date('Y-m-d'),
      'contact_id' => $contact['id'],
      'fee_amount' => 0,
      'net_amount' => "{$mainMembershipType->priceFieldValue['amount']}",
      'total_amount' => "{$mainMembershipType->priceFieldValue['amount']}",
      'payment_instrument_id' => 'EFT',
      'financial_type_id' => 'Member Dues',
    ]);
    LineItemFabricator::fabricate([
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membership['id'],
      'contribution_id' => $payment['id'],
      'price_field_id' => $mainMembershipType->priceFieldValue['price_field_id'],
      'price_field_value_id' => $mainMembershipType->priceFieldValue['id'],
      'label' => $mainMembershipType->membershipType['name'],
      'qty' => 1,
      'unit_price' => $mainMembershipType->priceFieldValue['amount'],
      'line_total' => $mainMembershipType->priceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 0,
    ]);

    $newEndDate = date('Y-m-d', strtotime($membership['end_date'] . '+2 years'));
    $membership['end_date'] = $newEndDate;
    $paymentType = 'owed';
    $hook = new MembershipEditHook($membership['id'], $membership, $payment['id'], $paymentType);
    $hook->preProcess();

    $this->assertEquals($newEndDate, $membership['end_date']);
  }

  public function testExtendPendingPaymentPlanMembershipOnRenewal() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipEndDate = $membershipParams['end_date'];
    $paymentType = 'owed';

    $tmpGlobals = [];
    $tmpGlobals['_REQUEST']['action'] = CRM_Core_Action::RENEW;
    $tmpGlobals['_REQUEST']['contribution_status_id'] = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
    $tmpGlobals['_REQUEST']['installments'] = $paymentPlan['installments'];
    $tmpGlobals['_REQUEST']['record_contribution'] = 1;
    $tmpGlobals['_REQUEST']['contribution_type_toggle'] = 'payment_plan';
    CRM_Utils_GlobalStack::singleton()->push($tmpGlobals);

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, $contributions[0]['id'], $paymentType);
    $hook->preProcess();

    $this->assertEquals(
      date('Y-m-d', strtotime($membershipEndDate . ' +12 months')),
      date('Y-m-d', strtotime($membershipParams['end_date']))
    );
    CRM_Utils_GlobalStack::singleton()->pop();
  }

  public function testEndDateNotUnsetWhenContributionIdIsNull() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, NULL, '');
    $hook->preProcess();

    $this->assertArrayHasKey('end_date', $membershipParams, 'end_date should not be unset when contributionID is NULL');
  }

  public function testEndDateUnsetWhenContributionIdIsSet() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipParams['end_date'] = date('Y-m-d', strtotime('+1 year'));

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, $contributions[0]['id'], '');
    $hook->preProcess();

    $this->assertArrayNotHasKey('end_date', $membershipParams, 'end_date should be unset when contributionID is set for a payment plan membership');
  }

  /**
   * Integration test: simulates the real webhook scenario with two
   * completeTransaction calls in the same PHP request.
   * The resetContributionID flag ensures each call starts with a
   * clean $contributionID, preventing stale state from interfering.
   */
  public function testSecondCompleteTransactionDoesNotResetMembershipEndDate() {
    $membershipTypeA = $this->createMembershipType([
      'name' => 'Membership Type A',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);
    $membershipTypeB = $this->createMembershipType([
      'name' => 'Membership Type B',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlanA = $this->createPendingPaymentPlan($membershipTypeA);
    $paymentPlanB = $this->createPendingPaymentPlan($membershipTypeB);

    $contributionsA = $this->getPaymentPlanContributions($paymentPlanA['id']);
    $contributionsB = $this->getPaymentPlanContributions($paymentPlanB['id']);

    // Payment 1: complete contribution A (simulates first event in webhook)
    civicrm_api3('Contribution', 'completetransaction', [
      'id' => $contributionsA[0]['id'],
      'trxn_id' => 'GC_TEST_PM001',
      'is_transactional' => FALSE,
    ]);

    // Payment 2: complete contribution B (simulates second event in same webhook)
    civicrm_api3('Contribution', 'completetransaction', [
      'id' => $contributionsB[0]['id'],
      'trxn_id' => 'GC_TEST_PM002',
      'is_transactional' => FALSE,
    ]);

    // Refresh both memberships from DB
    $membershipAAfter = civicrm_api3('Membership', 'getsingle', [
      'contribution_recur_id' => $paymentPlanA['id'],
    ]);
    $membershipBAfter = civicrm_api3('Membership', 'getsingle', [
      'contribution_recur_id' => $paymentPlanB['id'],
    ]);

    // Both memberships should have valid end_dates — neither should be
    // nulled or corrupted by stale static $contributionID state.
    $this->assertNotEmpty(
      $membershipAAfter['end_date'],
      'First completeTransaction should not null membership A end_date'
    );
    $this->assertNotEmpty(
      $membershipBAfter['end_date'],
      'Second completeTransaction in same request should not null membership B end_date'
    );
  }

  public function testExtendPendingPlanWithFixedMembershipAndOneInstalment() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Test Fixed Membership',
      'period_type' => 'fixed',
      'minimum_fee' => 0,
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'fixed_period_start_day' => '1201',
      'fixed_period_rollover_day' => '1130',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType, 'Yearly');
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipEndDate = $membershipParams['end_date'];
    $paymentType = 'owed';

    $tmpGlobals['_REQUEST']['action'] = CRM_Core_Action::RENEW;
    $tmpGlobals['_REQUEST']['contribution_status_id'] = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
    $tmpGlobals['_REQUEST']['installments'] = $paymentPlan['installments'];
    $tmpGlobals['_REQUEST']['record_contribution'] = 1;
    $tmpGlobals['_REQUEST']['contribution_type_toggle'] = 'payment_plan';
    CRM_Utils_GlobalStack::singleton()->push($tmpGlobals);

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, $contributions[0]['id'], $paymentType);
    $hook->preProcess();

    $this->assertEquals(
      date('Y-m-d', strtotime($membershipEndDate . ' +12 months')),
      date('Y-m-d', strtotime($membershipParams['end_date']))
    );
    CRM_Utils_GlobalStack::singleton()->pop();
  }

  public function testPreventCreatingRenewalActivityForPaymentPlanMembershipOnRecordingPayment() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipParams['end_date'] = date('Y-m-d');
    $paymentType = 'owed';

    $tmpGlobals = [];
    $tmpGlobals['_REQUEST']['q'] = 'civicrm/contribute/search';
    $tmpGlobals['_REQUEST']['_qf_Status_next'] = 'Update Pending Status';
    $tmpGlobals['_REQUEST']['contribution_status_id'] = '1';
    CRM_Utils_GlobalStack::singleton()->push($tmpGlobals);

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, NULL, $paymentType);
    $hook->preProcess();

    $this->assertTrue(!isset($membership['membership_activity_status']));
    CRM_Utils_GlobalStack::singleton()->pop();
  }

  public function testCreateRenewalActivityPaymentPlanMembershipOnRenewal() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $membershipParams['membership_activity_status'] = 'Completed';
    $paymentType = 'owed';

    $tmpGlobals = [];
    $tmpGlobals['_REQUEST']['action'] = CRM_Core_Action::RENEW;
    $tmpGlobals['_REQUEST']['contribution_status_id'] = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
    $tmpGlobals['_REQUEST']['installments'] = $paymentPlan['installments'];
    $tmpGlobals['_REQUEST']['record_contribution'] = 1;
    $tmpGlobals['_REQUEST']['contribution_type_toggle'] = 'payment_plan';
    CRM_Utils_GlobalStack::singleton()->push($tmpGlobals);

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, $contributions[0]['id'], $paymentType);
    $hook->preProcess();

    $this->assertTrue(isset($membershipParams['membership_activity_status']));
    CRM_Utils_GlobalStack::singleton()->pop();
  }

  public function testVerifyMembershipStartDate() {
    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);

    $paymentPlan = $this->createPaymentPlan($mainMembershipType);
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $memberships = $this->getPaymentPlanRenewableMemberships($paymentPlan['id']);
    $membershipParams = array_shift($memberships);
    $originalStartDate = $membershipParams['start_date'];
    $membershipParams['start_date'] = date('Y-m-d', strtotime('+1000 years'));
    $paymentType = 'owed';

    $hook = new MembershipEditHook($membershipParams['id'], $membershipParams, $contributions[0]['id'], $paymentType);
    $hook->preProcess();

    $this->assertEquals($originalStartDate, $membershipParams['start_date']);
  }

  /**
   * Paying instalments of a plan whose term the membership already has
   * (a signup, or a plan created by the renewal job) through
   * Contribution.completetransaction, as Stripe and Pay Online invoices do,
   * doesn't add a term (CIVIMM-592, MAE-1404, MAE-1405).
   */
  public function testCompleteTransactionOnPlanWithTermAlreadyGivenKeepsEndDate() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $endDateBefore = $this->getPlanMembershipEndDate($paymentPlan['id']);

    $this->completeTransaction($contributions[0]['id']);
    $this->assertEquals($endDateBefore, $this->getPlanMembershipEndDate($paymentPlan['id']), 'First instalment');

    $this->completeTransaction($contributions[1]['id']);
    $this->assertEquals($endDateBefore, $this->getPlanMembershipEndDate($paymentPlan['id']), 'Later instalment');
  }

  /**
   * The same through Payment.create, as GoCardless and Record Payment do:
   * unchanged behaviour.
   */
  public function testPaymentCreateOnPlanWithTermAlreadyGivenKeepsEndDate() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $endDateBefore = $this->getPlanMembershipEndDate($paymentPlan['id']);

    $this->recordPayment($contributions[0]);
    $this->recordPayment($contributions[1]);

    $this->assertEquals($endDateBefore, $this->getPlanMembershipEndDate($paymentPlan['id']));
  }

  /**
   * A plan set up to renew a membership that hasn't been extended yet (e.g.
   * an existing member renewing on a webform, paid later by GoCardless):
   * its first payment extends the membership once, later instalments don't
   * (IESSPRT-293).
   */
  public function testPaymentCreateOnRenewalPlanExtendsMembershipOnce() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $lapsedEndDate = $this->lapsePlanMembership($paymentPlan['id']);

    $this->recordPayment($contributions[0]);
    $this->assertEquals($this->getEndDateOneTermAfter($lapsedEndDate), $this->getPlanMembershipEndDate($paymentPlan['id']), 'First payment renews the membership');

    $this->recordPayment($contributions[1]);
    $this->assertEquals($this->getEndDateOneTermAfter($lapsedEndDate), $this->getPlanMembershipEndDate($paymentPlan['id']), 'Later instalment');
  }

  /**
   * As above, through Contribution.completetransaction.
   */
  public function testCompleteTransactionOnRenewalPlanExtendsMembershipOnce() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $lapsedEndDate = $this->lapsePlanMembership($paymentPlan['id']);

    $this->completeTransaction($contributions[0]['id']);
    $this->assertEquals($this->getEndDateOneTermAfter($lapsedEndDate), $this->getPlanMembershipEndDate($paymentPlan['id']), 'First payment renews the membership');

    $this->completeTransaction($contributions[1]['id']);
    $this->assertEquals($this->getEndDateOneTermAfter($lapsedEndDate), $this->getPlanMembershipEndDate($paymentPlan['id']), 'Later instalment');
  }

  /**
   * A current member who renews early with a new plan, before the membership
   * ends: the first payment extends it by one term from its end date.
   */
  public function testPaymentCreateOnEarlyRenewalPlanExtendsMembershipOnce() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $currentEndDate = date('Y-m-d', strtotime('+20 days'));
    $this->setPlanMembershipEndDate($paymentPlan['id'], $currentEndDate, '-1 year');

    $this->recordPayment($contributions[0]);

    $this->assertEquals($this->getEndDateOneTermAfter($currentEndDate), $this->getPlanMembershipEndDate($paymentPlan['id']));
  }

  /**
   * A membership renewed with a payment plan on the back-office renewal form
   * is extended there, when the plan is created. Paying the plan's first
   * instalment later doesn't extend it again, even though the plan starts
   * after the old end date, so the new end date is before one term from the
   * plan's start.
   */
  public function testPaymentCreateOnPlanRenewedInBackOfficeKeepsEndDate() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_contribution_recur SET start_date = %1 WHERE id = %2',
      [1 => [date('Y-m-d'), 'String'], 2 => [$paymentPlan['id'], 'Integer']]
    );
    $this->setPlanMembershipEndDate($paymentPlan['id'], date('Y-m-d', strtotime('-14 days')), '-1 year');
    $renewedEndDate = date('Y-m-d', strtotime('+1 year -14 days'));
    $this->setPlanMembershipEndDate($paymentPlan['id'], $renewedEndDate, 'now');

    $this->recordPayment($contributions[0]);

    $this->assertEquals($renewedEndDate, $this->getPlanMembershipEndDate($paymentPlan['id']));
  }

  /**
   * Several payments recorded in one PHP process, as a GoCardless webhook
   * queue run does: an earlier payment's contribution, not on any plan, must
   * not decide whether a plan payment adds a term.
   */
  public function testPaymentCreateAfterAnotherContributionKeepsEndDate() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $endDateBefore = $this->getPlanMembershipEndDate($paymentPlan['id']);
    $this->editContributionNotOnAPlan();

    $this->recordPayment($contributions[0]);

    $this->assertEquals($endDateBefore, $this->getPlanMembershipEndDate($paymentPlan['id']));
  }

  /**
   * As above, through Contribution.completetransaction.
   */
  public function testCompleteTransactionAfterAnotherContributionKeepsEndDate() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);
    $endDateBefore = $this->getPlanMembershipEndDate($paymentPlan['id']);
    $this->editContributionNotOnAPlan();

    $this->completeTransaction($contributions[0]['id']);

    $this->assertEquals($endDateBefore, $this->getPlanMembershipEndDate($paymentPlan['id']));
  }

  /**
   * The order-completion mark never outlives its order.
   */
  public function testOrderCompletionMarkIsClearedAfterCompleteTransaction() {
    $paymentPlan = $this->createPendingPaymentPlan($this->createOneYearRollingMembershipType());
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);

    $this->completeTransaction($contributions[0]['id']);
    $this->assertNull(CRM_MembershipExtras_Hook_Config_OrderCompletion::getCompletingContributionID());

    $this->recordPayment($contributions[1]);
    $this->assertNull(CRM_MembershipExtras_Hook_Config_OrderCompletion::getCompletingContributionID(), 'After Payment.create');
  }

  /**
   * Edits a contribution that isn't on any payment plan, as recording an
   * earlier payment (e.g. an event fee) does in the same PHP process.
   */
  private function editContributionNotOnAPlan() {
    $contactID = civicrm_api3('Contact', 'create', [
      'contact_type' => 'Individual',
      'first_name' => 'Earlier',
      'last_name' => 'Payer',
    ])['id'];
    $contribution = civicrm_api3('Contribution', 'create', [
      'contact_id' => $contactID,
      'financial_type_id' => 'Donation',
      'total_amount' => 50,
      'contribution_status_id' => 'Pending',
      'is_pay_later' => 1,
    ]);
    civicrm_api3('Contribution', 'create', [
      'id' => $contribution['id'],
      'source' => 'Edited before the plan payment',
    ]);
  }

  /**
   * @return \stdClass
   */
  private function createOneYearRollingMembershipType() {
    return $this->createMembershipType([
      'name' => 'One Year Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);
  }

  /**
   * @param int $paymentPlanID
   *
   * @return string
   */
  private function getPlanMembershipEndDate($paymentPlanID) {
    return civicrm_api3('Membership', 'getvalue', [
      'contribution_recur_id' => $paymentPlanID,
      'return' => 'end_date',
    ]);
  }

  /**
   * Ends the plan's membership yesterday, an end date set when it was last
   * renewed a year ago, as for a lapsed member who sets up a plan to renew.
   *
   * @param int $paymentPlanID
   *
   * @return string
   *   The new end date.
   */
  private function lapsePlanMembership($paymentPlanID) {
    $endDate = date('Y-m-d', strtotime('-1 day'));
    $this->setPlanMembershipEndDate($paymentPlanID, $endDate, '-1 year');

    return $endDate;
  }

  /**
   * Sets the plan's membership end date and logs it in core's membership log
   * as set at the given time.
   *
   * @param int $paymentPlanID
   * @param string $endDate
   * @param string $loggedAt
   *   When the end date was set, e.g. '-1 year'.
   */
  private function setPlanMembershipEndDate($paymentPlanID, $endDate, $loggedAt) {
    CRM_Core_DAO::executeQuery(
      'UPDATE civicrm_membership SET end_date = %1 WHERE contribution_recur_id = %2',
      [1 => [$endDate, 'String'], 2 => [$paymentPlanID, 'Integer']]
    );
    CRM_Core_DAO::executeQuery('
      INSERT INTO civicrm_membership_log (membership_id, status_id, start_date, end_date, modified_id, modified_date, membership_type_id)
      SELECT id, status_id, start_date, end_date, contact_id, %1, membership_type_id
      FROM civicrm_membership
      WHERE contribution_recur_id = %2
    ', [
      1 => [date('YmdHis', strtotime($loggedAt)), 'String'],
      2 => [$paymentPlanID, 'Integer'],
    ]);
  }

  /**
   * Gets the end date core gives a one-year rolling membership renewed from
   * the given end date.
   *
   * @param string $endDate
   *
   * @return string
   */
  private function getEndDateOneTermAfter($endDate) {
    return date('Y-m-d', strtotime('-1 day', strtotime('+1 year', strtotime('+1 day', strtotime($endDate)))));
  }

  /**
   * @param int $contributionID
   */
  private function completeTransaction($contributionID) {
    civicrm_api3('Contribution', 'completetransaction', [
      'id' => $contributionID,
      'trxn_id' => 'TEST_' . $contributionID,
      'is_transactional' => FALSE,
    ]);
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

}
