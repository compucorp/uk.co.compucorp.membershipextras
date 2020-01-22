<?php

/**
 * Calculates membership status taking into account in_arrears and not_arrears
 * start and end events.
 */
class CRM_MembershipExtras_Hook_Alter_CalculatedMembershipStatus {

  /**
   * Array with the membership's details, as passed by the hook function.
   *
   * @var array
   */
  private $membership;

  /**
   * Array with the recurring contribution's data.
   *
   * @var array
   */
  private $recurringContribution;

  /**
   * Dates used by CiviCRM to calculate status, ie. start_date, end_date and
   * join_date.
   *
   * @var array
   */
  private $calculationArguments;

  /**
   * List of all available membership statuses.
   *
   * @var array
   */
  private static $memberShipStatuses = [];

  /**
   * Maps the names of contribution statuses to their corresponding values.
   *
   * @var array
   */
  private static $contributionStatusValueMap = [];

  public function __construct() {
    if (count(self::$memberShipStatuses) == 0) {
      $membershipStatuses = civicrm_api3('MembershipStatus', 'get', [
        'sequential' => 1,
        'is_active' => 1,
        'options' => ['sort' => 'weight ASC', 'limit' => 0],
      ]);
      self::$memberShipStatuses = $membershipStatuses['values'];
    }

    if (count(self::$contributionStatusValueMap) == 0) {
      $contributionStatuses = civicrm_api3('OptionValue', 'get', [
        'option_group_id' => "contribution_status",
      ]);

      foreach ($contributionStatuses['values'] as $currentStatus) {
        self::$contributionStatusValueMap[$currentStatus['name']] = $currentStatus['value'];
      }
    }
  }

  /**
   * This hook is called when membership status is being calculated.
   *
   * @param array $calculatedStatus
   *   Membership status details as determined by CiviCRM core
   * @param array $arguments
   *   Arguments passed in to calculate date
   * @param array $membership
   *   Membership details from the calling function
   */
  public function alterMembershipStatus(&$calculatedStatus, $arguments, $membership) {
    $this->membership = $membership;
    $this->calculationArguments = $arguments;

    // If membership is being created, we won't be able to tell if it's for a
    // payment plan or not.
    $isMembershipExist = CRM_Utils_Array::value('id', $this->membership, false);
    if (!$isMembershipExist) {
      return;
    }

    // If membership was not last paid for with a payment plan, no need to process
    $isPaymentPlanMembership = $this->checkMembershipPaymentPlan();
    if (!$isPaymentPlanMembership) {
      return;
    }

    // Otherwise, recalculate status taking into account arrears related events.
    $this->recalculateMembershipStatus($calculatedStatus);
  }

