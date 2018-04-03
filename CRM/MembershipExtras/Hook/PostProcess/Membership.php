<?php

/**
 * Post processes Membership create/edit Form.
 */
class CRM_MembershipExtras_Hook_PostProcess_Membership extends CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlan {

  /**
   * CRM_MembershipExtras_Hook_PostProcess_Membership constructor.
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
