<?php

/**
 * Postprocesses membership renewal form, creating payment plan installments when
 * necessary.
 */
class CRM_MembershipExtras_Hook_PostProcess_MembershipRenewal extends CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlan {

  /**
   * CRM_MembershipExtras_Hook_PostProcess_MembershipRenewal constructor.
   *
   * @param \CRM_Member_Form_MembershipRenewal $form
   */
  public function __construct(CRM_Member_Form_MembershipRenewal &$form) {
    parent::__construct($form);
  }

  /**
   * @inheritdoc
   */
  protected function isCorrectOperation($action) {
    return $action & CRM_Core_Action::RENEW;
  }

}
