<?php

use CRM_MembershipExtras_SettingsManager as MembershipTypeSettings;

/**
 * Implements form changes needed to be done to add membership type colour selection fields
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipTypeColour {

  /**
   * @var \CRM_Member_Form_MembershipType
   *   Form object that is being altered.
   */
  private $form;

  /**
   * @var string
   *   Path where template with new fields is stored.
   */
  private $templatePath;

  /**
   * CRM_MembershipExtras_Hook_BuildForm_MembershipTypeColour constructor.
   *
   * @param \CRM_Member_Form_MembershipType $form
   */
  public function __construct(CRM_Member_Form_MembershipType &$form) {
    $this->form = $form;
    $this->templatePath = CRM_MembershipExtras_ExtensionUtil::path() . '/templates';
  }

  /**
   * Implements changes to form loaded in $this->form
   */
  public function buildForm() {
    if ($this->form->getAction() & CRM_Core_Action::DELETE) {
      return;
    }
    $this->addColourRelatedFields();
  }

  /**
   * Adds the colour related fields for a membership type form.
   */
  private function addColourRelatedFields() {
    CRM_Core_Resources::singleton()->addScriptFile('uk.co.compucorp.membershipextras', 'js/vendor/spectrum/spectrum.min.js');
    CRM_Core_Resources::singleton()->addStyleFile('uk.co.compucorp.membershipextras', 'js/vendor/spectrum/spectrum.css');
    $this->form->add('advcheckbox', 'set_membership_colour', ts('Set membership colour?'));
    $this->form->add('text', 'membership_colour', ts('Membership colour'));
    $this->setColourFieldsDefaultValue();

    CRM_Core_Region::instance('page-body')->add([
      'template' => "{$this->templatePath}/CRM/Member/Form/MembershipTypeColour.tpl"
    ]);
  }

  /**
   * Sets default form field values for a membership type that already has colour fields set.
   */
  private function setColourFieldsDefaultValue() {
    $defaults = $this->form->_defaultValues;
    $membershipTypeColourSettings = Civi::settings()->get(MembershipTypeSettings::COLOUR_SETTINGS_KEY);

    if (!empty($this->form->_id)) {
      $membershipTypeId = $this->form->_id;
      if (isset($membershipTypeColourSettings[$membershipTypeId])) {
        $defaults['set_membership_colour'] = $membershipTypeColourSettings[$membershipTypeId]['set_membership_colour'];
        $defaults['membership_colour'] = $membershipTypeColourSettings[$membershipTypeId]['membership_colour'];
      }
      $this->form->setDefaults($defaults);
    }
  }
}
