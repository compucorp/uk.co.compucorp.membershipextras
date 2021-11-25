<?php

class CRM_MembershipExtras_Service_PaymentPlanNextContributionDate {

  /**
   * @var int
   */
  private $recurContributionID;

  public function __construct($recurContributionID) {
    $this->recurContributionID = $recurContributionID;
  }

  /**
   * Calculates and Updates the recurring contribution `Next scheduled contribution date'.
   *
   * The `Next scheduled contribution date' will be used to control autorenewal,
   * so we here update its value according to the following:
   *  - Monthly payment plan: +1 month from the last contribution receive date.
   *  - Quarterly: +3 months from the last contribution receive date.
   *  - Yearly: +1 year from the last contribution receive date
   */
  public function calculateAndUpdate() {
    $nextContributionDate = $this->calculateNextScheduledContributionDate();
    if (empty($nextContributionDate)) {
      return;
    }

    $query = 'UPDATE civicrm_contribution_recur SET next_sched_contribution_date = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($query, [
      1 => [$nextContributionDate, 'String'],
      2 => [$this->recurContributionID, 'Integer'],
    ]);
  }

  private function calculateNextScheduledContributionDate() {
    $lastContributionReceiveDate = $this->getLastContributionReceiveDate();
    if (empty($lastContributionReceiveDate)) {
      return NULL;
    }

    $contributionRecur = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'return' => ['id', 'start_date', 'frequency_interval', 'frequency_unit', 'cycle_day'],
      'id' => $this->recurContributionID,
      'options' => ['limit' => 1],
    ])['values'][0];
    $receiveDateCalculator = new CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator($contributionRecur);
    $receiveDateCalculator->setStartDate($lastContributionReceiveDate);

    return $receiveDateCalculator->calculate(2);
  }

  private function getLastContributionReceiveDate() {
    $lastContributionReceiveDate = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $this->recurContributionID,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ]);
    if ($lastContributionReceiveDate['count'] != 1) {
      return NULL;
    }

    return $lastContributionReceiveDate['values'][0]['receive_date'];
  }

}
