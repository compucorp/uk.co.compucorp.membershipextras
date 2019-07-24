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

  public static function getPaymentMethodsThatAlwaysActivateMemberships() {
    $paymentMethods = self::getSettingValue('membershipextras_paymentmethods_that_always_activate_memberships');
    if (empty($paymentMethods)) {
      return [];
    }

    return $paymentMethods;
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

}
