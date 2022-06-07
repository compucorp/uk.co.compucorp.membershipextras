<?php

use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as InstalmentsSchedule;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as DurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as DateCalculator;

trait CRM_MembershipExtras_Helper_InstalmentHelperTrait {

  /**
   * Gets Membership start date
   *
   * @param int $membershipTypeId
   * @param DateTime|NULL $startDate
   * @param DateTime|NULL $endDate
   * @param DateTime|NULL $joinDate
   *
   * @return mixed
   */
  private function getMembershipStartDate(int $membershipTypeId, DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $membershipDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $membershipTypeId,
      $startDate,
      $endDate,
      $joinDate
    );

    return $membershipDates['start_date'];
  }

  /**
   * Gets number of instalments based on membership period type id, start date, duration unit and/or schedule
   *
   * @param $membershipType
   * @param $schedule
   * @param $startDate
   * @param DateTime|NULL $endDate
   * @param DateTime|NULL $joinDate
   *
   * @return int
   * @throws Exception
   */
  private function getInstalmentsNumber($membershipType, $schedule, $startDate = NULL, $endDate = NULL, $joinDate = NULL) {
    if ($membershipType->period_type == 'fixed' && $schedule == InstalmentsSchedule::MONTHLY && !is_null($startDate)) {
      $durationCalculator = new DurationCalculator($membershipType, new DateCalculator());

      return $durationCalculator->calculateMonthsBasedOnDates($startDate, $endDate, $joinDate);
    }

    $durationUnit = $membershipType->duration_unit;
    if ($membershipType->period_type == 'rolling' && $durationUnit == 'lifetime') {
      return 1;
    }

    if ($membershipType->period_type == 'rolling' && $durationUnit == 'month') {
      return (int) $membershipType->duration_interval;
    }

    return CRM_MembershipExtras_Helper_InstalmentSchedule::getInstalmentCountBySchedule($schedule, (int) $membershipType->duration_interval);
  }

}
