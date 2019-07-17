<?php

class CRM_MembershipExtras_Hook_BuildForm_MembershipAmountProRata {

  /**
   * @var string
   */
  private $templatePath;

  /**
   * @var \CRM_Member_Form_Membership
   *   Form object that is being altered.
   */
  private $form;

  /**
   * CRM_MembershipExtras_Hook_BuildForm_MembershipAmountProRata constructor.
   *
   * @param \CRM_Member_Form $form
   */
  public function __construct(CRM_Member_Form &$form) {
    $this->form = $form;
    $this->templatePath = CRM_MembershipExtras_ExtensionUtil::path() . '/templates';
  }

  /**
   * Implements changes to form loaded in $this->form
   */
  public function buildForm() {
    $this->addProRataTemplate();
  }

  /**
   * Adds the template for prorating membership amount.
   */
  private function addProRataTemplate() {
    CRM_Core_Region::instance('page-body')->add([
      'template' => "{$this->templatePath}/CRM/Member/Form/MembershipAmountProRata.tpl"
    ]);
  }
}
