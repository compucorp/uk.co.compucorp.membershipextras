<?php

use CRM_MembershipExtras_Service_ContributionUtilities as ContributionUtilities;
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_Job_OverdueMembershipPeriodProcessor {

  /**
   * Starts the scheduled job for disabling overdue membership
   * periods
   * 
   * @return True
   * 
   * @throws \Exception
   */
  public function run() {
    $autoUpdateOverdueMembershipEnabled = CRM_MembershipExtras_SettingsManager::shouldUpdateOverdueMembershipPeriod();
    if (!$autoUpdateOverdueMembershipEnabled) {
      return TRUE;
    }

    $membershipPeriodDao = $this->getOverdueMembershipPeriods();
    $errors = [];
    while ($membershipPeriodDao->fetch()) {
      try {
        $this->takeActionOnOverdueMembershipPeriods($membershipPeriodDao->membership_period_id);
      } catch (Exception $e) {
        $errors[] = E::ts("An error occurred disabling an overdue membership period with id({$membershipPeriodDao->membership_period_id}): {$e->getMessage()}");
      }

      if (!empty($errors)) {
        throw new Exception("Errors found while processing periods: " . implode('; ', $errors));
      }
    }

    return TRUE;
  }

  /**
   * Construct query to get overdue membership periods.
   * An overdue membership period is a membership period with one or more
   * overdue payments.
   *
   * @return CRM_Core_DAO
   *  Object that point to result set of IDs of overdue membership periods
   */
  private function getOverdueMembershipPeriods() {
    $contributionStatusesNameMap = ContributionUtilities::getStatusesNameMap();
    $completedContributionStatusID = $contributionStatusesNameMap['Completed'];

    $daysToDisableMP = CRM_MembershipExtras_SettingsManager::getDaysToUpdateOverdueMembershipPeriods();
    $date = new DateTime();
    $date->sub(new DateInterval("P{$daysToDisableMP}D"));
    $maxReceiveDate = $date->format('Y-m-d H:i:s');

    $query = "(
      SELECT mmp.id as membership_period_id
        FROM membershipextras_membership_period mmp
          INNER JOIN civicrm_contribution cc ON (
            mmp.entity_id = cc.id
            AND mmp.payment_entity_table = 'civicrm_contribution'
          )
        WHERE cc.receive_date <= '{$maxReceiveDate}'
          AND cc.contribution_status_id != {$completedContributionStatusID}
          AND mmp.is_active = 1
        GROUP BY membership_period_id
    ) UNION (
      SELECT mmp.id as membership_period_id
        FROM membershipextras_membership_period mmp
          INNER JOIN civicrm_contribution_recur ccr ON (
            mmp.entity_id = ccr.id
            AND mmp.payment_entity_table = 'civicrm_contribution_recur'
          )
          INNER JOIN civicrm_contribution cc ON ccr.id = cc.contribution_recur_id
        WHERE cc.receive_date <= '{$maxReceiveDate}'
          AND ccr.contribution_status_id != {$completedContributionStatusID}
          AND mmp.is_active = 1
        GROUP BY membership_period_id
    )";

    return CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Determine action to take on overdue membership periods.
   * 
   * @param int $membershipPeriodID 
   */
  private function takeActionOnOverdueMembershipPeriods($membershipPeriodID) {
    $actionToTake = CRM_MembershipExtras_SettingsManager::getActionToTakeOnOverdueMembershipPeriods();
    switch ($actionToTake) {
      case 1:
        $this->deactivateMembershipPeriod($membershipPeriodID);
        break;
      case 2:
        $this->updateMembershipPeriodEndDate($membershipPeriodID);
      case 0:
      default:
        break;
    }
  }

  /**
   * Deactivates a membership period
   *
   * @param int $membershipPeriodID 
   */
  private function deactivateMembershipPeriod($membershipPeriodID) {
    $transaction = new CRM_Core_Transaction();
    try {
      CRM_MembershipExtras_BAO_MembershipPeriod::updatePeriod([
        'id' => $membershipPeriodID,
        'is_active' => 0,
      ]);
    } catch (Exception $exception) {
      $transaction->rollback();
      $message = E::ts("An error occurred updating membership period with id({$membershipPeriodID}): " . $exception->getMessage());

      throw new Exception($message);
    }

    $transaction->commit();
  }

  /**
   * Updates a membership period's end date
   *
   * @param int $membershipPeriodID
   */
  private function updateMembershipPeriodEndDate($membershipPeriodID) {
    $preferredEndDate = CRM_MembershipExtras_SettingsManager::getEndDatePreferredForOverdueMembershipPeriods();
    $offset = CRM_MembershipExtras_SettingsManager::getOffsetToPadOverdueMembershipPeriodsEndDate();
    $newEndDate = new DateTime();
    if ($preferredEndDate == 1) {
      $membershipPeriod = CRM_MembershipExtras_BAO_MembershipPeriod::getMembershipPeriodById($membershipPeriodID);
      if ($membershipPeriod == NULL) {
        return;
      }

      $lastPayment = CRM_MembershipExtras_BAO_MembershipPeriod::getMembershipLastContribution($membershipPeriod->membership_id);
      $newEndDate = $newEndDate->modify($lastPayment['receive_date']);
    }
    $newEndDate->add(new DateInterval("P{$offset}D"));

    $transaction = new CRM_Core_Transaction();
    try {
      CRM_MembershipExtras_BAO_MembershipPeriod::updatePeriod([
        'id' => $membershipPeriodID,
        'end_date' => $newEndDate,
      ]);
    } catch (Exception $exception) {
      $transaction->rollback();
      $message = E::ts("An error occurred updating membership period with id({$membershipPeriodID}): " . $exception->getMessage());

      throw new Exception($message);
    }

    $transaction->commit();
  }
}
