<?php

/**
 * Implements form changes needed to be done to add payment plan as an option to
 * pay for a membership.
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlan {

  CONST DEFAULT_INSTALLMENTS_NUMBER = 12;

  CONST DEFAULT_INSTALLMENTS_FREQUENCY = 1;

  CONST DEFAULT_INSTALLMENTS_FREQUENCY_UNIT = 'month';

  /**
   * @var string
   *   Path where template with new fields is stored.
   */
  private $templatePath;

  /**
   * @var \CRM_Member_Form_Membership
   *   Form object that is being altered.
   */
  private $form;

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
   * Implements changes to form loaded in $this->form
   */
  public function buildForm() {
    $this->addPaymentPlanSection();
  }

  /**
   * Adds new fields to the form and includes template that shows the fields and
   * juggles around exiting ones.
   */
  private function addPaymentPlanSection() {
    if ($this->form->_mode === 'live') {
      return;
    }

    $paymentToggler = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String', $this->form, FALSE);
    $this->form->assign('contribution_type_toggle', $paymentToggler ?: 'contribution');

    $this->form->add('text', 'installments', ts('Number of Instalments'), '', FALSE);
    $this->form->addRule('installments', ts('Instalments must be a number.'), 'numeric');
    $this->form->setDefaults(['installments' => self::DEFAULT_INSTALLMENTS_NUMBER]);

    $this->form->add('text', 'installments_frequency', ts('Interval'), '', FALSE);
    $this->form->addRule('installments_frequency', ts('Instalments must be a number.'), 'numeric');
    $this->form->setDefaults(['installments_frequency' => self::DEFAULT_INSTALLMENTS_FREQUENCY]);

    $this->form->add('select', 'instalments_frequency_unit',
      ts('Instalments Frequency Units'),
      CRM_Core_OptionGroup::values('recur_frequency_units', FALSE, FALSE, TRUE),
      FALSE
    );
    $this->form->setDefaults(['installments_frequency_unit' => self::DEFAULT_INSTALLMENTS_FREQUENCY_UNIT]);

    CRM_Core_Region::instance('page-body')->add([
      'template' => "{$this->templatePath}/CRM/Member/Form/PaymentPlanToggler.tpl"
    ]);
  }
}
