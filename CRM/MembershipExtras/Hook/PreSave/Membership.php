<?php

class CRM_MembershipExtras_Hook_PreSave_Membership {

  /**
   * @var int|null
   */
  private static $contributionId = NULL;

  /**
   * The membership to be created object
   * passed form the hook
   *
   * @var CRM_Member_DAO_Membership|NULL
   */
  private $membership;

  public function __construct($membership = NULL) {
  $this->membership = $membership;
  }

  /**
   * Sets the membership contribution (payment) ID.
   * Used if one of the payment plan membership installments
   * is paid or modified.
   *
   * @param $id
   */
  public function setContributionId($id) {
    self::$contributionId = $id;
  }

  public function preventCancellationOnInstallmentCancellation() {
    if (empty(self::$contributionId)) {
      return;
    }

    if ($this->isOfflinePaymentPlanMembership() && $this->isMembershipToBeCancelled()) {
      $fieldsToUnset = ['is_override', 'status_override_end_date', 'status_id'];
      foreach ($fieldsToUnset as $field) {
        if (property_exists($this->membership, $field)) {
          unset($this->membership->$field);
        }
      }
    }
  }

  /**
   * Determines if the payment for a membership
   * subscription is offline (pay later) and paid
   * as payment plan.
   *
   * @return bool
   */
  private function isOfflinePaymentPlanMembership() {
    $recContributionID = $this->getPaymentRecurringContributionID();

    if ($recContributionID === NULL) {
      return FALSE;
    }

    return $this->isOfflinePaymentPlanContribution($recContributionID);
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
      'id' => self::$contributionId,
      'return' => ['id', 'contribution_recur_id'],
    ]);

    if (empty($paymentContribution['values'][0]['contribution_recur_id'])) {
      return NULL;
    }

    return $paymentContribution['values'][0]['contribution_recur_id'];
  }

  /**
   * Determines if the recurring contribution
   * is offline (pay later) and is for
   * a payment plan.
   *
   * @param $recurringContributionID
   * @return bool
   */
  private function isOfflinePaymentPlanContribution($recurringContributionID) {
    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContributionID,
    ])['values'][0];

    $isPaymentPlanRecurringContribution = !empty($recurringContribution['installments']);

    $manualPaymentProcessors = CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs();
    $isOfflineContribution = empty($recurringContribution['payment_processor_id']) ||
      in_array($recurringContribution['payment_processor_id'], $manualPaymentProcessors);

    if ($isOfflineContribution && $isPaymentPlanRecurringContribution) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Determines if the membership is updated
   * to be cancelled or not.
   *
   * @return bool
   */
  private function isMembershipToBeCancelled() {
    $membershipStatuses = CRM_Member_BAO_Membership::buildOptions('status_id', 'get');
    if ($membershipStatuses[$this->membership->status_id] == 'Cancelled') {
      return TRUE;
    }

    return FALSE;
  }

}
