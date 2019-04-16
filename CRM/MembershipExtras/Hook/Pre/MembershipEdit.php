<?php

use CRM_MembershipExtras_Service_MembershipEndDateCalculator as MembershipEndDateCalculator;
use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

/**
 * Implements hook to be run before a membership is created/edited.
 */
class CRM_MembershipExtras_Hook_Pre_MembershipEdit {

  /**
   * Parameters that will be used to create the membership.
   *
   * @var array
   */
  private $params;

  /**
   * ID of the membership.
   *
   * @var int
   */
  private $id;

  /**
   * The membership payment contribution ID.
   *
   * @var int
   */
  private $paymentContributionID;

  private $recurContributionPreviousStatus;

  private $originalEndDateParam;

  public function __construct($id, &$params, $contributionID, $recurContributionPreviousStatus) {
    $this->id = $id;
    $this->params = &$params;
    $this->originalEndDateParam = CRM_Utils_Array::value('end_date', $this->params);
    $this->paymentContributionID = $contributionID;
    $this->recurContributionPreviousStatus = $recurContributionPreviousStatus;
  }

  /**
   * Preprocesses parameters used for Membership operations.
   */
  public function preProcess() {
    if ($this->paymentContributionID && $this->isOfflineNonPendingPaymentPlanMembership()) {
      $this->preventExtendingPaymentPlanMembership();
      $this->correctStartDateIfRenewingExpiredPaymentPlanMembership();
    }

    $isMultipleInstallmentsPaymentPlan = $this->isPaymentPlanWithMoreThanOneInstallment();
    $isMembershipRenewal = CRM_Utils_Request::retrieve('action', 'String') & CRM_Core_Action::RENEW;
    if ($isMembershipRenewal && $isMultipleInstallmentsPaymentPlan) {
      $this->extendPendingPaymentPlanMembershipOnRenewal();
    }

    if ($this->isOfflinePaymentPlanMembership()) {
      $this->verifyMembershipStartDate();
    }

    $this->updateMembershipPeriods();
  }

  /**
   * Prevents extending offline non pending payment plan Membership.
   *
   * If a membership price will be paid using
   * payment plan then each time an installment get
   * paid the membership will get extended.
   * For example if you have 12 installments for
   * a 1 year membership, then each time an
   * installment get paid the membership will get extended
   * by one year, this method prevent civicrm from doing that
   * so the membership gets only extended at completing
   * the first payment.
   */
  public function preventExtendingPaymentPlanMembership() {
    unset($this->params['end_date']);
  }

  /**
   * If we are renewing an expired membership
   * with payment plan, then the start date should
   * equal the join date.
   */
  private function correctStartDateIfRenewingExpiredPaymentPlanMembership() {
    if (empty($this->params['join_date']) || empty($this->params['start_date'])) {
      return;
    }

    $currentEndDateParam = CRM_Utils_Array::value('end_date', $this->params);
    if (!empty($this->originalEndDateParam) && $this->params['start_date'] > $currentEndDateParam) {
      $this->params['start_date'] = $this->params['join_date'];
    }
  }

  /**
   * Determines if the payment for a membership
   * subscription is offline (pay later), non pending and paid
   * as payment plan.
   *
   * @return bool
   */
  private function isOfflineNonPendingPaymentPlanMembership() {
    $recContributionID = $this->getPaymentRecurringContributionID();

    if ($recContributionID === NULL) {
      return FALSE;
    }

    return $this->isOfflineNonPendingPaymentPlanContribution($recContributionID);
  }

