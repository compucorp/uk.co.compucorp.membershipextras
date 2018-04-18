<?php

class CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlanProcessor {

  /***
   * The form submitted data.
   *
   * @var CRM_Core_Form
   */
  private $form;

  public function __construct(&$form) {
    $this->form = &$form;
  }

  /**
   * Processes memberships after creating
   * or renewing them from Civicrm admin form in
   * case they were paid using the payment plan option.
   *
   * For now, it basically create the remaining installments
   * contributions upfront for the payment plan.
   */
  public function process() {
    if (!$this->isPaymentPlanPayment()) {
      return;
    }

    $recurContributionID = $this->getMembershipLastRecurContributionID();
    $installmentsHandler = new CRM_MembershipExtras_Service_MembershipInstallmentsHandler($recurContributionID);
    $installmentsHandler->createRemainingInstalmentContributionsUpfront();
  }

  /**
   * Detects if the membership is paid for
   * using payment plan option.
   *
   * @return bool
   */
  private function isPaymentPlanPayment() {
    $installmentsCount = CRM_Utils_Request::retrieve('installments', 'Int');
    $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($isSavingContribution && $contributionIsPaymentPlan && $installmentsCount > 1) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the membership last recurring contribution ID.
   *
   * @return mixed
   */
  private function getMembershipLastRecurContributionID() {
    $membershipID = $this->form->_id;

    $recurContributionID = civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'return' => ['contribution_id.contribution_recur_id'],
      'membership_id' => $membershipID,
      'options' => ['limit' => 1, 'sort' => 'contribution_id.contribution_recur_id DESC'],
    ])['values'][0]['contribution_id.contribution_recur_id'];

    return $recurContributionID;
  }

}
