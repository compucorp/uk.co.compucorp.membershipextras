<?php

use CRM_MembershipExtras_SettingsManager as SettingsManager;
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
   * The membership payment contribution in case the membership edit is triggeed
   * by completing a contribution.
   * this holds the contribution details at the moment of completing it but
   * before its status and the payment relevant details are changed.
   *
   * @var array
   */
  private $paymentContributionPreviousParams;

  private $recurContributionPreviousStatus;

  private $originalEndDateParam;

  public function __construct($id, &$params, $contributionPreviousParams, $recurContributionPreviousStatus) {
    $this->id = $id;
    $this->params = &$params;
    $this->originalEndDateParam = CRM_Utils_Array::value('end_date', $this->params);
    $this->paymentContributionPreviousParams = $contributionPreviousParams;
    $this->recurContributionPreviousStatus = $recurContributionPreviousStatus;
  }

  /**
   * Preprocesses parameters used for Membership operations.
   */
  public function preProcess() {
    if ($this->paymentContributionPreviousParams) {
      if ($this->isOfflineNonPendingPaymentPlanMembership()) {
        $this->preventExtendingPaymentPlanMembership();
        $this->correctStartDateIfRenewingExpiredPaymentPlanMembership();
      }

      $this->preventExtendingAlreadyActiveAndExtendedMembership();
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
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getPaymentRecurringContributionID() {
    $paymentContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $this->paymentContributionPreviousParams['id'],
      'return' => ['id', 'contribution_recur_id'],
    ]);

    if (empty($paymentContribution['values'][0]['contribution_recur_id'])) {
      return NULL;
    }

    return $paymentContribution['values'][0]['contribution_recur_id'];
  }

  /**
   * If a membership is created/renewed by a "payment method"
   * that should automatically activate the membership. then completing
   * any pending payment should not extend the membership dates since it should
   * be already extended at the time of creating/renewal.
   * This method ensure that the extending of the membership should not happen.
   */
  private function preventExtendingAlreadyActiveAndExtendedMembership() {
    $contributionCurrentParams = $this->getContributionCurrentParams();
    $contributionCurrentStatus = $contributionCurrentParams['contribution_status'];

    $contributionPreviousStatus = $this->paymentContributionPreviousParams['contribution_status'];

    $isCompletingPendingContribution = in_array($contributionPreviousStatus, [
        'Pending',
        'Partially paid',
      ]) && $contributionCurrentStatus == 'Completed';

    $paymentMethodsThatAlwaysActivateMemberships = SettingsManager::getPaymentMethodsThatAlwaysActivateMemberships();
    $isPaymentMethodAlwaysActivate = in_array($this->paymentContributionPreviousParams['payment_instrument_id'], $paymentMethodsThatAlwaysActivateMemberships);

    if ($isCompletingPendingContribution && $isPaymentMethodAlwaysActivate) {
      unset($this->params['end_date']);
    }

  }

  /**
   * The current details of the completed contribution
   * which is after its status and the other payment details
   * are changed by the payment made.
   *
   * @return array
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getContributionCurrentParams() {
    $contributionParamsResponse = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $this->paymentContributionPreviousParams['id'],
    ]);

    $contributionParams = [];
    if (!empty($contributionParamsResponse['values'][0])) {
      $contributionParams = $contributionParamsResponse['values'][0];
    }

    return $contributionParams;
  }

  /**
   * Update membership periods upon membership
   * edit which might result on updating existing
   * periods or creating new ones or both.
   */
  private function updateMembershipPeriods() {
    $membershipPeriodUpdate = new CRM_MembershipExtras_Hook_Pre_MembershipPeriodUpdater($this->id, $this->params, $this->paymentContributionPreviousParams['id']);
    $membershipPeriodUpdate->process();
  }

}
