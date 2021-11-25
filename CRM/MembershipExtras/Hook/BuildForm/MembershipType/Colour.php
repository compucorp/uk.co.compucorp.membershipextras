<?php

use CRM_MembershipExtras_SettingsManager as MembershipTypeSettings;
use CRM_MembershipExtras_Hook_BuildForm_MembershipType_Base as Base;

/**
 * Implements form changes needed to be done to add membership type colour selection fields
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipType_Colour extends Base {

  const SET_MEMBERSHIP_COLOUR_ELEMENT = 'set_membership_colour';
  const MEMBERSHIP_COLOUR_ELEMENT = 'membership_colour';

  /**
   * CRM_MembershipExtras_Hook_BuildForm_MembershipTypeColour constructor.
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
    $settings = Civi::settings()->get(MembershipTypeSettings::COLOUR_SETTINGS_KEY);
    if (is_null($settings)) {
      return;
    }
    $fields = [self::SET_MEMBERSHIP_COLOUR_ELEMENT, self::MEMBERSHIP_COLOUR_ELEMENT];
    parent::setFieldsDefaultValue($settings, $fields);
  }

  /**
   * Adds the colour related fields for a membership type form.
   */
  protected function addFields() {
    $this->form->add('advcheckbox', self::SET_MEMBERSHIP_COLOUR_ELEMENT, ts('Set Membership Colour?'));
    $this->form->add('text', self::MEMBERSHIP_COLOUR_ELEMENT, ts('Membership Colour'));
  }

  /**
   * Adds required resources and templates
   *
   * @throws CRM_Core_Exception
   */
  protected function addResources() {
    CRM_Core_Resources::singleton()->addScriptFile('uk.co.compucorp.membershipextras', 'js/vendor/spectrum/spectrum.min.js');
    CRM_Core_Resources::singleton()->addStyleFile('uk.co.compucorp.membershipextras', 'js/vendor/spectrum/spectrum.css');
    CRM_Core_Region::instance('page-body')->add([
      'template' => "{$this->templatePath}/CRM/Member/Form/MembershipType/Colour.tpl",
    ]);
  }

}
