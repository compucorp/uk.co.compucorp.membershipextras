<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_AbstractFixedPeriodTypeCalculator as FixedPeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;
use CRM_MembershipExtras_Hook_BuildForm_MembershipType_Setting as SettingField;

class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeAnnualCalculator extends FixedPeriodTypeCalculator implements Calculator {

  /**
   * Constants for Annal ProRata Calculation
   * These constants will be used across application for calculation pro rata
   *
   */
  const BY_DAYS = 1;
  const BY_MONTHS = 2;

  /**
   * @var \CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
   */
  private $instalmentTaxAmountCalculator;

  /**
   * @var array
   */
  private $membershipTypes;

  public function __construct(array $membershipTypes) {
    $this->instalmentTaxAmountCalculator = new MembershipInstalmentTaxAmountCalculator();
    $this->membershipTypes = $membershipTypes;
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
        $diff = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($this->startDate);
        $duration = self::TWELVE_MONTHS;
      }
      else {
        $duration  = $membershipTypeDurationCalculator->calculateOriginalInDays();
        $diff = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($this->startDate);
      }
      $this->amount += $this->calculateProRatedAmount($membershipAmount, $duration, $diff);
      $this->taxAmount += $this->calculateProRatedAmount($taxAmount, $duration, $diff);
    }
  }

}
