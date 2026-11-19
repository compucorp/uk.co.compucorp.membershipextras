<?php

use CRM_MembershipExtras_Service_PaymentPlanStatusCalculator as PaymentPlanStatusCalculator;

/**
 * Corrects membership payment plans saved with status 0 (CIVIMM-593).
 *
 * On CiviCRM 6.x, Membership Extras looked plan statuses up in the
 * contribution status options, which have no "In Progress", and saved
 * plans recalculated as In Progress with status 0. Status 0 is not a valid
 * status, so only plans that bug wrote are touched: status 0, with at least
 * one contribution linked to a membership.
 *
 * Each gets the status Membership Extras would have written, from its own
 * contributions (test or live, as the plan is): see
 * PaymentPlanStatusCalculator::statusFromInstalmentCounts(). Only the status
 * column is changed, with SQL, so no hooks run and no other field (end date,
 * next scheduled date) changes. Running it again changes nothing.
 */
class CRM_MembershipExtras_Upgrader_Steps_Step0014 {

  public function apply() {
    $completedID = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Completed');
    $partiallyPaidID = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', 'Partially paid');

    $plans = CRM_Core_DAO::executeQuery("
      SELECT ccr.id, ccr.installments,
        (SELECT COUNT(c.id) FROM civicrm_contribution c WHERE c.contribution_recur_id = ccr.id AND c.contribution_status_id = %1) AS paid,
        (SELECT COUNT(c.id) FROM civicrm_contribution c WHERE c.contribution_recur_id = ccr.id AND c.contribution_status_id = %2) AS partially_paid
      FROM civicrm_contribution_recur ccr
      WHERE ccr.contribution_status_id = 0
        AND EXISTS (
          SELECT 1 FROM civicrm_contribution c
          INNER JOIN civicrm_membership_payment mp ON mp.contribution_id = c.id
          WHERE c.contribution_recur_id = ccr.id
        )
    ", [
      1 => [$completedID, 'Integer'],
      2 => [$partiallyPaidID, 'Integer'],
    ]);

    $corrected = [];
    while ($plans->fetch()) {
      $status = PaymentPlanStatusCalculator::statusFromInstalmentCounts((int) $plans->installments, (int) $plans->paid, (int) $plans->partially_paid);
      $statusID = (int) CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', $status);
      if (empty($statusID)) {
        continue;
      }

      CRM_Core_DAO::executeQuery('UPDATE civicrm_contribution_recur SET contribution_status_id = %1 WHERE id = %2 AND contribution_status_id = 0', [
        1 => [$statusID, 'Integer'],
        2 => [(int) $plans->id, 'Integer'],
      ]);
      $corrected[$status] = ($corrected[$status] ?? 0) + 1;
    }

    if (!empty($corrected)) {
      $summary = [];
      foreach ($corrected as $status => $count) {
        $summary[] = "$count to $status";
      }
      \Civi::log()->info('Membership Extras: corrected payment plans saved with status 0 (CIVIMM-593): ' . implode(', ', $summary) . '.');
    }

    return TRUE;
  }

}
