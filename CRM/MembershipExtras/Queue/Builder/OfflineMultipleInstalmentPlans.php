<?php

use CRM_MembershipExtras_Queue_Builder_OfflinePaymentPlans as OfflinePaymentPlansQueueBuilder;
use CRM_MembershipExtras_Queue_Task_OfflineRenewMultipleInstalmentPlan as OfflineRenewMultipleInstalmentPlanTask;

/**
 * Adds payment plans with multiple instalments that are ready to
 * be renewed to the queue.
 */
class CRM_MembershipExtras_Queue_Builder_OfflineMultipleInstalmentPlans extends OfflinePaymentPlansQueueBuilder {

  protected $taskCallback = [OfflineRenewMultipleInstalmentPlanTask::class, 'run'];

  public function run() {
    $records = $this->getRecurringContributions();
    $this->buildQueue($records);
  }

  /**
   * Returns a list of payment plans with multiple instalments that have at
   * least one line item ready to be renewed (ie. has an end date, is not
   * removed and is set to auto renew), mmeting these conditions:
   *
   * 1- is using an offline payment processor (payment manual class).
   * 2- has an end date.
   * 3- is set to auto-renew
   * 4- is not in status cancelled
   * 5- "Next Payment Plan Period" is empty
   * 6- has either of the following conditions:
   *    - end date of at least one membership is equal to or smaller than today
   *    - there are no related line items with memberships to be renewed and
   *      line items have an end date
   *
   * @return array
   */
  private function getRecurringContributions() {
    $manualPaymentProcessorsIDs = implode(',', $this->manualPaymentProcessorIDs);
    $cancelledStatusID = $this->contributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $this->contributionStatusesNameMap['Refunded'];
    $daysToRenewInAdvance = $this->daysToRenewInAdvance;

    $query = "
      SELECT ccr.id as contribution_recur_id
        FROM civicrm_contribution_recur ccr
   LEFT JOIN membershipextras_subscription_line msl ON msl.contribution_recur_id = ccr.id
   LEFT JOIN civicrm_line_item cli ON msl.line_item_id = cli.id
   LEFT JOIN civicrm_membership cm ON (cm.id = cli.entity_id AND cli.entity_table = 'civicrm_membership')
   LEFT JOIN civicrm_value_payment_plan_periods ppp ON ppp.entity_id = ccr.id
       WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorsIDs}))
         AND ccr.installments > 1
         AND ccr.auto_renew = 1
         AND (
          ccr.contribution_status_id != {$cancelledStatusID}
          AND ccr.contribution_status_id != {$refundedStatusID}
         )
         AND (ppp.next_period IS NULL OR ppp.next_period = 0)
         AND msl.auto_renew = 1
         AND msl.is_removed = 0
    GROUP BY ccr.id
      HAVING MIN(cm.end_date) <= DATE_ADD(CURDATE(), INTERVAL {$daysToRenewInAdvance} DAY)
          OR (
            COUNT(cm.id) = 0
            AND COUNT(msl.id) > 0
          )
    ";
    $recurContributions = CRM_Core_DAO::executeQuery($query);

    $recurContributionIDs = [];
    while ($recurContributions->fetch()) {
      $recurContributionIDs[] = $recurContributions->contribution_recur_id;
    }

    return $recurContributionIDs;
  }

}
