<?php

use CRM_MembershipExtras_Service_MembershipEndDateCalculator as MembershipEndDateCalculator;

/**
 * Called by membershipextras_civicrm_pre hook
 * before editing/renewing a membership
 * record.
 *
 * Class CRM_MembershipExtras_Hook_PreEdit_Membership
 */
class CRM_MembershipExtras_Hook_PreEdit_Membership {

  /**
   * The membership ID.
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

  /**
   * The other membership parameters passed by the calling hook.
   *
   * @var array
   */
  private $params;

  public function __construct($id, $paymentContributionID, &$params) {
    $this->id = $id;
    $this->paymentContributionID = $paymentContributionID;
    $this->params = &$params;
  }

  /**
   * Prevents extending offline pending recurring Membership.
   *
   * If a membership price will be paid in multiple
   * installments, then each time an installment get
   * paid then the membership will get extended.
   * For example if you have 12 installments for
   * 120 USD - 1 year membership, then each time an
   * installment get paid the membership will get extended
   * by one year (it is how civicrm work!), this method
   * prevent civicrm from doing that.
   */
  public function preventExtendingOfflinePendingRecurringMembership() {
    if ($this->isOfflinePendingRecurringMembership()) {
      unset($this->params['end_date']);
    }
  }

  /**
   * Determines if the payment for a membership
   * subscription is offline (pay later), pending and recurring.
   *
   * @return bool
   */
  private function isOfflinePendingRecurringMembership() {
    $recContributionID = $this->getPaymentRecurringContribution();

    if ($recContributionID === NULL) {
      return FALSE;
    }

    return $this->isOfflinePendingRecurringContribution($recContributionID);
  }

  /**
   * Gets the associated recurring contribution for
   * the membership payment(contribution)
   * if it does exist.
   *
   * @return int|null
   *   The recurring contribution ID or NULL
   *   if no recurring contribution exist.
   */
  private function getPaymentRecurringContribution() {
    $paymentContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $this->paymentContributionID,
      'return' => ['id', 'contribution_recur_id'],
    ]);

    if (empty($paymentContribution['id'])) {
      return NULL;
    }

    $paymentContribution= $paymentContribution['values'][0];

    if (empty($paymentContribution['contribution_recur_id'])) {
      return NULL;
    }

    return $paymentContribution['contribution_recur_id'];
  }

  /**
   * Determines if the recurring
   * contribution is offline (pay later) and pending.
   *
   * @param $recurringContributionID
   * @return bool
   */
  private function isOfflinePendingRecurringContribution($recurringContributionID) {
    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContributionID,
    ])['values'][0];

    $installmentsCount = $recurringContribution['installments'];
    $completedInstallmentsCount = $this->getRecContributionCompletedInstallmentsCount($recurringContributionID);

    $isTherePendingInstallments = $completedInstallmentsCount !== $installmentsCount;

    $manualPaymentProcessors = CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs();
    $isOfflineContribution = empty($recurringContribution['payment_processor_id']) ||
      in_array($recurringContribution['payment_processor_id'], $manualPaymentProcessors);

    if ($isTherePendingInstallments && $isOfflineContribution) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the count of completed (paid) installments
   * for the specified recurring contribution.
   *
   * @param $recContributionID
   *
   * @return int
   */
  private function getRecContributionCompletedInstallmentsCount($recContributionID) {
    $pendingContributions = civicrm_api3('Contribution', 'get', [
      'contribution_recur_id' => $recContributionID,
      'contribution_status_id' => 'Completed',
    ]);

    return $pendingContributions['count'];
  }

  /**
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

}
