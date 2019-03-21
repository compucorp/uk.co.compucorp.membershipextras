<?php

/**
 * Form controller class
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_MembersOnlyEvent_Form_Configurations extends CRM_Core_Form {

  /**
   * @inheritdoc
   */
  public function preProcess() {
    CRM_Utils_System::setTitle(ts('Members-Only Event Extension Configurations'));
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    $this->add(
      'checkbox',
      'membership_duration_check',
      ts('Membership duration check')
    );

    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Submit'),
        'isDefault' => TRUE,
      ),
    ));
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    $existingValues = civicrm_api3('Setting', 'get',
      array('sequential' => 1, 'return' => 'membership_duration_check')
    );

    $defaults['membership_duration_check'] = !empty($existingValues['values'][0]['membership_duration_check']) ? TRUE : FALSE;

    return $defaults;
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $params = $this->exportValues();

    $settingsToSave['membership_duration_check'] = !empty($params['membership_duration_check']) ? TRUE : FALSE;

    civicrm_api3('Setting', 'create', $settingsToSave);

    CRM_Core_Session::setStatus(ts('The configurations have been saved.'), ts('Saved'), 'success');
  }
}
