<?php

class CRM_MembersOnlyEvent_Configurations {

  /**
   * @const String
   * The settings group name in which all extension settings are stored.
   */
  const SETTINGS_GROUP_NAME = 'members_only_event_extension_settings';

  /**
   * Gets the extension system-wide configurations
   *
   * @return array
   */
  public static function get() {
    $settingFields = civicrm_api3('Setting', 'getfields',
      array(
        'filters' => array('group' => self::SETTINGS_GROUP_NAME),
      )
    )['values'];
    $settingFields = array_keys($settingFields);

    // we do separate API call for each field since
    // civicrm Setting API does not allow retrieving more than
    // on field value in a single API call
    $settingValues = array();
    foreach($settingFields as $field) {
      $configurations = civicrm_api3('Setting', 'get',
        array('return' => $field, 'sequential' => 1))['values'][0];
      $settingValues[$field] = $configurations[$field];
    }


    return $settingValues;
  }
}
