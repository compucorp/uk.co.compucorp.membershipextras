<?php

use CRM_Member_BAO_MembershipType as MembershipType;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeDuration
 */
class CRM_MembershipExtras_Service_MembershipTypeDuration {

  /**
   * @var MembershipType
   */
  private $membershipType;

  /**
   * @var DateTime
   */
  private $membershipStartDate;

  /**
   * @var DateTime
   */
  private $membershipEndDate;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeDuration constructor.
   *
   * @param \CRM_Member_BAO_MembershipType $membershipType
   */
  public function __construct(MembershipType $membershipType) {
    $this->membershipType = $membershipType;
    $this->setMembershipStartAndEndDates();
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
   * The dates are adjusted depending on whether they are present or not.
   * One more day is added to interval because the start and end date are inclusive of the
   * number of days.
   *
   * @param \DateTime|NULL $startDate
   * @param \DateTime|NULL $endDate
   *
   * @return int
   */
  public function calculateDaysBasedOnDates(DateTime $startDate = NULL, DateTime $endDate = NULL) {
    if ($startDate) {
      $this->setMembershipStartAndEndDates($startDate);
    }
    $adjustedStartDate = $startDate ? $startDate : $this->membershipStartDate;
    $adjustedEndDate = $endDate ? $endDate : $this->membershipEndDate;

    $interval = $adjustedEndDate->diff($adjustedStartDate);

    return (int) $interval->format("%a") + 1;
  }

  /**
   * Returns the number of days for a membership type period duration.
   *
   * @return float|int
   */
  private function getDurationInDays() {
    switch ($this->membershipType->duration_unit) {
      case 'month':
        $numberOfDays = date('t');
        break;
      case 'day':
        $numberOfDays = 1;
        break;
      case 'year':
        $numberOfDays = date('L') ? 366 : 365;
        break;
    }

    return $numberOfDays * $this->membershipType->duration_interval;
  }

  /**
   * Sets the membership type start and end dates. The logic works by using
   * today as the start date and adding the duration days to today's date
   * to get the end date. The end date is adjusted because the duration in
   * days includes both the start and end date.
   *
   * @param DateTime|NULL $startDate
   */
  private function setMembershipStartAndEndDates(DateTime $startDate = NULL) {
    $durationInDays = $this->getDurationInDays() - 1;
    $datetime = $startDate ? clone $startDate : new DateTime();
    $datetime->modify("+{$durationInDays} day");
    $this->membershipEndDate = $datetime;
    $this->membershipStartDate = $startDate ? clone $startDate : new DateTime();
  }
}
