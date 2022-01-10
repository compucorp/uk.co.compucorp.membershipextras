<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing 'Offline autorenew option' custom group and its fields.
 */
class CRM_MembershipExtras_Setup_Manage_CustomGroup_OfflineAutorenewOption extends AbstractManager {

  /**
   * @inheritDoc
   */
  public function create() {
    // nothing to do here, the custom group will be created automatically
    // because it is defined in the extension XML files.
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    $customFields = [
      'optout_last_price_offline_autorenew',
    ];
    foreach ($customFields as $customFieldName) {
      civicrm_api3('CustomField', 'get', [
        'name' => $customFieldName,
        'custom_group_id' => 'offline_autorenew_option',
        'api.CustomField.delete' => ['id' => '$value.id'],
      ]);
    }

    civicrm_api3('CustomGroup', 'get', [
      'name' => 'offline_autorenew_option',
      'api.CustomGroup.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('CustomGroup', 'get', [
      'name' => 'offline_autorenew_option',
      'api.CustomGroup.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
