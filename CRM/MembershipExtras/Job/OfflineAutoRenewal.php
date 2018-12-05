<?php

class CRM_MembershipExtras_Job_OfflineAutoRenewal {

  /**
   * Mapping between financial types IDs and Machine Names.
   *
   * @var array
   */
  private $financialTypesIDMap = [];

  /**
   * True if we should use the membership latest price
   * for renewal or false otherwise.
   *
   * @var bool
   */
  private $useMembershipLatestPrice = FALSE;

  /**
   * The option value "value" for the "pending"
   * contribution status.
   *
   * @var int
   */
  private $contributionPendingStatusValue;

  /**
   * Maps contribution status names to their corresponding ID's.
   *
   * @var array
   */
  private $contributionStatusesNameMap;

  /**
   * Number of days in advance a membership shuld be renewed.
   *
   * @var int
   */
  private $daysToRenewInAdvance;

  /**
   * ID's for payment processors that are considered to be manual.
   *
   * @var array
   */
  private $manualPaymentProcessorIDs;

  public function __construct() {
    $this->setFinancialTypesIDMap();
    $this->setUseMembershipLatestPrice();
    $this->setContributionPendingStatusValue();
    $this->setContributionStatusesNameMap();
    $this->setDaysToRenewInAdvance();
    $this->setManualPaymentProcessorIDs();
  }

  /**
   * Sets $financialTypesIDMap
   */
  private function setFinancialTypesIDMap() {
    $financialTypes = civicrm_api3('FinancialType', 'get', [
      'sequential' => 1,
      'return' => ['id', 'name'],
      'options' => ['limit' => 0],
    ])['values'];

    foreach ($financialTypes as $type) {
      $this->financialTypesIDMap[$type['id']] = $type['name'];
    }
  }

  /**
   * Sets $useMembershipLatestPrice
   */
  private function setUseMembershipLatestPrice() {
    $settingFieldName = 'membershipextras_paymentplan_use_membership_latest_price';
    $useMembershipLatestPrice = civicrm_api3('Setting', 'get', [
      'sequential' => 1,
      'return' => [$settingFieldName],
    ]);

    if (!empty($useMembershipLatestPrice['values'][0][$settingFieldName])) {
      $this->useMembershipLatestPrice = TRUE;
    }
  }

  /**
   * Sets $currentRecurContribution
   */
  private function setContributionPendingStatusValue() {
    $this->contributionPendingStatusValue =  civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  /**
   * Gets contribution Statuses Name to value Mapping
   *
   * @return array $contributionStatusesNameMap
   */
  private function setContributionStatusesNameMap() {
    $contributionStatuses = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ['name', 'value'],
      'option_group_id' => 'contribution_status',
      'options' => ['limit' => 0],
    ])['values'];

    $contributionStatusesNameMap = [];
    foreach ($contributionStatuses as $status) {
      $contributionStatusesNameMap[$status['name']] = $status['value'];
    }

    $this->contributionStatusesNameMap = $contributionStatusesNameMap;
  }

  /**
   * Loads setting and assigns it to a class attribute.
   */
  private function setDaysToRenewInAdvance() {
    $this->daysToRenewInAdvance = CRM_MembershipExtras_SettingsManager::getDaysToRenewInAdvance();
  }

  /**
   * Loads list of manual payment processors into an array as a class attribute.
   */
  private function setManualPaymentProcessorIDs() {
    $payLaterProcessorID = 0;
    $this->manualPaymentProcessorIDs = array_merge([$payLaterProcessorID], CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs());
  }

  /**
   * Starts the scheduled job for renewing offline
   * auto-renewal memberships.
   *
   * @return True
   *
   * @throws \Exception
   */
  public function run() {
    $multipleInstallmentPaymentPlans = $this->getMultipleInstallmentRenewablePaymentPlans();
    foreach ($multipleInstallmentPaymentPlans as $recurContribution) {
      $renewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstallmentPlan($recurContribution['contribution_recur_id']);
      $renewal->setContributionPendingStatusValue($this->contributionPendingStatusValue);
      $renewal->setUseMembershipLatestPrice($this->useMembershipLatestPrice);
      $renewal->run();
    }

    $singleInstallmentPaymentPlans = $this->getSingleInstallmentRenewablePaymentPlans();
    foreach ($singleInstallmentPaymentPlans as $recurContribution) {
      $renewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlan($recurContribution['contribution_recur_id']);
      $renewal->setContributionPendingStatusValue($this->contributionPendingStatusValue);
      $renewal->setUseMembershipLatestPrice($this->useMembershipLatestPrice);
      $renewal->run();
    }

    return TRUE;
  }

  /**
   * Returns a list of payment plans with multiple installments that have at
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
  private function getMultipleInstallmentRenewablePaymentPlans() {
    $manualPaymentProcessorsIDs = implode(',', $this->manualPaymentProcessorIDs);
    $cancelledStatusID = $this->contributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $this->contributionStatusesNameMap['Refunded'];
    $daysToRenewInAdvance = $this->daysToRenewInAdvance;

    $query = "
      SELECT ccr.id as contribution_recur_id, ccr.installments 
        FROM civicrm_contribution_recur ccr
   LEFT JOIN membershipextras_subscription_line msl ON msl.contribution_recur_id = ccr.id
   LEFT JOIN civicrm_line_item cli ON msl.line_item_id = cli.id
   LEFT JOIN civicrm_membership cm ON (cm.id = cli.entity_id AND cli.entity_table = 'civicrm_membership')
   LEFT JOIN civicrm_value_payment_plan_periods ppp ON ppp.entity_id = ccr.id
       WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorsIDs}))
         AND ccr.installments > 1
         AND ccr.end_date IS NOT NULL
         AND ccr.auto_renew = 1 
         AND (
          ccr.contribution_status_id != {$cancelledStatusID} 
          AND ccr.contribution_status_id != {$refundedStatusID}
         )
         AND ppp.next_period IS NULL
         AND msl.auto_renew = 1
         AND msl.is_removed = 0
         AND msl.end_date IS NOT NULL
    GROUP BY ccr.id
      HAVING MIN(cm.end_date) <= DATE_ADD(CURDATE(), INTERVAL {$daysToRenewInAdvance} DAY)
          OR (
            COUNT(cm.id) = 0
            AND COUNT(msl.id) > 0
          )
    ";
    $recurContributions = CRM_Core_DAO::executeQuery($query);

    $recurContributionsList = [];
    while ($recurContributions->fetch()) {
      $recurContribution['contribution_recur_id'] = $recurContributions->contribution_recur_id;
      $recurContribution['installments'] = $recurContributions->installments;
      $recurContributionsList[] = $recurContribution;
    }

    return $recurContributionsList;
  }

  /**
   * Obtains list of payment plans with a single installment that are ready to
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
  private function getSingleInstallmentRenewablePaymentPlans() {
    $manualPaymentProcessorsIDs = implode(',', $this->manualPaymentProcessorIDs);
    $cancelledStatusID = $this->contributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $this->contributionStatusesNameMap['Refunded'];
    $daysToRenewInAdvance = $this->daysToRenewInAdvance;

    $query = "
      SELECT ccr.id as contribution_recur_id, ccr.installments,
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

    $recurContributionsList = [];
    while ($recurContributions->fetch()) {
      $recurContribution['contribution_recur_id'] = $recurContributions->contribution_recur_id;
      $recurContribution['installments'] = $recurContributions->installments;
      $recurContributionsList[] = $recurContribution;
    }

    return $recurContributionsList;
  }

}
