<?php

use CRM_Member_BAO_MembershipType as MembershipType;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeDurationCalculator
 */
class CRM_MembershipExtras_Service_MembershipTypeDurationCalculator {

  /**
   * @var CRM_Member_BAO_MembershipType
   */
  private $membershipType;

  /**
   * @var CRM_MembershipExtras_Service_MembershipTypeDatesCalculator
   */
  private $membershipTypeDatesCalculator;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeDuration constructor.
   *
   * @param CRM_Member_BAO_MembershipType $membershipType
   * @param CRM_MembershipExtras_Service_MembershipTypeDatesCalculator $membershipTypeDatesCalculator
   */
  public function __construct(MembershipType $membershipType, MembershipTypeDatesCalculator $membershipTypeDatesCalculator) {
    $this->membershipType = $membershipType;
    $this->membershipTypeDatesCalculator = $membershipTypeDatesCalculator;
  }

  /**
   * Calculates the membership type period in days based on the membership type period unit
   * ,interval and the dates in which the membership will be created within.
   *
   * @params \DateTime|NULL $membershipStartDate
   * @params \DateTime|NULL $membershipEndDate
   *
   * @return int
   */
  public function calculateOriginalInDays($membershipStartDate = NULL, $membershipEndDate = NULL) {
    $membershipType = CRM_Member_BAO_MembershipType::findById($this->membershipType->id);
    if ($membershipType->duration_unit == 'year' && !empty($membershipStartDate) && !empty($membershipEndDate)) {
      return $this->calculateDurationForOneYearMemberships($membershipType, $membershipStartDate, $membershipEndDate);
    }

    $membershipDates = $this->membershipTypeDatesCalculator->getDatesForMembershipType($this->membershipType->id);
    $startDate = new DateTime($membershipDates['start_date']);
    $endDate = new DateTime($membershipDates['end_date']);
    $interval = $endDate->diff($startDate);

    return (int) $interval->format("%a") + 1;
  }

  /**
   * This handles calculating during for membership types with "year" duration unit.
   * In general, we only support "1 year" fixed memberships, where the fixed period
   * start day can only be the 1st day of the month, so this simplifies calculating
   * the pro-rata amount, but there are some cases that are handled here such as:
   *
   * 1- Creating a membership with dates that fall within a leap year during
   * a non leap year (e.g the membership starts and ends within 2024 but it was created
   * druing 2023).
   *
   * 2- Creating a membership with dates that fall within a non leap year during
   * a leap year.
   *
   * 3- Creating a membership that start during a non leap year but ends within
   * a leap year, or the other way around.
   */
  private function calculateDurationForOneYearMemberships($membershipType, $membershipStartDate, $membershipEndDate) {
    $isStartDateYearLeapYear = $this->isLeapYear($membershipStartDate->format('Y'));
    $isEndDateYearLeapYear = $this->isLeapYear($membershipEndDate->format('Y'));
    $termStartMonth = substr($membershipType->fixed_period_start_day, 0, strlen($membershipType->fixed_period_start_day) - 2);
    $termEndMonth = substr($membershipType->fixed_period_rollover_day, 0, strlen($membershipType->fixed_period_rollover_day) - 2);

    if (!$isStartDateYearLeapYear && !$isEndDateYearLeapYear) {
      return 365;
    }

    if ($isStartDateYearLeapYear && $isEndDateYearLeapYear) {
      return 366;
    }

    if ($isStartDateYearLeapYear && !$isEndDateYearLeapYear) {
      // If the fixed membership type is configured to start on or after March, and a membership
      // of such type is created with start date that is also on or after March, then the
      // leap day (which is february the 29th) shouldn't be included.
      if ($termStartMonth >= 3) {
        return 365;
      }
      return 366;
    }

    if (!$isStartDateYearLeapYear && $isEndDateYearLeapYear) {
      // If the fixed membership type is configured to end on or after March, and a membership
      // of such type is created with end date that is also on or after March, then the
      // leap day (which is february the 29th) should be included.
      if ($termEndMonth >= 3) {
        return 366;
      }
      return 365;
    }

    return 365;
  }

  private function isLeapYear($year) {
    if (date('L', strtotime("$year-01-01"))) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Calculates the membership type duration in days based on start and end dates passed in.
   * One more day is added to interval because the start and end date are inclusive of the
   * number of days.
   *
   * @param \DateTime|NULL $startDate
   * @param \DateTime|NULL $endDate
   * @param \DateTime|NULL $joinDate
   *
   * @return int
   */
  public function calculateDaysBasedOnDates (DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    $membershipDates = $this->getMembershipDates($startDate, $endDate, $joinDate);
    $interval = $this->diffDates($membershipDates['start_date'], $membershipDates['end_date']);

    return (int) $interval->format("%a") + 1;
  }

  /**
   * Calculates the membership type duration in months based on start and end dates passed in.
   * One more month is added to interval because the start and end date are inclusive of the
   * number of months
   *
   * @param \DateTime|NULL $startDate
   * @param \DateTime|NULL $endDate
   * @param \DateTime|NULL $joinDate
   *
   * @return int
   */
  public function calculateMonthsBasedOnDates(DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    $membershipDates = $this->getMembershipDates($startDate, $endDate, $joinDate);
    $interval = $this->diffDates($membershipDates['start_date'], $membershipDates['end_date']);

    return (int) $interval->format('%m') + 1;
  }

  /**
   * Gets Membership Dates
   *
   * @return array
   */
  private function getMembershipDates($startDate, $endDate, $joinDate) {
    return $this->membershipTypeDatesCalculator->getDatesForMembershipType(
      $this->membershipType->id,
      $startDate,
      $endDate,
      $joinDate
    );
  }

  /**
   * Gets different interval between two dates.
   *
   * @return DateInterval
   * @throws Exception
   */
  private function diffDates($startDate, $endDate) {
    $membershipStartDate = new DateTime($startDate);
    $membershipEndDate = new DateTime($endDate);

    return $membershipEndDate->diff($membershipStartDate);
  }

}
