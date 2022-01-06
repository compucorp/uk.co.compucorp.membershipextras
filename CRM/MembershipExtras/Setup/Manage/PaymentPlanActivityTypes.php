<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing 'Update Payment Plan Next Period' and 'Update Payment Plan Current Period'
 * activity types.
 */
class CRM_MembershipExtras_Setup_Manage_PaymentPlanActivityTypes extends AbstractManager {

  /**
   * @inheritDoc
   */
  public function create() {
    $optionValues = [
      ['name' => 'update_payment_plan_next_period', 'label' => 'Update Payment Plan Next Period'],
      ['name' => 'update_payment_plan_current_period', 'label' => 'Update Payment Plan Current Period'],
    ];
    foreach ($optionValues as $optionValue) {
      $result = civicrm_api3('OptionValue', 'get', [
        'sequential' => 1,
        'option_group_id' => 'activity_type',
        'name' => $optionValue['name'],
      ]);
      if (!empty($result['id'])) {
        continue;
      }

      $optionValue['option_group_id'] = 'activity_type';
      $optionValue['filter'] = 1;
      $optionValue['is_reserved'] = 1;
      civicrm_api3('OptionValue', 'create', $optionValue);
    }
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'name' => [
        'IN' => ['update_payment_plan_next_period', 'update_payment_plan_current_period'],
      ],
      'api.OptionValue.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'name' => 'update_payment_plan_next_period',
      'api.OptionValue.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);

    civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'activity_type',
      'name' => 'update_payment_plan_current_period',
      'api.OptionValue.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
