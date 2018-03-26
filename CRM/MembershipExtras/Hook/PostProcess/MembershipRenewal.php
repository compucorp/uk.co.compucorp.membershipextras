<?php

/**
 * Postprocesses membership renewal, creating payment plan installments when
 * necessary.
 */
class CRM_MembershipExtras_Hook_PostProcess_MembershipRenewal extends CRM_MembershipExtras_Hook_PostProcess_Membership {

  /**
   * CRM_MembershipExtras_Hook_PostProcess_MembershipRenewal constructor.
   *
   * @param \CRM_Member_Form_MembershipRenewal $form
   */
  public function __construct(CRM_Member_Form_MembershipRenewal &$form) {
    $this->form = $form;
  }

  /**
   * Post-processes form to check if membership is going to be payed for with a
   * payment plan and makes the necessary adjustments.
   */
  public function postProcess() {
    $isMembershipRenewal = $this->form->getAction() & CRM_Core_Action::RENEW;
    $recordingContribution = $this->form->getSubmitValue('record_contribution');
    $contributionIsPaymentPlan = $this->form->getSubmitValue('contribution_type_toggle') == 'payment_plan';

    if ($isMembershipRenewal && $recordingContribution && $contributionIsPaymentPlan) {
      $this->loadCurrentMembershipAndContribution();
      $this->createRecurringContribution();
      $this->createInstallmentContributions();
      $this->deleteOldContribution();
    }
  }

}
