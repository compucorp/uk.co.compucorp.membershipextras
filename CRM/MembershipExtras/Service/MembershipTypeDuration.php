<?php

use CRM_Member_BAO_MembershipType as MembershipType;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeDuration
 */
class CRM_MembershipExtras_Service_MembershipTypeDuration {

  /**
   * @var MembershipType
   */
  private $membershipType;

  /**
   * @var MembershipTypeDatesCalculator
   */
  private $membershipTypeDatesCalculator;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeDuration constructor.
   *
   * @param MembershipType $membershipType
   * @param MembershipTypeDatesCalculator $membershipTypeDatesCalculator
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
  public function calculateOriginalInDays(){
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
    $startDate = empty($startDate) ? $joinDate : $startDate;
    $membershipDates = $this->membershipTypeDatesCalculator->getDatesForMembershipType(
      $this->membershipType,
      $startDate,
      $endDate,
      $joinDate
    );

    $membershipStartDate = new DateTime($membershipDates['start_date']);
    $membershipEndDate = new DateTime($membershipDates['end_date']);

    $interval = $membershipEndDate->diff($membershipStartDate);

    return (int) $interval->format("%a") + 1;
  }

  /**
   * Returns the number of days for a membership type period duration.
   *
   * @return float|int
   */
  private function getDurationInDays() {
    $membershipDates = $this->membershipTypeDatesCalculator->getDatesForMembershipType($this->membershipType);
    $startDate = new DateTime($membershipDates['start_date']);
    $endDate = new DateTime($membershipDates['end_date']);
    $interval = $endDate->diff($startDate);

    return (int) $interval->format("%a") + 1;
  }

}
