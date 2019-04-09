<?php

use CRM_MembershipExtras_Service_ContributionUtilities as ContributionUtilities;

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
    $membershipPeriodDao = $this->getMembershipsPeriodsWithOverduePayment();
    $errors = [];
    while ($membershipPeriodDao->fetch()) {
      try {
        $this->disableMembershipPeriod($membershipPeriodDao->membership_period_id);
      } catch (Exception $e) {
        $errors[] = "An error occurred disabling an overdue membership period with id({$membershipPeriodDao->membership_period_id}): " . $e->getMessage();
      }

      if (count($errors) > 0) {
        throw new Exception("Errors found while processing periods: " . implode('; ', $errors));
      }
    }

    return TRUE;
  }

  /**
   * @return CRM_Core_DAO
   *  Object that point to result set of IDs of overdue membership periods
   */
  private function getMembershipsPeriodsWithOverduePayment() {
    $contributionStatusesNameMap = ContributionUtilities::getStatusesNameMap();
    $completedContributionStatusID = $contributionStatusesNameMap['Completed'];
    
    $daysToDisableMP = CRM_MembershipExtras_SettingsManager::getDaysToDisableMembershipPeriodsWithOverduePayment();
    $date = new DateTime();
    $date->sub(new DateInterval("P{$daysToDisableMP}D"));
    $maxReceiveDate = $date->format('Y-m-d H:i:s');

    $query = "
    (
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
    )
    ";

    return CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Disables a membership period
   * 
   * @param int $membershipPeriodID 
   */
  private function disableMembershipPeriod($membershipPeriodID) {
    $membershipPeriodBao = new CRM_MembershipExtras_BAO_MembershipPeriod();
    $membershipPeriodBao->id = $membershipPeriodID;
    $membershipPeriodBao->is_active = 0;
    $membershipPeriodBao->save();
  }
}
