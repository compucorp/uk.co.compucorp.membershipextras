<?php

/**
 * Implements form changes needed to be done to add payment plan as an option to
 * pay for a membership.
 */
abstract class CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlan {
  /**
   * @var string
   *   Path where template with new fields is stored.
   */
  protected $templatePath = '';

  /**
   * @var \CRM_Member_Form_Membership
   *   Form object that is being altered.
   */
  protected $form;

  /**
   * CRM_MembershipExtras_Hook_BuildForm_Membership constructor.
   *
   * @param \CRM_Member_Form $form
   */
  public function __construct(CRM_Member_Form &$form) {
    $this->form = $form;
    $this->templatePath = CRM_MembershipExtras_ExtensionUtil::path() . '/templates';
  }

  /**
   * This method should check if the given action is the proper operation so
   * that the form is changed to have payment plan data (ie. new for membership
   * form, renew for membership renewal form).
   *
   * @param int $action
   *   Constant value for the action, as defined in CRM_Core_Action class
   *
   * @return mixed
   */
  protected abstract function isCorrectOperation($action);

  /**
   * Implements changes to form loaded in $this->form
   */
  public function buildForm() {
    $this->addPaymentPlanSection();
  }

  /**
   * Adds new fields to the form and includes template that shows the fields and
   * juggles around exiting ones.
   */
  protected function addPaymentPlanSection() {
    if ($this->isCorrectOperation($this->form->_action)) {
      $this->form->add('text', 'installments', ts('Number of Installments'), '', FALSE);
      $this->form->addRule('installments', ts('Installments must be a number.'), 'numeric');

      $this->form->add('text', 'installments_frequency', ts('Interval'), '', FALSE);
      $this->form->addRule('installments_frequency', ts('Installments must be a number.'), 'numeric');

      $this->form->add('select', 'installments_frequency_unit',
        ts('Installments Frequency Units'),
        ['' => ts('- select -')] + CRM_Core_OptionGroup::values('recur_frequency_units', FALSE, FALSE, TRUE),
        FALSE
      );

      CRM_Core_Region::instance('page-body')->add([
        'template' => "{$this->templatePath}/CRM/Member/Form/PaymentPlanToggler.tpl"
      ]);
    }
  }
}
