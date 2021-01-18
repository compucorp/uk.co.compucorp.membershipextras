<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_AbstractFixedPeriodTypeCalculator as FixedPeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeMonthlyCalculator extends FixedPeriodTypeCalculator implements Calculator {

  /**
   * @var array
   */
  private $membershipTypes;
  /**
   * @var CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
   */
  private $instalmentTaxAmountCalculator;

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
      $membershipAmount = $membershipType->minimum_fee;
      $diffInMonth = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($this->startDate);
      $this->amount += $this->calculateProRatedAmount($membershipAmount, self::TWELVE_MONTHS, $diffInMonth);
      $taxAmount = $this->instalmentTaxAmountCalculator->calculateByMembershipType($membershipType, $membershipAmount);
      $this->taxAmount += $this->calculateProRatedAmount($taxAmount, self::TWELVE_MONTHS, $diffInMonth);
    }
  }

}
