<?php

class CRM_MembershipExtras_WebformAPI_PaymentPlanActivation {

  /**
   * Given a recuring contribution, this will
   * look for any other related recurring contribution
   * related to add, deactivate them and only
   * set the most recent one to active.
   *
   * @param int $contributionRecurId
   */
  public static function activateOnlyLast($contributionRecurId) {
    $relatedMembershipIds = self::getRelatedMemberships($contributionRecurId);
    foreach ($relatedMembershipIds as $relatedMembershipId) {
      $paymentPlanActivationService = new CRM_MembershipExtras_Service_MembershipPaymentPlanActivation();
      $paymentPlanActivationService->activateMembershipCurrentPaymentPlan($relatedMembershipId);
    }
  }

  /**
   * Gets all the memberships
   * related to the given recurring contribution.
   *
   * @param int $contributionRecurId
   *
   * @return array
   */
  private static function getRelatedMemberships($contributionRecurId) {
    $query = '
      SELECT cmp.membership_id as id from civicrm_membership_payment cmp 
      INNER JOIN civicrm_contribution cc ON cmp.contribution_id = cc.id 
      INNER JOIN civicrm_contribution_recur ccr ON cc.contribution_recur_id = ccr.id 
      WHERE ccr.id = %1 
      GROUP BY cmp.membership_id 
      ORDER BY cmp.membership_id ASC 
    ';
    $results = CRM_Core_DAO::executeQuery($query, [
      1 => [$contributionRecurId, 'Integer'],
    ]);

    $ids = [];
    while ($results->fetch()) {
      $ids[] = $results->id;
    }

    return $ids;
  }

}
