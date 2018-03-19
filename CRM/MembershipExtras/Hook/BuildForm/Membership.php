<?php

class CRM_MembershipExtras_Hook_BuildForm_Membership {
  private $templatePath = '';

  public function __construct() {
    $path = realpath(dirname(__FILE__));
    $classPath = strtr(
      self::class,
      array('BuildForm_Membership' => 'BuildForm', '_' => '/')
    );
    $this->templatePath = strtr($path, array($classPath => '')) . 'templates';
  }

  public function buildForm(CRM_Member_Form_Membership &$form) {
    $form->add('text', 'installments', ts('Number of Installments'), '', TRUE);
    $form->addRule('installments', ts('Installments must be a number.'), 'numeric');

    $form->add('text', 'installments_frequency', ts('Interval'), '', TRUE);
    $form->addRule('installments_frequency', ts('Installments must be a number.'), 'numeric');

    $form->add('select', 'installments_frequency_unit',
      ts('Installments Frequency Units'),
      array('' => ts('- select -')) + CRM_Core_OptionGroup::values('recur_frequency_units', FALSE, FALSE, TRUE),
      TRUE
    );

    CRM_Core_Region::instance('page-body')->add(array(
      'template' => "{$this->templatePath}/CRM/Member/Form/PaymentPlanToggler.tpl"
    ));
  }

}