  /**
   * Checks if membership was last payed for with a payment plan.
   *
   * @return bool
   */
  private function checkMembershipPaymentPlan() {
    $query = '
      SELECT civicrm_contribution_recur.id AS recurid
      FROM civicrm_membership_payment
      INNER JOIN civicrm_contribution ON civicrm_membership_payment.contribution_id = civicrm_contribution.id
      LEFT JOIN civicrm_contribution_recur ON civicrm_contribution.contribution_recur_id = civicrm_contribution_recur.id
      WHERE civicrm_membership_payment.membership_id = %1
      AND civicrm_contribution_recur.installments > 0
      ORDER BY civicrm_contribution.id DESC
      LIMIT 1
    ';
    $pendingContributionsResult = CRM_Core_DAO::executeQuery($query, [
      1 => [$this->membership['id'], 'Integer'],
    ]);
    $pendingContributionsResult->fetch();

    if (!empty($pendingContributionsResult->recurid)) {
      $this->recurringContribution = civicrm_api3('ContributionRecur', 'getsingle', [
        'id' => $pendingContributionsResult->recurid,
      ]);

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Reevaluates the membership status rules taking into account arrears events.
   *
   * @param array $calculatedStatus
   */
  private function recalculateMembershipStatus(&$calculatedStatus) {
    foreach (self::$memberShipStatuses as $status) {
      $statusStartEvent = CRM_Utils_Array::value('start_event', $status);
      $startEventIsArrearsRelated = stripos($statusStartEvent, 'arrears') !== false;

      $statusEndEvent = CRM_Utils_Array::value('end_event', $status);
      $endEventIsArrearsRelated = stripos($statusEndEvent, 'arrears') !== false;

      if (!$startEventIsArrearsRelated && !$endEventIsArrearsRelated) {
        // If we reach calculated status, we don't need to consider other options by weight.
        if ($calculatedStatus['id'] == $status['id']) {
          break;
        }

        // No arrears, so we continue with next status.
        continue;
      }

      $startEvent = $this->checkEvent(
        CRM_Utils_Array::value('start_event', $status),
        $this->calculationArguments['status_date'],
        CRM_Utils_Array::value('start_event_adjust_unit', $status),
        CRM_Utils_Array::value('start_event_adjust_interval', $status)
      );

      $endEvent = $this->checkEvent(
        CRM_Utils_Array::value('end_event', $status),
        $this->calculationArguments['status_date'],
        CRM_Utils_Array::value('start_event_adjust_unit', $status),
        CRM_Utils_Array::value('start_event_adjust_interval', $status)
      );

      if ($startEvent && !$endEvent) {
        $calculatedStatus['id'] = $status['id'];
        $calculatedStatus['name'] = $status['name'];
        break;
      }
    }
  }

  /**
   * Checks if the given event has presented itself on the given membership.
   *
   * @param string $event
   *   Event to be checked
   * @param string $referenceDate
   *   Date to use as reference to check start date
   * @param string $adjustUnit
   *   Unit of time interval to be added to start date
   * @param int $adjustInterval
   *   Amount of time to be added to start date
   *
   * @return boolean
   *   True if the event has occurred, false otherwise
   */
  private function checkEvent($event, $referenceDate, $adjustUnit, $adjustInterval) {
    switch($event) {
      case 'start_date':
        $result = $this->checkStartDateEvent($referenceDate, $adjustUnit, $adjustInterval);
        break;

      case 'end_date':
        $result = $this->checkEndDateEvent($referenceDate, $adjustUnit, $adjustInterval);
        break;

      case 'join_date':
        $result = $this->checkJoinDateEvent($referenceDate, $adjustUnit, $adjustInterval);
        break;

      case 'in_arrears':
        $result = $this->checkInArrearsEvent($referenceDate, $adjustUnit, $adjustInterval);
        break;

      case 'not_arrears':
        $result = $this->checkNotInArrearsEvent($referenceDate, $adjustUnit, $adjustInterval);
        break;

      default:
        $result = FALSE;
    }

    return $result;
  }

  /**
   * Checks if the membership has started, taking into account given time
   * interval.
   *
   * @param string $referenceDateString
   *   Date to use as reference to check start date
   * @param string $adjustUnit
   *   Unit of time interval to be added to start date
   * @param int $adjustInterval
   *   Amount of time to be added to start date
   *
   * @return boolean
   *   True if membership start date + interval is less than reference date,
   *   false otherwise
   */
  private function checkStartDateEvent($referenceDateString, $adjustUnit, $adjustInterval) {
    return $this->hasAdjustedDatePassed(
      $this->calculationArguments['start_date'],
      $referenceDateString,
      $adjustUnit,
      $adjustInterval
    );
  }

  /**
   * Checks if the membership has ended, taking into account given time
   * interval.
   *
   * @param string $referenceDateString
   *   Date to use as reference to check end date
   * @param string $adjustUnit
   *   Unit of time interval to be added to end date
   * @param int $adjustInterval
   *   Amount of time to be added to end date
   *
   * @return boolean
   *   True if membership end date + interval is less than reference date,
   *   false otherwise
   */
  private function checkEndDateEvent($referenceDateString, $adjustUnit, $adjustInterval) {
    return $this->hasAdjustedDatePassed(
      $this->calculationArguments['end_date'],
      $referenceDateString,
      $adjustUnit,
      $adjustInterval
    );
  }

  /**
   * Checks if the join date has passed, taking into account given time
   * interval.
   *
   * @param string $referenceDateString
   *   Date to use as reference to check end date
   * @param string $adjustUnit
   *   Unit of time interval to be added to end date
   * @param int $adjustInterval
   *   Amount of time to be added to end date
   *
   * @return boolean
   *   True if membership join date + interval is less than reference date,
   *   false otherwise
   */
  private function checkJoinDateEvent($referenceDateString, $adjustUnit, $adjustInterval) {
    return $this->hasAdjustedDatePassed(
      $this->calculationArguments['join_date'],
      $referenceDateString,
      $adjustUnit,
      $adjustInterval
    );
  }

  /**
   * Checks if the given date has passed, taking into account given time
   * interval, comparing it to the reference date.
   *
   * @param string $evaluatedDateString
   *   Date to be evaluated
   * @param string $referenceDateString
   *   Date to use as reference to check date to be evaluated
   * @param string $adjustUnit
   *   Unit of time interval to be added to evaluated date
   * @param int $adjustInterval
   *   Amount of time to be added to evaluated date
   *
   * @return boolean
   *   True if evaluated date + interval is less than reference date, false
   *   otherwise
   */
  private function hasAdjustedDatePassed($evaluatedDateString, $referenceDateString, $adjustUnit, $adjustInterval) {
    $referenceDate = new DateTime($referenceDateString);
    $evaluatedDate = new DateTime($evaluatedDateString);

    if ($adjustInterval && $adjustUnit) {
      $intervalAbsoluteValue = abs($adjustInterval);

      switch ($adjustUnit) {
        case 'month':
          $interval = "P{$intervalAbsoluteValue}M";
          break;
        case 'day':
          $interval = "P{$intervalAbsoluteValue}D";
          break;
        case 'year':
          $interval = "P{$intervalAbsoluteValue}Y";
          break;
      }

      if ($adjustInterval >= 0) {
        $referenceDate->add(new DateInterval($interval));
      } else {
        $referenceDate->sub(new DateInterval($interval));
      }
    }

    if ($evaluatedDate >= $referenceDate) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if membership is in arrears, by checking if any pending payments
   * associated to a payment plan land before the adjusted reference date and
   * no payment is cancelled.
   *
   * @param string $referenceDateString
   *   Date to use as reference to check if membership is in arrears
   * @param string $adjustUnit
   *   Unit of time interval to be added to evaluated date
   * @param int $adjustInterval
   *   Amount of time to be added to evaluated date
   *
   * @return boolean
   *   True if membership is in arrears, false otherwise
   */
  private function checkInArrearsEvent($referenceDateString, $adjustUnit, $adjustInterval) {
    $referenceDate = new DateTime($referenceDateString);

    if ($adjustInterval && $adjustUnit) {
      $intervalAbsoluteValue = abs($adjustInterval);

      switch ($adjustUnit) {
        case 'month':
          $interval = "P{$intervalAbsoluteValue}M";
          break;
        case 'day':
          $interval = "P{$intervalAbsoluteValue}D";
          break;
        case 'year':
          $interval = "P{$intervalAbsoluteValue}Y";
          break;
      }

      /**
       * Since we're applying interval delta to reference date instead of
       * evaluated date, we need to reverse sign of interval, ie. subtract
       * if > 0, add if < 0.
       */
      if ($adjustInterval >= 0) {
        $referenceDate->sub(new DateInterval($interval));
      } else {
        $referenceDate->add(new DateInterval($interval));
      }
    }

    $adjustedReferenceDate = $referenceDate->format('Y-m-d H:i:s');

    $query = "
      SELECT COUNT(civicrm_contribution.id) AS total
      FROM civicrm_membership_payment
      INNER JOIN civicrm_contribution ON civicrm_membership_payment.contribution_id = civicrm_contribution.id
      INNER JOIN civicrm_contribution_recur ON civicrm_contribution.contribution_recur_id = civicrm_contribution_recur.id
      WHERE civicrm_membership_payment.membership_id = %1 
      AND civicrm_contribution.contribution_status_id = %2 
      AND civicrm_contribution.receive_date <= %3 
      AND civicrm_contribution_recur.contribution_status_id != %4 
      AND civicrm_contribution_recur.installments > 0
    ";
    $pendingContributionsResult = CRM_Core_DAO::executeQuery($query, [
      1 => [$this->membership['id'], 'Integer'],
      2 => [self::$contributionStatusValueMap['Pending'], 'String'],
      3 => [$adjustedReferenceDate, 'String'],
      4 => [self::$contributionStatusValueMap['Cancelled'], 'String'],
    ]);
    $pendingContributionsResult->fetch();

    if ($pendingContributionsResult->total > 0) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if membership is NOT in arrears.
   *
   * @param string $referenceDateString
   *   Date to use as reference to check if membership is in arrears
   * @param string $adjustUnit
   *   Unit of time interval to be added to evaluated date
   * @param int $adjustInterval
   *   Amount of time to be added to evaluated date
   *
   * @return boolean
   *   True if membership is not in arrears, false otherwise
   */
  private function checkNotInArrearsEvent($referenceDateString, $adjustUnit, $adjustInterval) {
    return !$this->checkInArrearsEvent($referenceDateString, $adjustUnit, $adjustInterval);
  }

}
