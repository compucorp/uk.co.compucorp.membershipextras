<?php

use CRM_Member_BAO_MembershipType as MembershipType;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_MembershipTypeTaxAmount as MembershipTypeTaxAmount;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentAmount as InvalidMembershipTypeInstalmentAmount;

class CRM_MembershipExtras_Service_MembershipTypeInstalmentAmount {

  /**
   * @var float
   */
  private $followingInstalmentAmount;

  /**
   * @var array
   */
  private $membershipTypes;

  /**
   * @var \DateTime
   */
  private $startDate;

  /**
   * @var \DateTime
   */
  private $endDate;

  /**
   * @var MembershipTypeTaxAmount
   */
  private $membershipTypeTaxAmount;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeInstalmentAmount constructor.
   *
   * @param MembershipType[] $membershipTypes
   * @param MembershipTypeTaxAmount $membershipTypeTaxAmount
   * @param \DateTime $startDate
   * @param \DateTime $endDate
   */
  public function __construct(array $membershipTypes, MembershipTypeTaxAmount $membershipTypeTaxAmount, DateTime $startDate, DateTime $endDate) {
    $this->membershipTypes = $membershipTypes;
    $this->startDate = $startDate;
    $this->endDate = $endDate;
    $this->membershipTypeTaxAmount = $membershipTypeTaxAmount;
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
        $totalMembershipTypeAmount += ($membershipType->minimum_fee + $this->membershipTypeTaxAmount->calculateTax($membershipType));
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
   * @return float
   */
  public function calculateFirstInstalmentAmount() {
    $foiAmount = $this->calculateFollowingInstalmentAmount();
    $calculatedDurationInDays = $this->calculateRemainingDaysInStartDateMonth();
    $proRata = ($calculatedDurationInDays / $this->getNumberOfDaysInStartDateMonth()) * $foiAmount;

    return MoneyUtilities::roundToPrecision(($proRata), 2);
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

      if ((int) $this->endDate->format( 't' ) != $this->endDate->format( 'j')) {
        throw new InvalidMembershipTypeInstalmentAmount(InvalidMembershipTypeInstalmentAmount::LAST_DAY_OF_MONTH);
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
   * @return int
   */
  private function calculateRemainingDaysInStartDateMonth() {
    $endOfMonthDate = new DateTime($this->startDate->format('Y-m-t'));
    $interval = $endOfMonthDate->diff($this->startDate);

    return (int) $interval->format("%a");
  }

  /**
   * Calculates the number of days in the month the start date falls in.
   *
   * @return int
   */
  private function getNumberOfDaysInStartDateMonth() {
    return (int) $this->startDate->format('t');
  }
}
