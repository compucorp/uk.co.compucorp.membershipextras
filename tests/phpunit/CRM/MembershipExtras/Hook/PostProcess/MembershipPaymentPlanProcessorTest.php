<?php

use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrder;
use CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlanProcessor as MembershipPaymentPlanProcessor;

/**
 * Class CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlanProcessorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlanProcessorTest extends BaseHeadlessTest {
  /**
   * @var CRM_Member_Form
   */
  private $form;

  /**
   * @var array
   */
  private $membershipType;

  /**
   * @var mixed
   */
  private $membershipTypePriceFieldValue;

  /**
   * @var array
   */
  private $paymentPlan;

  /**
   * @var CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder
   */
  private $paymentPlanMembershipOrder;

  private static $NEW_MEMBERSHIP_FORM_NAME = 'CRM_Member_Form_Membership';

  public function setUp() {
    $this->setUpMembershipForm();
  }

  public function testProcessFormWithRollingMembershipTypeAndMonthlySchedule() {
    $this->simulateMembershipSignupForm('monthly', 'rolling', date('Y-m-d'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $membershipPayments = $this->getMembershipPayment($this->form->_id);

    $this->assertEquals(12, $membershipPayments['count']);
  }

  public function testProcessFormWithRollingMembershipTypeAndQuarterlySchedule() {
    $this->simulateMembershipSignupForm('quarterly', 'rolling', date('Y-m-d'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $membershipPayments = $this->getMembershipPayment($this->form->_id);

    $this->assertEquals(4, $membershipPayments['count']);
  }

  public function testProcessFormWithFixedPeriodTypeWithMonthlySchedule() {
    $this->simulateMembershipSignupForm('monthly', 'fixed', date('Y-01-15'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $membershipPayments = $this->getMembershipPayment($this->form->_id);
    //Joined date = 15-01-Y
    //Membership Period End day 30-09-Y
    //(30-09-Y) - (15-01-Y) = 9 months
    $expectedCount = 9;
    $this->assertEquals($expectedCount, $membershipPayments['count']);
  }

  /**
   * Tests post process form for one instalment (annual schedule or one month duration unit)
   */
  public function testPostProcessFormForOneInstalment() {
    $this->simulateMembershipSignupForm('annual', 'rolling', date('Y-m-d'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $membershipPayments = $this->getMembershipPayment($this->form->_id);

    $this->assertEquals(1, $membershipPayments['count']);
  }

  public function testMonthlyPaymentPlanNextContributionDateWillBePlusOneMonthFromLastContributionDateForRollingMembership() {
    $this->simulateMembershipSignupForm('monthly', 'rolling', date('2020-01-01'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);

    $lastContributionReceiveDate = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $recurContributionId,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0]['receive_date'];
    $expectedNextDate = date('Y-m-d 00:00:00', strtotime('+1 month', strtotime($lastContributionReceiveDate)));

    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals($expectedNextDate, $nextDate);
  }

  public function testQuarterlyPaymentPlanNextContributionDateWillBePlusThreeMonthsFromLastContributionDateForRollingMembership() {
    $this->simulateMembershipSignupForm('quarterly', 'rolling', date('2020-01-01'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);

    $lastContributionReceiveDate = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $recurContributionId,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0]['receive_date'];
    $expectedNextDate = date('Y-m-d 00:00:00', strtotime('+3 month', strtotime($lastContributionReceiveDate)));

    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals($expectedNextDate, $nextDate);
  }

  public function testYearlyPaymentPlanNextContributionDateWillBePlusOneYearFromLastContributionDateForRollingMemberships() {
    $this->simulateMembershipSignupForm('annual', 'rolling', date('2020-01-01'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);

    $lastContributionReceiveDate = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $recurContributionId,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0]['receive_date'];
    $expectedNextDate = date('Y-m-d 00:00:00', strtotime('+1 year', strtotime($lastContributionReceiveDate)));

    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals($expectedNextDate, $nextDate);
  }

  public function testMonthlyPaymentPlanNextContributionDateWillBeOnSameCycleDayFromMembershipNewStartDateForFixedMembership() {
    $this->simulateMembershipSignupForm('monthly', 'fixed', date('2020-07-03'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $membershipEndDate = civicrm_api3('Membership', 'getvalue', [
      'return' => 'end_date',
      'id' => $this->form->_id,
    ]);

    $expectedNextDate = date('Y-m-d 00:00:00', strtotime('+3 day', strtotime($membershipEndDate)));

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);
    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals($expectedNextDate, $nextDate);
  }

  public function testAnnualPaymentPlanNextContributionDateWillBePlusOneDayFromMembershipNewStartDateForFixedMemberships() {
    $this->simulateMembershipSignupForm('annual', 'fixed', date('2020-07-03'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $membershipEndDate = civicrm_api3('Membership', 'getvalue', [
      'return' => 'end_date',
      'id' => $this->form->_id,
    ]);

    $expectedNextDate = date('Y-m-d 00:00:00', strtotime('+1 day', strtotime($membershipEndDate)));

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);
    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals($expectedNextDate, $nextDate);
  }

  public function testPaymentPlanNextContributionDateDayWillNotExceed28ForMonthlyRollingMemberships() {
    $this->simulateMembershipSignupForm('monthly', 'rolling', date('2020-01-31'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);

    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals(28, date('d', strtotime((string) $nextDate)));
  }

  public function testPaymentPlanNextContributionDateDayWillUseMembershipEndDateForAnnualRollingMemberships() {
    $this->simulateMembershipSignupForm('annual', 'rolling', date('2022-01-31'));

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);
    $this->updateLastContributionReceiveDate($recurContributionId, '2022-01-31');

    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals(31, date('d', strtotime((string) $nextDate)));
  }

  public function testPaymentPlanNextContributionDateDayIsNotLeapDateForAnnualRollingMemberships() {
    $this->simulateMembershipSignupForm('annual', 'rolling', date('2020-02-29'));

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);
    $this->updateLastContributionReceiveDate($recurContributionId, '2020-02-29');

    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $nextDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals(28, date('d', strtotime((string) $nextDate)));
  }

  public function testPaymentPlanCycleDayWillNotExceed28ForRollingMemberships() {
    $this->simulateMembershipSignupForm('monthly', 'rolling', date('2020-01-31'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form, 'creation');
    $processor->postProcess();

    $recurContributionId = civicrm_api3('Membership', 'getvalue', [
      'return' => 'contribution_recur_id',
      'id' => $this->form->_id,
    ]);

    $cycle_day = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'cycle_day',
      'id' => $recurContributionId,
    ]);

    $this->assertEquals(28, $cycle_day);
  }

  /**
   * Sets Membership Form
   */
  private function setUpMembershipForm() {
    $controller = new CRM_Core_Controller();
    $this->form = new CRM_Member_Form_Membership();
    $this->form->controller = $controller;
  }

  /**
   * @param string $schedule
   * @param string $periodType
   * @param string $startDate
   *
   * @throws CiviCRM_API3_Exception
   * @throws ReflectionException
   */
  private function simulateMembershipSignupForm($schedule, $periodType, $startDate) {
    $this->createPaymentPlanMembershipOrder($schedule, $periodType, $startDate);

    $this->setFormMembershipIDs();
    $this->form->setVar('_submitValues', [
      'record_contribution' => 1,
      'membership_type_id' => [$this->membershipTypePriceFieldValue['id'], $this->membershipType['id']],
      'financial_type_id' => $this->paymentPlanMembershipOrder->financialType,
      'payment_instrument_id' => $this->paymentPlanMembershipOrder->paymentMethod,
      'contribution_status_id' => $this->paymentPlanMembershipOrder->paymentPlanStatus,
      'payment_plan_schedule' => $schedule,
      'join_date' => date('Y-m-d'),
      'start_date' => $startDate,
    ]);
    $this->form->buildForm();
  }

  /**
   * Fabricates payment plan membership order
   *
   * @param string $schedule
   * @param string $periodType
   * @param string $startDate
   * @throws CiviCRM_API3_Exception
   */
  private function createPaymentPlanMembershipOrder($schedule, $periodType, $startDate) {
    $this->membershipType = CRM_MembershipExtras_Test_Fabricator_MembershipType::fabricate([
      'name' => 'Test Membership',
      'period_type' => $periodType,
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => 'year',
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
    ]);

    $this->membershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $this->paymentPlanMembershipOrder = new CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder();
    $this->paymentPlanMembershipOrder->autoRenew = 0;
    $this->paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    $this->paymentPlanMembershipOrder->paymentPlanFrequency = $this->getPaymentPlanFrequency($schedule);
    $this->paymentPlanMembershipOrder->paymentPlanStartDate = $startDate;
    $this->paymentPlanMembershipOrder->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->membershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->membershipTypePriceFieldValue['id'],
      'label' => $this->membershipType['name'],
      'qty' => 1,
      'unit_price' => $this->membershipTypePriceFieldValue['amount'],
      'line_total' => $this->membershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 0,
    ];

    $this->paymentPlan = PaymentPlanOrder::fabricate($this->paymentPlanMembershipOrder, FALSE);
  }

  private function getPaymentPlanFrequency($schedule) {
    if ($schedule == 'monthly') {
      return 'Monthly';
    }

    if ($schedule == 'quarterly') {
      return 'Quarterly';
    }

    if ($schedule == 'annual') {
      return 'Yearly';
    }

    return 'Yearly';
  }

  /**
   * @throws CiviCRM_API3_Exception
   * @throws ReflectionException
   */
  private function setFormMembershipIDs() {
    $memberships = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $this->paymentPlan['id'],
      'api.Membership.get' => [],
    ])['values'][0]['api.Membership.get']['values'];

    $this->form->_id = $memberships[0]['id'];
  }

  private function getMembershipPayment($membershipId) {
    return civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'membership_id' => $membershipId,
    ]);
  }

  private function updateLastContributionReceiveDate($recurContributionId, $date) {
    $lastContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurContributionId,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ]);

    civicrm_api3('Contribution', 'create', [
      'sequential' => 1,
      'id' => $lastContribution['id'],
      'receive_date' => $date,
    ]);
  }

}
