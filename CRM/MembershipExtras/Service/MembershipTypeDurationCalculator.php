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
   * and interval.
   *
   * @return int
   */
  public function calculateOriginalInDays() {
    return $this->getDurationInDays();
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

  /**
   * Returns the number of days for a membership type period duration.
   *
   * @return float|int
   * @throws Exception
   */
  private function getDurationInDays() {
    $membershipDates = $this->membershipTypeDatesCalculator->getDatesForMembershipType($this->membershipType->id);
    $startDate = new DateTime($membershipDates['start_date']);
    $endDate = new DateTime($membershipDates['end_date']);
    $interval = $endDate->diff($startDate);

    return (int) $interval->format("%a") + 1;
  }

}
