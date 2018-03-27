<?php

/**
 * Alters Membership creation form to include payment plan options.
 */
class CRM_MembershipExtras_Hook_BuildForm_Membership {

  /**
   * @var string
   *   Path where template with new fields is stored.
   */
  private $templatePath = '';

  /**
   * @var \CRM_Member_Form_Membership
   *   Form object that is being altered.
   */
  private $form;

  /**
   * CRM_MembershipExtras_Hook_BuildForm_Membership constructor.
   *
   * @param \CRM_Member_Form_Membership $form
   */
  public function __construct(CRM_Member_Form_Membership &$form) {
    $this->form = $form;

    $path = realpath(dirname(__FILE__));
    $classPath = strtr(
      self::class,
      ['BuildForm_Membership' => 'BuildForm', '_' => '/']
    );
    $this->templatePath = strtr($path, [$classPath => '']) . 'templates';
  }

  /**
   * Adds new fields to the form and includes template that shows the fields and
   * juggles around exiting ones.
   */
  public function buildForm() {
    if ($this->form->_action & CRM_Core_Action::ADD) {
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
