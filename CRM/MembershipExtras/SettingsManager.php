<?php

/**
 * Helps manage settings for the extension.
 */
class CRM_MembershipExtras_SettingsManager {

  /**
   * Constant variables for settings use in Membership Type
   * The membership_extras_membership_type_settings key should be used
   * for all adding settings required for Membership Type
   */
  const COLOUR_SETTINGS_KEY = 'membership_type_colour';
  const MEMBERSHIP_TYPE_SETTINGS_KEY = 'membership_extras_membership_type_settings';

  /**
   * Values of the 'membershipextras_webform_existing_membership_mode' setting.
   */
  const WEBFORM_MODE_RENEW = 'renew';
  const WEBFORM_MODE_NEW_IF_NOT_CURRENT = 'new_if_not_current';
  const WEBFORM_MODE_NEW_IF_NOT_CURRENT_AND_ENDED = 'new_if_not_current_and_ended';

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

  public static function getCustomFieldsIdsToExcludeForAutoRenew() {
    $customFieldsIdsToExcludeForAutoRenew = [];

    $customGroupsIdsToExcludeForAutoRenew = self::getSettingValue('membershipextras_customgroups_to_exclude_for_autorenew');
    if (!empty($customGroupsIdsToExcludeForAutoRenew)) {
      $customFieldsToExcludeForAutoRenew = \Civi\Api4\CustomField::get(FALSE)
        ->addSelect('id')
        ->addWhere('custom_group_id', 'IN', (array) $customGroupsIdsToExcludeForAutoRenew)
        ->execute();
      foreach ($customFieldsToExcludeForAutoRenew as $customField) {
        $customFieldsIdsToExcludeForAutoRenew[] = (int) $customField['id'];
      }
    }

    (new CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields($customFieldsIdsToExcludeForAutoRenew))->dispatch();

    return array_values(array_unique(array_map('intval', $customFieldsIdsToExcludeForAutoRenew)));
  }

  /**
   * Options for the 'Re-joining through a webform' setting, in display order.
   *
   * @return string[]
   */
  public static function getWebformExistingMembershipModeOptions() {
    return [
      self::WEBFORM_MODE_RENEW => ts('Renew the existing membership (CiviCRM default)'),
      self::WEBFORM_MODE_NEW_IF_NOT_CURRENT => ts('Create a new membership only if the existing one is not current'),
      self::WEBFORM_MODE_NEW_IF_NOT_CURRENT_AND_ENDED => ts('Create a new membership only if the existing one is not current and its end date has passed (more strict)'),
    ];
  }

  /**
   * What a webform sign-up does with an existing membership of the same
   * organisation. Falls back to 'renew' (CiviCRM default) for any unknown value.
   *
   * @return string
   */
  public static function getWebformExistingMembershipMode() {
    $mode = self::getSettingValue('membershipextras_webform_existing_membership_mode');
    if (is_string($mode) && array_key_exists($mode, self::getWebformExistingMembershipModeOptions())) {
      return $mode;
    }

    return self::WEBFORM_MODE_RENEW;
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
   * Gets Update start date renewal configuration
   *
   * @return int
   */
  public static function getUpdateStartDateRenewal() {
    $updateStartDateRenewal = self::getSettingValue('membershipextras_paymentplan_update_start_date_renewal');
    if (empty($updateStartDateRenewal)) {
      $updateStartDateRenewal = 0;
    }

    return $updateStartDateRenewal;
  }

  public static function getMembershipTypeSettings(int $membershipTypeId) {
    $settings = [];
    $membershipTypeSettings = Civi::settings()->get(self::MEMBERSHIP_TYPE_SETTINGS_KEY);
    if (!isset($membershipTypeSettings)) {
      return $settings;
    }
    foreach ($membershipTypeSettings as $id => $settingFields) {
      if ($id == $membershipTypeId) {
        $settings = $settingFields;
      }
    }

    return $settings;
  }

  /**
   * Calculate colour for RGB values.
   *
   * @param string $c
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

  private static function getSettingValue($settingName) {
    $result = civicrm_api3('Setting', 'get', [
      'sequential' => 1,
      'return' => [$settingName],
    ]);

    if (isset($result['values'][0][$settingName])) {
      return $result['values'][0][$settingName];
    }

    return NULL;
  }

}
