<?php

/**
 * Class CRM_MembershipExtras_Hook_BuildForm_MembershipType_SettingTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipType_SettingTest extends BaseHeadlessTest {

  /**
   * @var CRM_Member_Form_MembershipType
   */
  private $membershipTypeForm;

  public function setUp() {
    $formController = new CRM_Core_Controller();
    $this->membershipTypeForm = new CRM_Member_Form_MembershipType();
    $this->membershipTypeForm->controller = $formController;
  }

  /**
   * Tests Build Form
   * Test if added element exists
   */
  public function testBuildForm() {
    $membershipTypeBuildFormHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipType_Setting($this->membershipTypeForm);
    $membershipTypeBuildFormHook->buildForm();

    $annualProRataCalculationField = $this->membershipTypeForm->getElement('membership_type_annual_pro_rata_calculation');
    $this->assertTrue(is_object($annualProRataCalculationField));
  }

}
