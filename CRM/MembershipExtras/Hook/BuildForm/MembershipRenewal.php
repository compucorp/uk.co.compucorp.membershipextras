<?php

/**
 * Alters Membership creation form to include payment plan options.
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipRenewal extends CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlan {

  /**
   * CRM_MembershipExtras_Hook_BuildForm_MembershipRenewal constructor.
   *
   * @param \CRM_Member_Form_MembershipRenewal $form
   */
  public function __construct(CRM_Member_Form_MembershipRenewal $form) {
    parent::__construct($form);
  }

  /**
   * @inheritdoc
   */
  protected function isCorrectOperation($action) {
    return $action & CRM_Core_Action::RENEW;
  }

}