  /**
   * Determines if the recurring contribution
   * is offline (pay later) and is for
   * a payment plan.
   *
   * @param $recurringContributionID
   *
   * @return bool
   */
  private function isOfflineNonPendingPaymentPlanContribution($recurringContributionID) {
    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContributionID,
    ])['values'][0];

    $isPaymentPlanRecurringContribution = !empty($recurringContribution['installments']);
    $isOfflineContribution = ManualPaymentProcessors::isManualPaymentProcessor($recurringContribution['payment_processor_id']);

    $pendingContributionStatusId = array_search('Pending', CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name'));
    $isNonPending = !($recurringContribution['contribution_status_id'] == $pendingContributionStatusId);

    if (!empty($this->recurContributionPreviousStatus)) {
      $isNonPending = $isNonPending && ($this->recurContributionPreviousStatus !== 'Pending');
    }

    if ($isOfflineContribution && $isPaymentPlanRecurringContribution && $isNonPending) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the associated recurring contribution ID for
   * the membership payment(contribution) if it does exist.
   *
   * @return int|null
   *   The recurring contribution ID or NULL
   *   if no recurring contribution exist.
   */
  private function getPaymentRecurringContributionID() {
    $paymentContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $this->paymentContributionID,
      'return' => ['id', 'contribution_recur_id'],
    ]);

    if (empty($paymentContribution['values'][0]['contribution_recur_id'])) {
      return NULL;
    }

    return $paymentContribution['values'][0]['contribution_recur_id'];
  }

  /**
   * Update membership periods upon membership
   * edit which might result on updating existing
   * periods or creating new ones or both.
   */
  private function updateMembershipPeriods() {
    $membershipPeriodUpdate = new CRM_MembershipExtras_Hook_Pre_MembershipPeriodUpdater($this->id, $this->params, $this->paymentContributionID);
    $membershipPeriodUpdate->process();
  }

  private function isPaymentPlanWithMoreThanOneInstallment() {
    $installmentsCount = CRM_Utils_Request::retrieve('installments', 'Int');
    $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($isSavingContribution && $contributionIsPaymentPlan && $installmentsCount > 1) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   *
   * Extends the membership at renewal if the selected
   * payment status is pending.
   *
   * When renewing a membership through civicrm and selecting
   * the payment status as pending, then the membership will not
   * get extended unless you marked the first payment as complete,
   * So this method make sure it get extended without the need to
   * complete the first payment.
   */
  public function extendPendingPaymentPlanMembershipOnRenewal() {
    $pendingStatusValue =  civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
    $isPaymentPending = (CRM_Utils_Request::retrieve('contribution_status_id', 'String') === $pendingStatusValue);
    if (!$isPaymentPending) {
      return;
    }

    $this->params['end_date'] = MembershipEndDateCalculator::calculate($this->id);
  }

  /**
   * Checks if the start_date set in $params array is changed and if so, if it
   * makes sense within payment plan. If not, it corrects it.
   */
  private function verifyMembershipStartDate() {
    if (empty($this->params['start_date']) || $this->startDateSetInForm()) {
      return;
    }

    $membershipStartDate = $this->getCurrentMembershipStartDate();
    if ($this->params['start_date'] == $membershipStartDate) {
      return;
    }

    $this->params['start_date'] = $membershipStartDate;
  }

  /**
   * Checks if start_date has been sent on a form by checkin if the value is on
   * the request.
   *
   * @return bool
   */
  private function startDateSetInForm() {
    try {
      $startDateFromForm =  CRM_Utils_Request::retrieve('start_date', 'Date');
    } catch (CRM_Core_Exception $e) {
      return false;
    }

    if (empty($startDateFromForm)) {
      return false;
    }

    $formDate = new Date($startDateFromForm);
    $paramsDate = new Date($this->params['start_date']);

    if ($formDate === $paramsDate) {
      return true;
    }

    return false;
  }

  /**
   * Returns start_date of membership to be updated.
   *
   * @return string
   */
  private function getCurrentMembershipStartDate() {
    try {
      $membership = civicrm_api3('Membership', 'getsingle', [
        'sequential' => 1,
        'id' => $this->id,
      ]);
    } catch (Exception $e) {
      return '';
    }

    return $membership['start_date'];
  }

}
