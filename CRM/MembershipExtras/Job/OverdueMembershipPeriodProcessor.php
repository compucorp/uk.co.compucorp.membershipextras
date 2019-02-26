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
    
    while ($membershipPeriodDao->fetch()) {
      try {
        $this->disableMembershipPeriod($membershipPeriodDao->membership_period_id);
      } catch (Exception $e) {
        $message = "An error occurred disabling an overdue membership period with id({$membershipPeriodDao->membership_period_id}): " . $e->getMessage();

        throw new Exception($message);
      }
    }

    return TRUE;
  }

  /**
   * @return CRM_Core_DAO
   *  Object that point to result set of IDs of overdue membership periods
   */
  private function getMembershipsPeriodsWithOverduePayment() {
    $contributionStatusesNameMap = ContributionUtilities::getContributionStatusesNameMap();
    $completedContributionStatusID = $contributionStatusesNameMap['Completed'];
    
    $daysToDisableMP = CRM_MembershipExtras_SettingsManager::getDaysToDisableMembershipPeriodsWithOverduePayment();
    $dateNow = (new \DateTime())->format('Y-m-d H:i:s');
    $daysAgo = date('Y-m-d H:i:s', strtotime("-$daysToDisableMP days", strtotime($dateNow)));

    $query = "
      SELECT mmp.id as membership_period_id
        FROM membershipextras_membership_period mmp
      LEFT JOIN civicrm_membership cm ON mmp.membership_id = cm.id
      LEFT JOIN civicrm_membership_payment cmp ON mmp.membership_id = cmp.membership_id
      LEFT JOIN civicrm_contribution cc ON cmp.contribution_id = cc.id
        WHERE cc.receive_date <= '$daysAgo'
         AND cc.contribution_status_id != $completedContributionStatusID
         AND mmp.is_active = 1
      GROUP BY membership_period_id
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
