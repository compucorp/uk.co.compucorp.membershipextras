<?php

/**
 * Utils and helper methods to handle offline recurring contribution for Membership.
 */
class CRM_Membership_Utils {
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
   * @TODO: This method should be changed after we implement offline payment
   * processor. Currently we rely on NULL value of payment processor ID.
   *
   * @param CRM_Contribute_BAO_ContributionRecur $recurringContribution
   *
   * @return boolean
   */
  public static function isOfflineRecurring($recurringContribution) {
    if (!empty($recurringContribution) && $recurringContribution->installments > 1 && empty($recurringContribution->payment_processor_id)) {
      return TRUE;
    }

    return FALSE;
  }
}
