<?php

use CRM_MembershipExtras_Hook_PostProcess_RecurringContributionLineItemCreator as RecurringContributionLineItemCreator;
use CRM_MembershipExtras_Utils_InstalmentSchedule as InstalmentScheduleUtils;

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
   * For now, it basically create the remaining instalments
   * contributions upfront for the payment plan.
   *
   * @throws CiviCRM_API3_Exception
   */
  public function postProcess() {
    if (!$this->isPaymentPlanPayment()) {
      return;
    }

    $recurContributionID = $this->getMembershipLastRecurContributionID();
    $this->createRecurringSubscriptionLineItems($recurContributionID);

    $paymentPlanSchedule = CRM_Utils_Request::retrieve('payment_plan_schedule', 'String');
    $instalmentDetails = InstalmentScheduleUtils::getInstalmentDetails($paymentPlanSchedule,  $this->form->_id);
    $instalmentsCount = $instalmentDetails['instalments_count'];
    if ($instalmentsCount > 1) {
      $instalmentsHandler = new CRM_MembershipExtras_Service_MembershipInstallmentsHandler($recurContributionID);
      $instalmentsHandler->createRemainingInstalmentContributionsUpfront();
    }
  }

  /**
   * Detects if the membership is paid for
   * using payment plan option.
   *
   * @return bool
   */
  private function isPaymentPlanPayment() {
    $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($isSavingContribution && $contributionIsPaymentPlan) {
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

  /**
   * Creates recurring contribution's line items to set up current and next
   * periods.
   *
   * @param $recurContributionID
   */
  private function createRecurringSubscriptionLineItems($recurContributionID ) {
    $lineItemCreator = new RecurringContributionLineItemCreator($recurContributionID);
    $lineItemCreator->create();
  }

}
