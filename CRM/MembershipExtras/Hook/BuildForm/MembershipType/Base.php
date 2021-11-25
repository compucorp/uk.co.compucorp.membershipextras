<?php


abstract class CRM_MembershipExtras_Hook_BuildForm_MembershipType_Base {

  /**
   * @var \CRM_Member_Form_MembershipType
   *   Form object that is being altered.
   */
  protected $form;

  /**
   * @var string
   *
   *   Path where template with new fields is stored.
   */
  protected $templatePath;

  protected function buildForm() {
    if ($this->form->getAction() & CRM_Core_Action::DELETE) {
      return;
    }
  }

  /**
   * CRM_MembershipExtras_Hook_BuildForm_MembershipTypeColour constructor.
   *
   * @param \CRM_Member_Form_MembershipType $form
   */
  protected function __construct(CRM_Member_Form_MembershipType &$form) {
    $this->form = $form;
    $this->templatePath = CRM_MembershipExtras_ExtensionUtil::path() . '/templates';
    $this->addResources();
    $this->addFields();
  }

  /**
   * Sets default form field values from settings
   * @param $settings
   * @param $fields
   */
  protected function setFieldsDefaultValue(array $settings, array $fields) {
    if (empty($this->form->_id)) {
      return;
    }
    $membershipTypeId = $this->form->_id;
    if (!isset($settings[$membershipTypeId])) {
      return;
    }
    $defaults = $this->form->_defaultValues;
    foreach ($fields as $field) {
      $defaults[$field] = $settings[$membershipTypeId][$field];
    }
    $this->form->setDefaults($defaults);
  }

  abstract protected function addResources();

  abstract protected function addFields();

}
