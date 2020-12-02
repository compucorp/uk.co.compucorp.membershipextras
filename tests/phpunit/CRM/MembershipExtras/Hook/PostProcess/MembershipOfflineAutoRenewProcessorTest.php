<?php


/**
 * Class CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessorTest extends BaseHeadlessTest {

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

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function testPostProcessMembershipForm() {
    $this->createPaymentPlanMembershipOrder();
    $this->setUpMembershipForm();
    $this->simulateMembershipSignupForm();

    $this->assertEquals(0, $this->paymentPlan['auto_renew']);

    $contributionRecurLineItem = civicrm_api3('ContributionRecurLineItem', 'getsingle', [
      'contribution_recur_id' => $this->paymentPlan['id'],
    ]);

    $this->assertEquals(0, $contributionRecurLineItem['auto_renew']);

    $membershipOfflineAutoRenewProcessor =
      new CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessor($this->form);
    $membershipOfflineAutoRenewProcessor->postProcess();

    $processedPaymentPlan = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->paymentPlan['id'],
    ]);

    $this->assertEquals(1, $processedPaymentPlan['auto_renew']);

    $contributionRecurLineItem = civicrm_api3('ContributionRecurLineItem', 'getsingle', [
      'contribution_recur_id' => $this->paymentPlan['id'],
    ]);

    $this->assertEquals(1, $contributionRecurLineItem['auto_renew']);
  }

  /**
   * Fabricates payment plan membership order
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function createPaymentPlanMembershipOrder() {
    $this->membershipType = CRM_MembershipExtras_Test_Fabricator_MembershipType::fabricate([
      'name' => 'Test Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => 'year',
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

    $this->paymentPlan =
      CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder::fabricate($this->paymentPlanMembershipOrder);
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
   * @throws CiviCRM_API3_Exception
   * @throws ReflectionException
   */
  private function simulateMembershipSignupForm() {
    $this->setFormMembershipIDs();
    $this->form->setVar('_submitValues', [
      'record_contribution' => 1,
      'membership_type_id' => [$this->membershipType['id'], $this->membershipTypePriceFieldValue['id']],
      'financial_type_id' => $this->paymentPlanMembershipOrder->financialType,
      'payment_instrument_id' => $this->paymentPlanMembershipOrder->paymentMethod,
      'contribution_status_id' => $this->paymentPlanMembershipOrder->paymentPlanStatus,
      'payment_plan_schedule' => 'monthly',
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

    $membershipIDs = [];
    foreach ($memberships as $membership) {
      array_push($membershipIDs, $membership['id']);
    }
    $formReflection = new ReflectionObject($this->form);
    $propertyReflection = $formReflection->getProperty('_membershipIDs');
    $propertyReflection->setAccessible(TRUE);
    $propertyReflection->setValue(new stdClass(), $membershipIDs);
  }

}
