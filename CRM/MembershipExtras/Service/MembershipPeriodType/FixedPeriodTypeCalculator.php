<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_AbstractPeriodTypeCalculator as PeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;
use CRM_MembershipExtras_Hook_BuildForm_MembershipType_Setting as SettingField;

class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator extends PeriodTypeCalculator implements Calculator {

  /**
   * Constants for Annal ProRata Calculation
   * These constants will be used across application for calculation pro rata
   *
   */
  const BY_DAYS = 1;
  const BY_MONTHS = 2;

  const TWELVE_MONTHS = 12;

  /**
   * @var DateTime|null
   */
  private $joinDate = NULL;
  /**
   * @var DateTime|null
   */
  private $startDate = NULL;
  /**
   * @var DateTime|null
   */
  private $endDate = NULL;

  /**
   * @var array
   */
  private $membershipTypes;

  public function __construct(array $membershipTypes) {
    $this->instalmentTaxAmountCalculator = new MembershipInstalmentTaxAmountCalculator();
    $this->membershipTypes = $membershipTypes;
  }

  /**
   * Calculate pro rated amount base on amount, duration and different.
   * Amount, Tax amount can use this function.
   *
   * @param $amount
   * @param $duration
   * @param $diff
   * @return float|int
   */
  private function calculateProRatedAmount($amount, $duration, $diff) {
    return ($amount / $duration) * $diff;
  }

  /**
   * @throws Exception
   */
  public function calculate() {
    foreach ($this->membershipTypes as $membershipType) {
      $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, new MembershipTypeDatesCalculator());
      $settings = CRM_MembershipExtras_SettingsManager::getMembershipTypeSettings($membershipType->id);
      $annualProRataCalculation = $settings[SettingField::ANNUAL_PRORATA_CALCULATION_ELEMENT];
      $membershipAmount = $membershipType->minimum_fee;
      $taxAmount = $this->instalmentTaxAmountCalculator->calculateByMembershipType($membershipType, $membershipAmount);
      if ($annualProRataCalculation == self::BY_MONTHS) {
        $duration = self::TWELVE_MONTHS;
        $diff = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($this->startDate, $this->endDate, $this->joinDate);
        if ($this->isDurationWithInOneYearPeriod($duration, $diff)) {
          $this->recalCalcuateEndDate();
        }
        $diff = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($this->startDate, $this->endDate, $this->joinDate);
      }
      else {
        $duration  = $membershipTypeDurationCalculator->calculateOriginalInDays();
        $diff = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($this->startDate, $this->endDate, $this->joinDate);
        if ($this->isDurationWithInOneYearPeriod($duration, $diff)) {
          $this->recalCalcuateEndDate();
          $diff = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($this->startDate, $this->endDate, $this->joinDate);
        }
      }
      $amount = $this->calculateProRatedAmount($membershipAmount, $duration, $diff);
      $taxAmount = $this->calculateProRatedAmount($taxAmount, $duration, $diff);

      $this->amount += $amount;
      $this->taxAmount += $taxAmount;

      $this->generateLineItem($membershipType->financial_type_id, $amount, $taxAmount);
    }
  }

  /**
   * @return DateTime|null
   */
  public function getStartDate() {
    return $this->startDate;
  }

  /**
   * @param DateTime $startDate
   */
  public function setStartDate(DateTime $startDate) {
    $this->startDate = $startDate;
  }

  /**
   * @return DateTime|null
   */
  public function getJoinDate() {
    return $this->joinDate;
  }

  /**
   * @param DateTime|null $joinDate
   */
  public function setJoinDate(DateTime $joinDate) {
    $this->joinDate = $joinDate;
  }

  /**
   * @return DateTime|null
   */
  public function getEndDate() {
    return $this->endDate;
  }

  /**
   * @param DateTime|null $endDate
   */
  public function setEndDate(DateTime $endDate) {
    $this->endDate = $endDate;
  }

  /**
   * Checks if no of different days is greater than duration.
   * Example, start date and end date more than original in days.
   */
  private function isDurationWithInOneYearPeriod($duration, $diff) {
    return $diff > $duration;
  }

  /**
   * Subtracts end date by 1 year as
   * Membership extras default fixed membership to 1 year duration.
   */
  private function recalCalcuateEndDate() {
    $this->endDate->sub(new DateInterval('P1Y'));
  }

}
