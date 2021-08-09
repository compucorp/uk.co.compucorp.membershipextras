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
    $this->createPaymentPlanMembershipOrder('rolling');
    $this->simulateMembershipSignupForm('monthly', date('Y-m-d'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form);
    $processor->postProcess();

    $membershipPayments = $this->getMembershipPayment($this->form->_id);

    $this->assertEquals(12, $membershipPayments['count']);
  }

  public function testProcessFormWithRollingMembershipTypeAndQuarterlySchedule() {
    $this->createPaymentPlanMembershipOrder('rolling');
    $this->simulateMembershipSignupForm('quarterly', date('Y-m-d'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form);
    $processor->postProcess();

    $membershipPayments = $this->getMembershipPayment($this->form->_id);

    $this->assertEquals(4, $membershipPayments['count']);
  }

  public function testProcessFormWithFixedPeriodTypeWithMonthlySchedule() {
    $this->createPaymentPlanMembershipOrder('fixed');
    $this->simulateMembershipSignupForm('monthly', date('Y-01-15'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form);
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
    $this->createPaymentPlanMembershipOrder('rolling');
    $this->simulateMembershipSignupForm('annual', date('Y-m-d'));
    $processor = new MembershipPaymentPlanProcessor(self::$NEW_MEMBERSHIP_FORM_NAME, $this->form);
    $processor->postProcess();

    $membershipPayments = $this->getMembershipPayment($this->form->_id);

    $this->assertEquals(1, $membershipPayments['count']);
  }

  /**
   * Fabricates payment plan membership order
   *
   * @param $periodType
   * @throws CiviCRM_API3_Exception
   */
  private function createPaymentPlanMembershipOrder($periodType) {
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

    $this->paymentPlan = PaymentPlanOrder::fabricate($this->paymentPlanMembershipOrder);
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
   *
   * @param $schedule
   * @param $startDate
   *
   * @throws CiviCRM_API3_Exception
   * @throws ReflectionException
   */
  private function simulateMembershipSignupForm($schedule, $startDate) {
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

}
