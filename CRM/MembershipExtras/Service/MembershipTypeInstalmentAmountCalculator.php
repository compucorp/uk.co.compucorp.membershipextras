<?php

use CRM_Member_BAO_MembershipType as MembershipType;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_MembershipTypeTaxAmountCalculator as MembershipTypeTaxAmountCalculator;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentAmount as InvalidMembershipTypeInstalmentAmount;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;

class CRM_MembershipExtras_Service_MembershipTypeInstalmentAmountCalculator {

  /**
   * @var float
   */
  private $followingInstalmentAmount;

  /**
   * @var MembershipTypeDatesCalculator
   */
  private $membershipTypeDatesCalculator;

  /**
   * @var array
   */
  private $membershipTypes;

  /**
   * @var MembershipTypeTaxAmountCalculator
   */
  private $membershipTypeTaxAmountCalculator;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeInstalmentAmount constructor.
   *
   * @param MembershipType[] $membershipTypes
   * @param MembershipTypeTaxAmountCalculator $membershipTypeTaxAmountCalculator
   * @param MembershipTypeDatesCalculator $membershipTypeDatesCalculator
   */
  public function __construct
  (
    array $membershipTypes,
    MembershipTypeTaxAmountCalculator $membershipTypeTaxAmountCalculator,
    MembershipTypeDatesCalculator $membershipTypeDatesCalculator)
  {
    $this->membershipTypes = $membershipTypes;
    $this->membershipTypeTaxAmountCalculator = $membershipTypeTaxAmountCalculator;
    $this->membershipTypeDatesCalculator = $membershipTypeDatesCalculator;
    $this->validateMembershipTypeForInstalment();
  }

  /**
   * Calculates the following instalment amount for a set of membership types given that the
   * condition for calculating the following instalment amount is met.
   * FOI = (total amount of membership(s) cost for a full year / 12
   *
   * @return float
   */
  public function calculateFollowingInstalmentAmount() {
    if (!$this->followingInstalmentAmount) {
      $totalMembershipTypeAmount = 0;
      foreach ($this->membershipTypes as $membershipType) {
        $totalMembershipTypeAmount += ($membershipType->minimum_fee + $this->membershipTypeTaxAmountCalculator->calculateTax($membershipType));
      }

      $this->followingInstalmentAmount = MoneyUtilities::roundToPrecision($totalMembershipTypeAmount/12, 2);
    }

    return $this->followingInstalmentAmount;
  }

  /**
   * Calculates the first instalment amount for a set of membership types given that the conditions
   * for calculating the first instalment amount is met.
   * First Instalment Amount(FI) = (A/B) * FOI
   * A = No of days from membership start date to end of month
   * B = No of days in month.
   *
   * @param \DateTime|NULL $startDate
   * @param \DateTime|NULL $endDate
   * @param \DateTime|NULL $joinDate
   *
   * @return float
   */
  public function calculateFirstInstalmentAmount(DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    if (empty($startDate)) {
      $startDate = new DateTime($this->getMembershipStartDate($startDate, $endDate, $joinDate));
    }

    $foiAmount = $this->calculateFollowingInstalmentAmount();
    $calculatedDurationInDays = $this->calculateRemainingDaysInStartDateMonth($startDate);
    $proRata = ($calculatedDurationInDays / $this->getNumberOfDaysInStartDateMonth($startDate)) * $foiAmount;

    return MoneyUtilities::roundToPrecision(($proRata), 2);
  }

  /**
   * @param \DateTime|NULL $startDate
   * @param \DateTime|NULL $endDate
   * @param \DateTime|NULL $joinDate
   * @return mixed
   */
  private function getMembershipStartDate(DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    $membershipDates = $this->membershipTypeDatesCalculator->getDatesForMembershipType(
      $this->membershipTypes[0],
      $startDate,
      $endDate,
      $joinDate
    );

    return $membershipDates['start_date'];
  }

  /**
   * Validates the membership types passed in to ensure they meets the criteria for calculating
   * First Instalment and Following Instalment amount for a set of membership types.
   */
  private function validateMembershipTypeForInstalment() {
    $fixedPeriodStartDays = [];
    foreach ($this->membershipTypes as $membershipType) {
      $fixedPeriodStartDays[] = $membershipType->fixed_period_start_day;
      if ($membershipType->duration_unit != 'year' || $membershipType->duration_interval != 1) {
        throw new InvalidMembershipTypeInstalmentAmount(InvalidMembershipTypeInstalmentAmount::ONE_YEAR_DURATION);
      }

      if ($membershipType->period_type != 'fixed') {
        throw new InvalidMembershipTypeInstalmentAmount(InvalidMembershipTypeInstalmentAmount::FIXED_PERIOD_TYPE);
      }
    }

    $fixedPeriodStartDays = array_unique($fixedPeriodStartDays);
    if (count($fixedPeriodStartDays) != 1) {
      throw new InvalidMembershipTypeInstalmentAmount(InvalidMembershipTypeInstalmentAmount::SAME_PERIOD_START_DAY);
    }
  }

  /**
   * Calculates the remaining days from start date to the end of month date.
   *
   * @param DateTime $startDate
   *
   * @return int
   */
  private function calculateRemainingDaysInStartDateMonth(DateTime $startDate) {
    $endOfMonthDate = new DateTime($startDate->format('Y-m-t'));
    $interval = $endOfMonthDate->diff($startDate);

    return (int) $interval->format("%a");
  }

  /**
   * Calculates the number of days in the month the start date falls in.
   *
   * @param DateTime $startDate
   *
   * @return int
   */
  private function getNumberOfDaysInStartDateMonth(DateTime $startDate) {
    return (int) $startDate->format('t');
  }
}
