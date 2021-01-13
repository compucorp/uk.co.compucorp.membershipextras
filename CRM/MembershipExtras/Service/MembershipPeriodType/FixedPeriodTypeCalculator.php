<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;
use CRM_MembershipExtras_Hook_BuildForm_MembershipType_Setting as SettingField;

class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator implements Calculator {

  /**
   * Constants for Annal ProRata Calculation
   * These constants will be used across application for calculation pro rata
   *
   */
  const BY_DAYS = 1;
  const BY_MONTHS = 2;

  const TWELVE_MONTHS = 12;

  /**
   * @var \CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
   */
  private $instalmentTaxAmountCalculator;

  /**
   * @var float
   */
  private $proRatedTaxAmount = 0;

  /**
   * @var float
   */
  private $proRatedAmount = 0;

  /**
   * @var array
   */
  private $membershipTypes;

  public function __construct(array $membershipTypes) {
    $this->instalmentTaxAmountCalculator = new MembershipInstalmentTaxAmountCalculator();
    $this->membershipTypes = $membershipTypes;
  }

  /**
   *
   * @param \DateTime|NULL $startDate
   * @param \DateTime|NULL $endDate
   * @param \DateTime|NULL $joinDate
   *
   * @throws Exception
   */
  public function calculate(DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    foreach ($this->membershipTypes as $membershipType) {
      $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, new MembershipTypeDatesCalculator());
      $settings = CRM_MembershipExtras_SettingsManager::getMembershipTypeSettings($membershipType->id);
      $annualProRataCalculation = $settings[SettingField::ANNUAL_PRORATA_CALCULATION_ELEMENT];
      $membershipAmount = $membershipType->minimum_fee;
      $taxAmount = $this->instalmentTaxAmountCalculator->calculateByMembershipType($membershipType, $membershipAmount);
      if ($annualProRataCalculation == self::BY_DAYS) {
        $durationInDays = $membershipTypeDurationCalculator->calculateOriginalInDays();
        $diffInDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($startDate, $endDate, $joinDate);
        $this->proRatedAmount += ($membershipAmount / $durationInDays) * $diffInDays;
        $this->proRatedTaxAmount += ($taxAmount / $durationInDays) * $diffInDays;
      }
      else {
        $diffInMonth = $membershipTypeDurationCalculator->calculateMonthForAnnualDurationBasedOnDates($startDate, $endDate, $joinDate);
        $amount = ($membershipAmount / self::TWELVE_MONTHS) * $diffInMonth;
        $this->proRatedAmount += $amount;
        $this->proRatedTaxAmount += ($taxAmount / self::TWELVE_MONTHS) * $diffInMonth;
      }
    }
  }

  /**
   * @return float
   */
  public function getTaxAmount() {
    return $this->proRatedTaxAmount;
  }

  /**
   * @return float
   */
  public function getAmount() {
    return $this->proRatedAmount;
  }

  /**
   * @return float
   */
  public function getTotalAmount() {
    return $this->proRatedAmount + $this->proRatedTaxAmount;
  }

}
