<?php

use CRM_MembershipExtras_Service_RecurringContributionLineItemCreator as RecurringContributionLineItemCreator;
use CRM_MembershipExtras_Utils_InstalmentSchedule as InstalmentScheduleUtils;

class CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlanProcessor {

  use CRM_MembershipExtras_Helper_InstalmentHelperTrait;

  /***
   * The form submitted data.
   *
   * @var CRM_Core_Form
   */
  private $form;

  /**
   * @var array
   */
  private $formSubmittedValues;

  /**
   * @var string
   */
  private $formName;

  /**
   * @var int
   */
  private $membershipTypeId;

  public function __construct($formName, &$form) {
    $this->formName = $formName;
    $this->form = &$form;
    $this->formSubmittedValues = $this->form->exportValues();
    $this->membershipTypeId = $this->getMembershipTypeId();
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
   * @throws Exception
   */
  public function postProcess() {
    if (!$this->isPaymentPlanWithSchedule()) {
      return;
    }

    $recurContributionID = $this->getMembershipLastRecurContributionID();
    $this->createRecurringSubscriptionLineItems($recurContributionID);

    $membershipId = $this->form->_id;
    $paymentPlanActivationService = new CRM_MembershipExtras_Service_MembershipPaymentPlanActivation();
    $paymentPlanActivationService->activateMembershipCurrentPaymentPlan($membershipId);

    $paymentPlanSchedule = $this->formSubmittedValues['payment_plan_schedule'];
    $instalmentDetails = InstalmentScheduleUtils::getInstalmentDetails($paymentPlanSchedule, $this->form->_id);
    $instalmentsCount = $instalmentDetails['instalments_count'];
    if ($instalmentsCount == 1) {
      return;
    }

    $membershipTypeObj = CRM_Member_BAO_MembershipType::findById($this->membershipTypeId);
    $startDate = $this->getStartDate();
    $actualInstalmentCount = $this->getInstalmentsNumber($membershipTypeObj, $paymentPlanSchedule, $startDate);
    $instalmentsHandler = new CRM_MembershipExtras_Service_MembershipInstalmentsHandler($recurContributionID);
    $instalmentsHandler->setInstalmentsCount($actualInstalmentCount);
    $instalmentsHandler->createRemainingInstalmentContributionsUpfront();
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
  private function createRecurringSubscriptionLineItems($recurContributionID) {
    $lineItemCreator = new RecurringContributionLineItemCreator($recurContributionID);
    $lineItemCreator->create();
  }

  /**
   * Detects if the membership is paid for
   * using payment plan option.
   *
   * @return bool
   */
  private function isPaymentPlanWithSchedule() {
    $paymentPlanSchdule = $this->formSubmittedValues['payment_plan_schedule'];
    $isSavingContribution = $this->formSubmittedValues['record_contribution'];

    if ($isSavingContribution && !empty($paymentPlanSchdule)) {
      return TRUE;
    }

    return FALSE;
  }

  private function getMembershipTypeId() {
    return civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => $this->form->_id,
      'return' => ["membership_type_id"],
    ])['values'][0]['membership_type_id'];
  }

  /**
   * Gets membership start date based on the submitted form.
   */
  private function getStartDate() {
    if ($this->formName === 'CRM_Member_Form_MembershipRenewal') {
      $startDate = new DateTime($this->formSubmittedValues['received_date']);
    }
    else {
      $joinDate = !empty($this->formSubmittedValues['join_date']) ? new DateTime($this->formSubmittedValues['join_date']) : NULL;
      $startDate = !empty($this->formSubmittedValues['start_date']) ? new DateTime($this->formSubmittedValues['start_date']) : $joinDate;
      $endDate = !empty($this->formSubmittedValues['end_date']) ? new DateTime($this->formSubmittedValues['end_date']) : NULL;
      if (empty($startDate)) {
        $startDate = new DateTime($this->getMembershipStartDate($this->membershipTypeId, $startDate, $endDate, $joinDate));
      }
    }

    return $startDate;
  }

}
