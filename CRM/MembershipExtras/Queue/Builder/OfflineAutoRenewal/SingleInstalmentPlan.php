<?php

use CRM_MembershipExtras_Queue_Builder_OfflineAutoRenewal_PaymentPlan as OfflineAutoRenewalPaymentPlanQueueBuilder;
use CRM_MembershipExtras_Queue_Task_OfflineAutoRenewal_RenewSingleInstalmentPlans as OfflineRenewSingleInstalmentPlansTask;

/**
 * Adds payment plans with a single instalment that are ready to
 * be renewed to the queue.
 */
class CRM_MembershipExtras_Queue_Builder_OfflineAutoRenewal_SingleInstalmentPlan extends OfflineAutoRenewalPaymentPlanQueueBuilder {

  protected $taskCallback = [OfflineRenewSingleInstalmentPlansTask::class, 'run'];

  public function run() {
    $records = $this->getRecurringContributions();
    $this->buildQueue($records);
  }

  /**
   * Obtains list of payment plans with a single instalment that are ready to
   * be renewed. This means:
   *
   * 1- Recurring contribution is a manual payment plan
   * 2- Recurring contribution is set to auto-renew.
   * 3- Recurring contribution has no end date.
   * 4- Recurring contribution is not cancelled nor complete.
   * 5- Recurring contribution has either:
   *   - At least one auto-renew, un-removed line item for a membership with an
   *     end date before today + $daysToRenewInAdvance.
   *   - At least one auto-renew, un-removed line item and NO memberships, and
   *     next_run_date(*) is before today + $daysToRenewInAdvance.
   *
   * (*) next_run_date corresponds to either maximum end date of all line items
   * related to the recurring contribution + 1 period, or the start date of the
   * recurring contribution + 1 period, if there are no line items with end
   * dates.
   *
   * @return array
   */
  protected function getRecurringContributions() {
    $manualPaymentProcessorsIDs = implode(',', $this->manualPaymentProcessorIDs);
    $cancelledStatusID = $this->contributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $this->contributionStatusesNameMap['Refunded'];
    $daysToRenewInAdvance = $this->daysToRenewInAdvance;

    $query = "
      SELECT ccr.id as contribution_recur_id,
        CASE
          WHEN frequency_unit = 'day' THEN DATE_ADD(
            CASE
              WHEN MAX(msl.end_date) IS NULL THEN ccr.start_date
              ELSE MAX(msl.end_date)
            END,
            INTERVAL frequency_interval DAY
          )
          WHEN frequency_unit = 'week' THEN DATE_ADD(
            CASE
              WHEN MAX(msl.end_date) IS NULL THEN ccr.start_date
              ELSE MAX(msl.end_date)
            END,
            INTERVAL frequency_interval WEEK
          )
          WHEN frequency_unit = 'month' THEN DATE_ADD(
            CASE
              WHEN MAX(msl.end_date) IS NULL THEN ccr.start_date
              ELSE MAX(msl.end_date)
            END,
            INTERVAL frequency_interval MONTH
          )
          WHEN frequency_unit = 'year' THEN DATE_ADD(
            CASE
              WHEN MAX(msl.end_date) IS NULL THEN ccr.start_date
              ELSE MAX(msl.end_date)
            END,
            INTERVAL frequency_interval YEAR
          )
        END AS next_run_date
        FROM civicrm_contribution_recur ccr
   LEFT JOIN membershipextras_subscription_line msl ON msl.contribution_recur_id = ccr.id
   LEFT JOIN civicrm_line_item cli ON msl.line_item_id = cli.id
   LEFT JOIN civicrm_membership cm ON (cm.id = cli.entity_id AND cli.entity_table = 'civicrm_membership')
   LEFT JOIN civicrm_value_payment_plan_periods ppp ON ppp.entity_id = ccr.id
       WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorsIDs}))
         AND ccr.end_date IS NULL
         AND (
          ccr.installments < 2
          OR ccr.installments IS NULL
         )
         AND ccr.auto_renew = 1
         AND (
          ccr.contribution_status_id != {$cancelledStatusID}
          AND ccr.contribution_status_id != {$refundedStatusID}
         )
         AND ppp.next_period IS NULL
         AND msl.auto_renew = 1
         AND msl.is_removed = 0
         AND msl.end_date IS NULL

    GROUP BY ccr.id
      HAVING MIN(cm.end_date) <= DATE_ADD(CURDATE(), INTERVAL {$daysToRenewInAdvance} DAY)
      OR (
        COUNT(cm.id) = 0
        AND next_run_date <= DATE_ADD(CURDATE(), INTERVAL {$daysToRenewInAdvance} DAY)
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
