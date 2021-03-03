<?php

/**
 * Class CRM_MembershipExtras_Service_MembershipPaymentPlanActivation
 *
 * This service ensures that only the last recurring
 * contribution is set as active for the given membership.
 */
class CRM_MembershipExtras_Service_MembershipPaymentPlanActivation {

  /**
   * Activates the current (and latest)
   * payment plan associated with the given
   * membership and ensures the rest
   * are deactivated.
   *
   * @param int $membershipId
   */
  public function activateMembershipCurrentPaymentPlan($membershipId) {
    $membershipRelatedRecurContributionsIds = $this->getAllMembershipRelatedRecurContributions($membershipId);
    $this->activateOnlyLastRecurContribution($membershipRelatedRecurContributionsIds);
  }

  /**
   * Gets all the recurring contributions
   * that were at any point related to the
   * membership.
   *
   *
   * @return array
   */
  private function getAllMembershipRelatedRecurContributions($membershipId) {
    $query = '
      SELECT cc.contribution_recur_id as id from civicrm_membership_payment cmp 
      INNER JOIN civicrm_contribution cc ON cmp.contribution_id = cc.id 
      WHERE cmp.membership_id = %1 
      GROUP BY cc.contribution_recur_id 
      ORDER BY cc.contribution_recur_id ASC 
    ';
    $results = CRM_Core_DAO::executeQuery($query, [
      1 => [$membershipId, 'Integer'],
    ]);

    $ids = [];
    while ($results->fetch()) {
      $ids[] = $results->id;
    }

    return $ids;
  }

  /**
   * Activates the latest recurring contribution
   * from a given list and deactivate the rest.
   *
   * @param array $recurContributionsIds
   */
  private function activateOnlyLastRecurContribution($recurContributionsIds) {
    if (!empty($recurContributionsIds)) {
      $formattedRecurIds = '(' . implode(',', $recurContributionsIds) . ')';

      $deactivationQuery = "
      UPDATE civicrm_value_payment_plan_extra_attributes    
      SET is_active = 0 WHERE entity_id IN $formattedRecurIds
    ";
      CRM_Core_DAO::executeQuery($deactivationQuery);
    }

    $lastRecurContributionId = end($recurContributionsIds);
    $activationQuery = "
      INSERT INTO civicrm_value_payment_plan_extra_attributes    
      (entity_id, is_active) VALUES ({$lastRecurContributionId}, 1) 
      ON DUPLICATE KEY UPDATE is_active = 1 
     ";
    CRM_Core_DAO::executeQuery($activationQuery);
  }

}
