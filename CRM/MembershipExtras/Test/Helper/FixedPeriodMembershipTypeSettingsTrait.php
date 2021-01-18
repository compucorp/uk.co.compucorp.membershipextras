<?php


trait CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait {

  /**
   * Mocks Extras Membership Type Settings
   *
   * @param $membershipTypeId
   * @param $setting
   */
  protected function mockSettings($membershipTypeId, $setting) {
    $settings[$membershipTypeId]  = [
      'membership_type_annual_pro_rata_calculation' => $setting,
    ];
    $currentSettings  = Civi::settings()->get(CRM_MembershipExtras_SettingsManager::MEMBERSHIP_TYPE_SETTINGS_KEY);
    if (is_null($currentSettings)) {
      $membershipTypeSettings = $settings;
    }
    else {
      $membershipTypeSettings = array_merge($currentSettings, $settings);
    }
    Civi::settings()->set(CRM_MembershipExtras_SettingsManager::MEMBERSHIP_TYPE_SETTINGS_KEY, $membershipTypeSettings);
  }

}
