<?php

class CRM_MembershipExtras_Helper_RecurringContributionHelper {

  /**
   * Checks if recurring contribution links to any membership.
   */
  public static function isRecurringContributionLinkedToMembership($recurringContributionID) {
    $query = 'SELECT count(*) as count FROM civicrm_contribution c
      INNER JOIN civicrm_membership_payment mp ON c.id  = mp.contribution_id
      WHERE c.contribution_recur_id = %1
    ';
    $count = CRM_Core_DAO::singleValueQuery($query, [
      1 => [$recurringContributionID, 'Integer'],
    ]);

    if ($count == 0) {
      return FALSE;
    }

    return TRUE;
  }

}
