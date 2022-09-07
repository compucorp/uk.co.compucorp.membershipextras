<?php

class CRM_MembershipExtras_Service_PaymentPlanNextContributionDate {

  /**
   * @var array
   */
  private $recurContribution;

  /**
   * @var string
   */
  private $operation;

  /**
   * @param int $recurContributionID
   * @param string $operation
   *   Payment plan "Creation" or "Renewal"
   */
  public function __construct($recurContributionID, $operation) {
    $this->recurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'return' => ['id', 'start_date', 'frequency_interval', 'frequency_unit', 'cycle_day'],
      'id' => $recurContributionID,
      'options' => ['limit' => 1],
    ])['values'][0];

    $this->operation = $operation;
  }

  /**
   * Calculates and Updates the recurring contribution `Next scheduled contribution date'.
   *
   * The `Next scheduled contribution date' will be used to control autorenewal,
   * so we here update its value according to the following if the
   * membership type period is "Rolling":
   *  - Monthly payment plan: +1 month from the last contribution receive date.
   *  - Quarterly: +3 months from the last contribution receive date.
   *  - Yearly: +1 year from the last contribution receive date
   * If the membership type period is "Fixed" (or if it is a priceset with fixed memberships)
   * then we instead:
   * - Calculate the date to be the membership end date plus 1 day (which is the start  date of the new period)
   * - Then correct the date to match the cycle day
   */
  public function calculateAndUpdate() {
    $nextContributionDate = $this->calculateNextScheduledContributionDate();
    if (empty($nextContributionDate)) {
      return;
    }

    $query = 'UPDATE civicrm_contribution_recur SET next_sched_contribution_date = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($query, [
      1 => [$nextContributionDate, 'String'],
      2 => [$this->recurContribution['id'], 'Integer'],
    ]);
  }

  private function calculateNextScheduledContributionDate() {
    $lastContribution = $this->getLastContribution();
    if (empty($lastContribution)) {
      return NULL;
    }

    $relatedMemberships = $this->getRelatedMemberships($lastContribution['id']);
    $atLeastOneFixedMembershipExist = FALSE;
    foreach ($relatedMemberships as $membership) {
      if ($membership['period_type'] == 'fixed') {
        $atLeastOneFixedMembershipExist = TRUE;
        $calculationMembership = $membership;
        break;
      }
    }

    if ($atLeastOneFixedMembershipExist && $this->operation == 'creation') {
      $nextScheduledDate = date('Y-m-d', strtotime($calculationMembership['end_date'] . '00:00:00 +1 day'));
      $nextScheduledDateCycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($nextScheduledDate, 'month');
      $paymentPlanCycleDay = $this->recurContribution['cycle_day'];
      $adjustmentDaysAmount = $paymentPlanCycleDay - $nextScheduledDateCycleDay;

      $nextScheduledDate = new DateTime($nextScheduledDate);
      $nextScheduledDate->modify("$adjustmentDaysAmount day");
      return $nextScheduledDate->format('Y-m-d 00:00:00');
    }
    else {
      $receiveDateCalculator = new CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator($this->recurContribution);
      $receiveDateCalculator->setStartDate($lastContribution['receive_date']);
      $nextScheduledDate = $receiveDateCalculator->calculate(2);
      $nextScheduledDate = new DateTime($nextScheduledDate);
      $frequencyUnit = $this->recurContribution['frequency_unit'];

      if ($frequencyUnit != 'year' || $nextScheduledDate->format('n') == 2) {
        $nextScheduledDate->setDate($nextScheduledDate->format('Y'), $nextScheduledDate->format('m'), min($nextScheduledDate->format('d'), 28));
      }

      return $nextScheduledDate->format('Y-m-d');
    }
  }

  private function getLastContribution() {
    $lastContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurContribution['id'],
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ]);
    if ($lastContribution['count'] != 1) {
      return NULL;
    }

    return $lastContribution['values'][0];
  }

  private function getRelatedMemberships($contributionId) {
    $query = '
      SELECT cm.id as membership_id, cm.end_date, cmt.period_type FROM civicrm_membership cm
      INNER JOIN civicrm_membership_payment cmp ON cm.id = cmp.membership_id
      INNER JOIN civicrm_membership_type cmt ON cm.membership_type_id = cmt.id
      WHERE cmp.contribution_id = %1
      ORDER BY cmp.membership_id ASC
    ';
    $results = CRM_Core_DAO::executeQuery($query, [
      1 => [$contributionId, 'Integer'],
    ]);

    $result = [];
    while ($results->fetch()) {
      $result[] = $results->toArray();
    }

    return $result;
  }

}
