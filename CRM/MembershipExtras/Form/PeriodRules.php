<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Membership Period Rules form controller
 * 
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_MembershipExtras_Form_PeriodRules extends CRM_Core_Form {

  private $settingFieldNames = [];

  private function setFormTitle() {
    CRM_Utils_System::setTitle(E::ts('Membership Period Rules'));
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    $this->setFormTitle();
    $this->setFormButtons();

    $settingNameFieldsMap = $this->getSettingFields();
    $this->addSettingFields($settingNameFieldsMap);
    $this->assign('settingFields', $this->settingFieldNames);
  }

  protected function setFormButtons() {
    $buttons = [
      [
        'type' => 'submit',
        'name' => E::ts('Submit'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ],
    ];

    $this->addButtons($buttons);
  }

  /**
   * Adds a setting field to the form.
   *
   * @param array $field
   */
  private function addSettingField($field, $attributes, $isChild = FALSE) {
    $this->add(
      $field['html_type'],
      $field['name'],
      E::ts($field['title']),
      '',
      $field['is_required'],
      $attributes
    );
  }

  private function addOverdueMembershipActionRadioGroupField($field, $attributes) {
    $options = [];
    foreach ($field['options'] as $key => $option) {
      $attributes['id'] = "{$field['name']}_{$key}";
      $options[] = $this->createElement('radio', NULL, NULL, $option, $key, $attributes);
    }

    $separator = isset($field['separator']) ? $field['separator'] : NULL;
    $group = $this->addGroup($options, $field['name'], $field['title'], $separator);
    $optionEditKey = 'data-option-edit-path';
    if (!empty($attributes[$optionEditKey])) {
      $group->setAttribute($optionEditKey, $attributes[$optionEditKey]);
    }

    if ($field['is_required']) {
      $this->addRule($field['name'], ts('%1 is a required field.', [1 => $field['title']]), 'required');
    }
  }

  private function addSettingFields($fields) {
    foreach ($fields as $name => $field) {
      $attributes = isset($field['extra_attributes']) ? $field['extra_attributes'] : [];
      $attributes['id'] = $name;
      switch($name) {
        case 'membershipextras_membership_period_rules_action_on_period_with_overdue_payment':
        case 'membershipextras_membership_period_rules_update_the_period_end_date_to':
          $this->addOverdueMembershipActionRadioGroupField($field, $attributes);
          break;
        default:
          $this->addSettingField($field, $attributes);
      }
      $this->settingFieldNames[] = $name;
    }
  }

  /**
   * Gets the setting field names to be set on this form.
   *
   * @return array
   */
  private function getSettingFields($group = 'membershipextras_membership_period_rules') {
    $settingFields =  civicrm_api3('setting', 'getfields', [
      'filters' => ['group' => $group],
    ])['values'];

    $childSettingFields = [];
    foreach ($settingFields as $fieldName => $fieldData) {
      if (isset($fieldData['has_children']) && $fieldData['has_children']) {
        $childSettingFields = $this->getSettingFields($fieldName);
      }
    }

    return array_merge($settingFields, $childSettingFields);
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $settingNameFieldsMap = $this->getSettingFields();
    $submittedValues = $this->exportValues();

    $valuesToSave = $this->constructValuesToSave($settingNameFieldsMap, $submittedValues);
    civicrm_api3('setting', 'create', $valuesToSave);
  }

  /**
   * @param array $settingNameFieldsMap
   */
  private function constructValuesToSave($settingNameFieldsMap, $submittedValues) {
    $valuesToSave = [];
    foreach ($settingNameFieldsMap as $fieldName => $fieldData) {
      if (array_key_exists($fieldName, $submittedValues)) {
        $valuesToSave[$fieldName] = $submittedValues[$fieldName];
      } else if ($fieldData['html_type'] === 'checkbox') {
        $valuesToSave[$fieldName] = FALSE;
      }
    }

    return $valuesToSave;
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    $settingNameFieldsMap = $this->getSettingFields();
    $currentValues = civicrm_api3('setting', 'get', [
      'return' => array_keys($settingNameFieldsMap)
    ]);

    $defaults = [];
    $domainID = CRM_Core_Config::domainID();
    foreach ($currentValues['values'][$domainID] as $name => $value) {
      $defaults[$name] = $value;
    }

    return $defaults;
  }
}
