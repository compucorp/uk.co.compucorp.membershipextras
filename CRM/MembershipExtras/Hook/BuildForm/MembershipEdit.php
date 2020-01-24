<?php

class CRM_MembershipExtras_Hook_BuildForm_MembershipEdit {


  /**
   * @var CRM_Member_Form_Membership
   */
  private $form;

  /**
   * @param \CRM_Member_Form_Membership $form
   */
  public function __construct(CRM_Member_Form_Membership $form) {
    $this->form = $form;
  }

  public function buildForm() {
    $this->allowMembershipStatusChange();
  }

  /**
   * CiviCRM core prevents editing auto-renewal
   * membership status by override using the edit
   * form, but Membershipextras workflow is different
   * from CiviCRM core and changing the membership
   * status should be allowed which is what this
   * method does.
   */
  private function allowMembershipStatusChange() {
    $isOverrideElement = $this->form->getElement('is_override');
    $isOverrideElement->unfreeze();
  }

}
