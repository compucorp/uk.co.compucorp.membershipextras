<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing 'Payment PlanExtra Attribute' custom group and its fields.
 */
class CRM_MembershipExtras_Setup_Manage_CustomGroup_PaymentPlanExtraAttributes extends AbstractManager {

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
      'is_active',
    ];
    foreach ($customFields as $customFieldName) {
      civicrm_api3('CustomField', 'get', [
        'name' => $customFieldName,
        'custom_group_id' => 'payment_plan_extra_attributes',
        'api.CustomField.delete' => ['id' => '$value.id'],
      ]);
    }

    civicrm_api3('CustomGroup', 'get', [
      'name' => 'payment_plan_extra_attributes',
      'api.CustomGroup.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('CustomGroup', 'get', [
      'name' => 'payment_plan_extra_attributes',
      'api.CustomGroup.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
