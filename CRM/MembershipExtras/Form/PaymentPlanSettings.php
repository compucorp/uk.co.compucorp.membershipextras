<?php

/**
 * Payment Plan Settings form controller
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_MembershipExtras_Form_PaymentPlanSettings extends CRM_Core_Form {

  /**
   * Contains the setting field names for payment plan.
   * Used for caching the list since it require an API
   * call to get it.
   *
   * @var string[]
   */
  private $settingFields = [];

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Payment Plan Settings'));

    $settingFields  = $this->getSettingFields();
    $settingFieldNames = [];
    foreach ($settingFields  as $name => $field) {
      $this->add(
        $field['html_type'],
        $name,
        ts($field['title']),
        '',
        $field['is_required'],
        ''
      );

      $settingFieldNames[] = $name;
    }

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);

    $this->assign('settingFields', $settingFieldNames);
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    $currentValues = civicrm_api3('setting', 'get',
      ['return' => array_keys($this->getSettingFields())]);

    $defaults = [];
    $domainID = CRM_Core_Config::domainID();
    foreach ($currentValues['values'][$domainID] as $name => $value) {
      $defaults[$name] = $value;
    }

    return $defaults;
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $settingFields = $this->getSettingFields();
    $submittedValues = $this->exportValues();

    $valuesToSave = [];
    foreach ($settingFields as $fieldName => $fieldData) {
      if (array_key_exists($fieldName, $submittedValues)) {
        $valuesToSave[$fieldName] = $submittedValues[$fieldName];
      }
      else if($fieldData['html_type'] === 'checkbox') {
        $valuesToSave[$fieldName] = FALSE;
      }
    }

    civicrm_api3('setting', 'create', $valuesToSave);
  }

  /**
   * Gets the setting field names to be set on this form.
   *
   * @return array
   */
  private function getSettingFields() {
    if (!empty($this->settingFields )) {
      return $this->settingFields;
    }

    $this->settingFields =  civicrm_api3('setting', 'getfields',[
      'filters' =>['group' => 'membershipextras_paymentplan'],
    ])['values'];

    return $this->settingFields;
  }

}
