<?php

use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType as TriggerDateTypeSelectValues;
use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit as PeriodUnitSelectValues;

class CRM_MembershipExtras_Form_AutomatedUpgradeRule extends CRM_Core_Form {

  /**
   * Upgrade rule id
   *
   * @var int
   */
  private $id;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->id = CRM_Utils_Request::retrieve('id', 'Positive', $this);

    $mode = 'New';
    if ($this->id) {
      $mode = 'Edit';
    }

    $title = $mode . ' Automated Membership Upgrade Rule';
    CRM_Utils_System::setTitle(ts($title));

    $url = CRM_Utils_System::url('civicrm/admin/member/automated-upgrade-rules', 'reset=1');
    $session = CRM_Core_Session::singleton();
    $session->replaceUserContext($url);
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    $this->add('text', 'label', ts('Label'), '', TRUE);

    $this->addEntityRef('from_membership_type_id', ts('From Membership'), [
      'entity' => 'membership_type',
      'select' => ['minimumInputLength' => 0],
      'placeholder' => ts('Select Membership Type'),
    ], TRUE);

    $this->addEntityRef('to_membership_type_id', ts('To Membership'), [
      'entity' => 'membership_type',
      'select' => ['minimumInputLength' => 0],
      'placeholder' => ts('Select Membership Type'),
    ], TRUE);

    $this->add(
      'select',
      'upgrade_trigger_date_type',
      '',
      TriggerDateTypeSelectValues::getAll(),
      TRUE
    );

    $this->add('text', 'period_length', 'Period Length', ['size' => 5], TRUE);

    $this->add(
      'select',
      'period_length_unit',
      '',
      PeriodUnitSelectValues::getAll(),
      TRUE
    );

    $this->addEntityRef('filter_group', ts('Filter Group'), [
      'entity' => 'group',
      'select' => ['minimumInputLength' => 1],
    ]);

    $this->add('checkbox', 'is_active', ts('Enabled ?'));

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    if (empty($this->id)) {
      return $this->getCreateFormDefaults();
    }

    return $this->getUpdateFormDefaults();
  }

  /**
   * Gets the default fields values
   * when creating new upgrade rule
   */
  private function getCreateFormDefaults() {
    $values = [];
    $values['period_length'] = 1;
    $values['is_active'] = 1;

    return $values;
  }

  /**
   * Gets the default fields values
   * when updating an existing upgrade rule
   */
  private function getUpdateFormDefaults() {
    $upgradeRule = CRM_MembershipExtras_BAO_AutoMembershipUpgradeRule::getById($this->id);

    $values = [];
    $values['label'] = $upgradeRule->label;
    $values['from_membership_type_id'] = $upgradeRule->from_membership_type_id;
    $values['to_membership_type_id'] = $upgradeRule->to_membership_type_id;
    $values['upgrade_trigger_date_type'] = $upgradeRule->upgrade_trigger_date_type;
    $values['period_length'] = $upgradeRule->period_length;
    $values['period_length_unit'] = $upgradeRule->period_length_unit;
    $values['filter_group'] = $upgradeRule->filter_group;
    $values['is_active'] = $upgradeRule->is_active;

    return $values;
  }

  public function addRules() {
    $this->addFormRule(array('CRM_MembershipExtras_Form_AutomatedUpgradeRule', 'validatePeriodLengthField'));
  }

  /**
   * Validates if period length field
   * inputted value is acceptable.
   *
   * @param $fields
   *
   * @return array|bool
   */
  public static function validatePeriodLengthField($fields) {
    $errors = [];

    $isPositiveInteger = $fields['period_length'] > 0 && (is_int($fields['period_length']) || ctype_digit($fields['period_length']));
    if (!$isPositiveInteger) {
      $errors['period_length'] = ts('Period Length accepts positive numbers only.');
    }

    if (count($errors) >= 1) {
      return $errors;
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $submittedValues = $this->exportValues();

    if (!empty($this->id)) {
      $params['id'] = $this->id;
    }

    $params['label'] = $submittedValues['label'];
    $params['from_membership_type_id'] = $submittedValues['from_membership_type_id'];
    $params['to_membership_type_id'] = $submittedValues['to_membership_type_id'];
    $params['upgrade_trigger_date_type'] = $submittedValues['upgrade_trigger_date_type'];
    $params['period_length'] = $submittedValues['period_length'];
    $params['period_length_unit'] = $submittedValues['period_length_unit'];
    $params['filter_group'] = $submittedValues['filter_group'];
    $params['is_active'] = CRM_Utils_Array::value('is_active', $submittedValues, FALSE);

    CRM_MembershipExtras_BAO_AutoMembershipUpgradeRule::create($params);

    CRM_Core_Session::setStatus(ts('The membership automated upgrade rule has been saved.'), ts('Saved'), 'success');
    $returnURL = CRM_Utils_System::url('civicrm/admin/member/automated-upgrade-rules', 'reset=1');
    CRM_Utils_System::redirect($returnURL);
  }

}
