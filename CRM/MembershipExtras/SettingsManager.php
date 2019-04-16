<?php

/**
 * Helps manage settings for the extension.
 */
class CRM_MembershipExtras_SettingsManager {
  const COLOUR_SETTINGS_KEY = 'membership_type_colour';

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

  /**
   * Returns the 'days to disable membership periods with overdue payment'
   * setting.
   *
   * @return int
   */
  public static function getDaysToDisableMembershipPeriodsWithOverduePayment() {
    $daysToDisableMP = self::getSettingValue('membershipextras_paymentplan_days_to_disable_membership_period_with_overdue_payment');
    if (empty($daysToDisableMP)) {
      return 0;
    }

    return $daysToDisableMP;
  }

  public static function getCustomFieldsIdsToExcludeForAutoRenew() {
    $customGroupsIdsToExcludeForAutoRenew = self::getSettingValue('membershipextras_customgroups_to_exclude_for_autorenew');
    if (empty($customGroupsIdsToExcludeForAutoRenew)) {
      return [];
    }

    $customFieldsToExcludeForAutoRenew = civicrm_api3('CustomField', 'get', [
      'return' => ['id'],
      'sequential' => 1,
      'custom_group_id' => ['IN' => $customGroupsIdsToExcludeForAutoRenew],
      'options' => ['limit' => 0],
    ]);
    if (empty($customFieldsToExcludeForAutoRenew['values'])) {
      return [];
    }

    $customFieldsIdsToExcludeForAutoRenew = [];
    foreach($customFieldsToExcludeForAutoRenew['values'] as $customField) {
      $customFieldsIdsToExcludeForAutoRenew[] = $customField['id'];
    }

    return $customFieldsIdsToExcludeForAutoRenew;
  }

  private static function getSettingValue($settingName) {
    return civicrm_api3('Setting', 'get', [
      'sequential' => 1,
      'return' => [$settingName],
    ])['values'][0][$settingName];
  }

  /**
   * Gets the extension configuration fields
   *
   * @return array
   */
  public static function getConfigFields() {
    $allowedConfigFields = self::fetchSettingFields();
    if (!isset($allowedConfigFields) || empty($allowedConfigFields)) {
      $result = civicrm_api3('System', 'flush');
      if ($result['is_error'] == 0){
        $allowedConfigFields =  self::fetchSettingFields();
      }
    }
    return $allowedConfigFields;
  }

  private static function fetchSettingFields() {
    return civicrm_api3('Setting', 'getfields',[
      'filters' =>[ 'group' => 'membershipextras_paymentplan'],
    ])['values'];
  }

  /**
   * Receives a background color in hexadecimal format and determines
   * what the text colour should be based on the intensity of the background
   * colour. Returns black or white in hex format.
   *
   * @param string $hex
   *
   * @return string
   */
  public static function computeTextColor($hex) {
    if ($hex == 'inherit') {
      return 'inherit';
    }

    list($r, $g, $b) = array_map('hexdec', str_split(trim($hex, '#'), 2));
    $uiColours = [$r / 255, $g / 255, $b / 255];
    $c = array_map('self::calcColour', $uiColours);

    $luminance = (0.2126 * $c[0]) + (0.7152 * $c[1]) + (0.0722 * $c[2]);

    return ($luminance > 0.179) ? '#000000' : '#ffffff';
  }

  /**
   * Calculate colour for RGB values.
   *
   * @param int $c
   *
   * @return float|int
   */
  private static function calcColour($c) {
    if ($c <= 0.03928) {
      return $c / 12.92;
    }
    else {
      return pow(($c + 0.055) / 1.055, 2.4);
    }
  }

}
