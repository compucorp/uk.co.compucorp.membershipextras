<?php

use CRM_MembershipExtras_Hook_BuildForm_MembershipType_Base as Base;
use CRM_MembershipExtras_SettingsManager as SettingsManager;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodCalculator;

/**
 * Class CRM_MembershipExtras_Hook_BuildForm_MembershipType_Setting
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipType_Setting extends Base {

  const ANNUAL_PRORATA_CALCULATION_ELEMENT = 'membership_type_annual_pro_rata_calculation';

  /**
   *
   * @param \CRM_Member_Form_MembershipType $form
   */
  public function __construct(CRM_Member_Form_MembershipType &$form) {
    parent::__construct($form);
  }

  /**
   * Build Form and set default values
   */
  public function buildForm() {
    parent::buildForm();
    $settings = Civi::settings()->get(SettingsManager::MEMBERSHIP_TYPE_SETTINGS_KEY);
    if (is_null($settings)) {
      return;
    }
    if (empty($this->form->_id)) {
      $this->setDefaultValue();
    }
    else {
      $fields = [self::ANNUAL_PRORATA_CALCULATION_ELEMENT];
      parent::setFieldsDefaultValue($settings, $fields);
    }
  }

  /**
   * Sets defaults settings for a create membership type form
   */
  private function setDefaultValue() {
    $defaults = [self::ANNUAL_PRORATA_CALCULATION_ELEMENT => FixedPeriodCalculator::BY_DAYS];
    $this->form->setDefaults($defaults);
  }

  /**
   * Add additional setting fields to membership type form.
   *
   * @throws CRM_Core_Exception
   */
  protected function addFields() {
    $options = [
      FixedPeriodCalculator::BY_DAYS => ts('By days'),
      FixedPeriodCalculator::BY_MONTHS => ts('By months'),
    ];

    $this->form->add('select', self::ANNUAL_PRORATA_CALCULATION_ELEMENT, ts('Annual Pro-rata Calculation'),
      ['' => ts('- select -')] + $options, FALSE, ['class' => 'crm-select2']
    );
  }

  /**
   * Adds required resources and templates
   */
  protected function addResources() {
    CRM_Core_Region::instance('page-body')->add([
      'template' => "{$this->templatePath}/CRM/Member/Form/MembershipType/Settings.tpl",
    ]);
  }

}
