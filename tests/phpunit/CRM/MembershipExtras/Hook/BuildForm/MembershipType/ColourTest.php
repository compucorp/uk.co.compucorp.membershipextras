<?php

/**
 * Class CRM_MembershipExtras_Hook_BuildForm_MembershipType_ColourTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipType_ColourTest extends BaseHeadlessTest {

  /**
   * @var CRM_Member_Form_MembershipType
   */
  private $membershipTypeForm;

  /**
   * Set up
   */
  public function setUp() {
    $formController = new CRM_Core_Controller();
    $this->membershipTypeForm = new CRM_Member_Form_MembershipType();
    $this->membershipTypeForm->controller = $formController;
  }

  /**
   * Tests Build Form
   * Check if added elements exist
   */
  public function testBuildForm() {
    $membershipTypeBuildFormHook = new CRM_MembershipExtras_Hook_BuildForm_MembershipType_Colour($this->membershipTypeForm);
    $membershipTypeBuildFormHook->buildForm();

    $setMembershipColourField = $this->membershipTypeForm->getElement('set_membership_colour');
    $membershipColourField = $this->membershipTypeForm->getElement('membership_colour');

    $this->assertTrue(is_object($setMembershipColourField));
    $this->assertTrue(is_object($membershipColourField));
  }

}
