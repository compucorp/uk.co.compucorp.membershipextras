<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_SettingsManager as SettingsManager;

/**
 * CRM_MembershipExtras_Hook_PostProcess_MembershipTypeSettingTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_PostProcess_MembershipTypeSettingTest extends BaseHeadlessTest {

  /**
   * @var CRM_Member_Form_MembershipType
   */
  private $membershipTypeForm;

  public function setUp() {
    $formController = new CRM_Core_Controller();
    $this->membershipTypeForm = new CRM_Member_Form_MembershipType();
    $this->membershipTypeForm->controller = $formController;
  }

  public function testProcess() {
    $mockValue = 2;
    $fields = [
      'membership_type_annual_pro_rata_calculation' => $mockValue,
    ];
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'xyz',
      'period_type' => 'fixed',
    ]);
    $settings = $this->getSettings();

    $this->assertNull($settings);

    //simulate form value
    $this->membershipTypeForm->_id = $membershipType['id'];
    $this->membershipTypeForm->_submitValues = $fields;

    $postProcessHook = new CRM_MembershipExtras_Hook_PostProcess_MembershipTypeSetting($this->membershipTypeForm);
    $postProcessHook->process();;

    $settings = $this->getSettings();
    $this->assertNotNull($settings);
    foreach ($fields as $key => $field) {
      $this->assertEquals($mockValue, $settings[$membershipType['id']][$key]);
    }
  }

  private function getSettings() {

    return Civi::settings()->get(SettingsManager::MEMBERSHIP_TYPE_SETTINGS_KEY);
  }

}
