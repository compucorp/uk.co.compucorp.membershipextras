<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0009 {

  public function apply() {
    $this->setRecurContributionWithCycleDayGreaterThan28to28();
    $this->setRecurContributionWithNextContribDateDayGreaterThan28To28();
  }

  /**
   * Updates monthly recurrring_contribution with next_sched_contribution_date day greater than 28.
   *
   * Change next_sched_contribution_date day to 28 i.e. 2021-09-30 00:00:00 -> 2021-09-28 00:00:00
   */
  private function setRecurContributionWithNextContribDateDayGreaterThan28To28() {
    $query = "UPDATE civicrm_contribution_recur 
              SET next_sched_contribution_date = DATE(CONCAT_WS('-', year(next_sched_contribution_date), month(next_sched_contribution_date), 28)) 
              WHERE frequency_unit = 'month' AND day(next_sched_contribution_date) > 28";

    CRM_Core_DAO::executeQuery($query);
  }

  /**
   * Updates monthly recurrring_contribution with cycle_day greater than 28.
   *
   * Change cycle day to 28 Where
   * - Cycle day > 28
   * - frequency is "monthly" (i.e. for monthly or quarterly payment plans only)
   */
  private function setRecurContributionWithCycleDayGreaterThan28to28() {
    $query = "UPDATE `civicrm_contribution_recur` SET cycle_day = 28 
              WHERE frequency_unit = 'month' AND cycle_day > 28";

    CRM_Core_DAO::executeQuery($query);
  }

}
