<?php

class CRM_MembershipExtras_Helper_RecurringContributionHelper {

  /**
   * Checks if recurring contribution links to any membership.
   */
  public static function isRecurringContributionLinkToMembership($recurringContributionID) {
    $membershipCount = \Civi\Api4\Membership::get()
      ->selectRowCount()
      ->addWhere('contribution_recur_id', '=', $recurringContributionID)
      ->execute();

    if ($membershipCount->rowCount == 0) {
      return FALSE;
    }

    return TRUE;
  }

}
