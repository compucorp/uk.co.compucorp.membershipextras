<?php

use CRM_MembershipExtras_Setup_Configure_ConfigurerInterface as ConfigurerInterface;

/**
 * Creating the Line item external id which might be useful for data import purposes.
 * we create it in code instead of using XML because LineItem entity does not support
 * custom groups by default, and thus we have to add it to `cg_extend_objects` option
 * group first.
 */
class CRM_MembershipExtras_Setup_Configure_CreateLineItemExternalIDCustomField implements ConfigurerInterface {

  /**
   * @inheritDoc
   */
  public function apply() {
    $this->makeLineItemEntityAnExtendableEntity();
    $this->createLineItemExternalIDCustomGroupAndField();
  }

  /**
   * Adds the line item entity as an option value
   * to cg_extend_objects option group, which is
   * a CiviCRM core custom group. Option values
   * added to this Option group allows CiviCRM core
   * to support creating custom groups on entities
   * that does not support custom groups by default
   * such as the Line item entity.
   * See: https://docs.civicrm.org/dev/en/latest/step-by-step/create-entity/#111-making-our-entity-available-for-custom-data
   */
  private function makeLineItemEntityAnExtendableEntity() {
    $optionValues = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'cg_extend_objects',
      'name' => 'civicrm_line_item',
    ]);

    if (!$optionValues['count']) {
      civicrm_api3('OptionValue', 'create', [
        'option_group_id' => 'cg_extend_objects',
        'name' => 'civicrm_line_item',
        'label' => ts('Line Item'),
        'value' => 'LineItem',
      ]);
    }
  }

  private function createLineItemExternalIDCustomGroupAndField() {
    $customGroup = civicrm_api3('CustomGroup', 'get', [
      'extends' => 'LineItem',
      'name' => 'line_item_external_id',
    ]);
    if (!$customGroup['count']) {
      $customGroup = civicrm_api3('CustomGroup', 'create', [
        'extends' => 'LineItem',
        'name' => 'line_item_external_id',
        'title' => ts('Line Item External ID'),
        'table_name' => 'civicrm_value_line_item_ext_id',
        'is_active' => 0,
        'style' => 'Inline',
        'is_multiple' => 0,
      ]);
    }

    $customField = civicrm_api3('CustomField', 'get', [
      'custom_group_id' => $customGroup['id'],
      'name' => 'external_id',
    ]);
    if (!$customField['count']) {
      civicrm_api3('CustomField', 'create', [
        'custom_group_id' => $customGroup['id'],
        'name' => 'external_id',
        'label' => ts('External ID'),
        'data_type' => 'String',
        'html_type' => 'Text',
        'required' => 0,
        'is_active' => 0,
        'is_searchable' => 1,
        'column_name' => 'external_id',
        'is_view' => 1,
      ]);
    }
  }

}
