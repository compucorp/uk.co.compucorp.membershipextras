<?php

/**
 * Implements post process hook on membership renewal creation and renewal form
 * to create periods.
 */
class CRM_MembershipExtras_Hook_PostProcess_MembershipPeriodCreator {

  /**
   * Form that is being post processed.
   *
   * @var \CRM_Member_Form
   */
  private $form;

  /**
   * CRM_MembershipExtras_Hook_PostProcess_MembershipPeriodCreator constructor.
   *
   * @param \CRM_Member_Form $form
   */
  public function __construct(\CRM_Member_Form $form) {
    $this->form = $form;
  }

  public function postProcess() {
    CRM_MembershipExtras_BAO_MembershipPeriod::createPeriodForMembership($this->form->_id);
  }

}
