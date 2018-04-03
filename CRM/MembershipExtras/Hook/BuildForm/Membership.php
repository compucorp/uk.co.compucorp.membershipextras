<?php

/**
 * Alters Membership creation form to include payment plan options.
 */
class CRM_MembershipExtras_Hook_BuildForm_Membership extends CRM_MembershipExtras_Hook_BuildForm_MembershipPaymentPlan {

  /**
   * CRM_MembershipExtras_Hook_BuildForm_Membership constructor.
   *
   * @param \CRM_Member_Form_Membership $form
   */
  public function __construct(CRM_Member_Form_Membership $form) {
    parent::__construct($form);
  }

  /**
   * @inheritdoc
   */
  protected function isCorrectOperation($action) {
    return $action & CRM_Core_Action::ADD;
  }

}
