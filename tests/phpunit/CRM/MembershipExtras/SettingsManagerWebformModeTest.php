<?php

use CRM_MembershipExtras_SettingsManager as SettingsManager;

/**
 * Tests the webform existing-membership mode setting (MRJ-001.1).
 *
 * @group headless
 */
class CRM_MembershipExtras_SettingsManagerWebformModeTest extends BaseHeadlessTest {

  private const SETTING = 'membershipextras_webform_existing_membership_mode';

  public function testDefaultsToRenewWhenNeverSaved() {
    Civi::settings()->revert(self::SETTING);

    $this->assertSame('renew', Civi::settings()->get(self::SETTING));
    $this->assertSame(SettingsManager::WEBFORM_MODE_RENEW, SettingsManager::getWebformExistingMembershipMode());
  }

  public function testReturnsEachSavedOption() {
    foreach (array_keys(SettingsManager::getWebformExistingMembershipModeOptions()) as $mode) {
      Civi::settings()->set(self::SETTING, $mode);
      $this->assertSame($mode, SettingsManager::getWebformExistingMembershipMode(), "Mode $mode");
    }
  }

  public function testUnknownValueFallsBackToRenew() {
    Civi::settings()->set(self::SETTING, 'bogus');

    $this->assertSame(SettingsManager::WEBFORM_MODE_RENEW, SettingsManager::getWebformExistingMembershipMode());
  }

  public function testOptionsAreTheThreeSpecLabelsInOrder() {
    $this->assertSame([
      'renew' => 'Renew the existing membership (CiviCRM default)',
      'new_if_not_current' => 'Create a new membership only if the existing one is not current',
      'new_if_not_current_and_ended' => 'Create a new membership only if the existing one is not current and its end date has passed (more strict)',
    ], SettingsManager::getWebformExistingMembershipModeOptions());
  }

  public function testSettingMetadataIsInThePaymentPlanGroup() {
    $field = civicrm_api3('Setting', 'getfields', ['name' => self::SETTING])['values'][self::SETTING];

    $this->assertSame('membershipextras_paymentplan', $field['group']);
    $this->assertSame('String', $field['type']);
    $this->assertSame('select', $field['html_type']);
    $this->assertSame('renew', $field['default']);
  }

  public function testSettingOptionsComeFromThePseudoconstantCallback() {
    $options = civicrm_api3('Setting', 'getoptions', ['field' => self::SETTING])['values'];

    $this->assertSame(['renew', 'new_if_not_current', 'new_if_not_current_and_ended'], array_keys($options));
  }

}
