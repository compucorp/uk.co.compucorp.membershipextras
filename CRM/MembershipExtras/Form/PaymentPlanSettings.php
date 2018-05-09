<?php

use CRM_MembershipExtras_PaymentProcessorType_ManualRecurringPayment as ManualRecurringPaymentProcessorType;

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
      $attributes = '';

      switch (true) {
        case $name == 'membershipextras_paymentplan_default_processor':
          $attributes = array('' => ts('- select -')) + $this->getManualPaymentProcessors();
          break;

        case $field['html_type'] == 'select':
          $functionName = CRM_Utils_Array::value('name', CRM_Utils_Array::value('pseudoconstant', $field));
          if ($functionName) {
            $attributes = array('' => ts('- select -')) + CRM_Contribute_PseudoConstant::$functionName();
          }
          break;
      }

      $this->add(
        $field['html_type'],
        $name,
        ts($field['title']),
        $attributes,
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
   * Builds an array mapping manual payment processor id's to processor name.
   *
   * @return array
   */
  private function getManualPaymentProcessors() {
    $offlineRecPaymentProcessors = civicrm_api3('PaymentProcessor', 'get', [
      'payment_processor_type_id' => ManualRecurringPaymentProcessorType::NAME,
    ]);

    $recPaymentProcessors = [];
    if (!empty($offlineRecPaymentProcessors['values'])) {
      foreach ($offlineRecPaymentProcessors['values'] as $paymentProcessor) {
        $recPaymentProcessors[$paymentProcessor['id']] = $paymentProcessor['name'];
      }
    }

    return $recPaymentProcessors;
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
