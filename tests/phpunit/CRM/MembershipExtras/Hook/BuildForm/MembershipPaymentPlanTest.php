<?php

/**
 * Class CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlanTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlanTest extends BaseHeadlessTest {

  private $membershipCreationForm;

  public function setUp() {
    $formController = new CRM_Core_Controller();
    $this->membershipCreationForm = new CRM_Member_Form_Membership();
    $this->membershipCreationForm->controller = $formController;
  }

  public function testPaymentPlanTogglerGetsAddedToPayLaterMembershipCreationForm() {
    $this->membershipCreationForm->_mode = NULL;

    $mebershipBuildFormHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlan($this->membershipCreationForm);
    $mebershipBuildFormHook->buildForm();

    $installmentsField = $this->membershipCreationForm->getElement('installments');
    $this->assertTrue(is_object($installmentsField));
  }

  public function testPaymentPlanTogglerIsNotAddedToCreditCardLiveMembershipCreationForm() {
    $this->membershipCreationForm->_mode = 'live';

    $mebershipBuildFormHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlan($this->membershipCreationForm);
    $mebershipBuildFormHook->buildForm();

    $this->expectException(PEAR_Exception::class);
    $this->membershipCreationForm->getElement('installments');
  }

}
