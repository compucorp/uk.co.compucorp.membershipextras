<?php

/**
 * Utils and helper methods to handle offline recurring contribution for Membership.
 */
class CRM_PaymentPlan_Utils_Contribution {
  /**
   * Returns CRM_Contribute_BAO_ContributionRecur instance for specified
   * Membership ID or NULL if there is no Recurring Contribution found.
   *
   * @param int $membershipId
   *
   * @return CRM_Contribute_BAO_ContributionRecur|NULL
   */
  public static function getContributionRecurByMembershipId($membershipId) {
    $contribution = self::getContributionByMembershipId($membershipId);
    if (empty($contribution) || empty($contribution->contribution_recur_id)) {
      return NULL;
    }

    return CRM_Contribute_BAO_ContributionRecur::findById($contribution->contribution_recur_id);
  }

  /**
   * Returns CRM_Contribute_BAO_Contribution instance for specified
   * Membership ID or NULL if there is no Contribution found.
   *
   * @param int $membershipId
   *
   * @return CRM_Contribute_BAO_Contribution|NULL
   */
  public static function getContributionByMembershipId($membershipId) {
    $contributionId = CRM_Core_DAO::getFieldValue(
      'CRM_Member_DAO_MembershipPayment',
      $membershipId,
      'contribution_id',
      'membership_id'
    );
    if (empty($contributionId)) {
      return NULL;
    }

    return CRM_Contribute_BAO_Contribution::findById($contributionId);
  }

  /**
   * Returns TRUE if given Recurring Contribution meets the requirements
   * of being offline recurring contribution.
   * Otherwise returns FALSE.
   *
   * @param CRM_Contribute_BAO_ContributionRecur $recurringContribution
   *
   * @return boolean
   */
  public static function isOfflineRecurring($recurringContribution) {
    if (!empty($recurringContribution)) {
      if ($recurringContribution->installments > 1 &&
        (empty($recurringContribution->payment_processor_id) ||
          in_array($recurringContribution->payment_processor_id, self::getOfflineRecurringPaymentProcessors()))) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Gets the list of offline Recurring Payment Processors
   *
   * @return array
   */
  private static function getOfflineRecurringPaymentProcessors() {
    $offlineRecPaymentProcessors = civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'payment_processor_type_id' => 'Offline_Recurring_Contribution',
    ]);

    $recPaymentProcessors = [];
    if (!empty($offlineRecPaymentProcessors['values'])) {
      foreach ($offlineRecPaymentProcessors['values'] as $paymentProcessor) {
        $recPaymentProcessors[] = $paymentProcessor['id'];
      }
    }

    return $recPaymentProcessors;
  }

}
