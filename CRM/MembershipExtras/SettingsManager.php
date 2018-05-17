<?php

/**
 * Helps manage settings for the extension.
 */
class CRM_MembershipExtras_SettingsManager {

  /**
   * Returns the details of the default payment processor as per payment plan
   * settings, or NULL if it does not exist.
   *
   * @return int
   */
  public static function getDefaultProcessorID() {
    return self::getSettingValue('membershipextras_paymentplan_default_processor');
  }

  /**
   * Returns the 'days to renew in advance'
   * setting.
   *
   * @return int
   */
  public static function getDaysToRenewInAdvance() {
    $daysToRenewInAdvance = self::getSettingValue('membershipextras_paymentplan_days_to_renew_in_advance');
    if (empty($daysToRenewInAdvance)) {
      $daysToRenewInAdvance = 0;
    }

    return $daysToRenewInAdvance;
  }

  private static function getSettingValue($settingName) {
    return civicrm_api3('Setting', 'get', [
      'sequential' => 1,
      'return' => [$settingName],
    ])['values'][0][$settingName];
  }

}
