<?php

use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_Member_BAO_MembershipType as MembershipType;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_MembershipTypeTaxAmount as MembershipTypeTaxAmount;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeAmountProrater
 */
class CRM_MembershipExtras_Service_MembershipTypeAmountProrater {

  /**
   * @var MembershipTypeDurationCalculator
   */
  private $membershipTypeDurationCalculator;

  /**
   * @var MembershipTypeTaxAmount
   */
  private $membershipTypeTaxAmount;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeAmount constructor.
   *
   * @param MembershipTypeDurationCalculator $membershipTypeDurationCalculator
   * @param MembershipTypeTaxAmount $membershipTypeTaxAmount
   */
  public function __construct(MembershipTypeDurationCalculator $membershipTypeDurationCalculator, MembershipTypeTaxAmount $membershipTypeTaxAmount) {
    $this->membershipTypeDurationCalculator = $membershipTypeDurationCalculator;
    $this->membershipTypeTaxAmount = $membershipTypeTaxAmount;
  }

  /**
   * Calculates the pro-rated amount for the membership type given start and end dates
   * on the Tax exclusive amount.
   *
   * @param \CRM_Member_BAO_MembershipType $membershipType
   * @param \DateTime|NULL $startDate
   * @param \DateTime|NULL $endDate
   * @param \DateTime|NULL $joinDate
   *
   * @return float
   */
  public function calculateProRata
  (
    MembershipType $membershipType,
    DateTime $startDate = NULL,
    DateTime $endDate = NULL,
    DateTime $joinDate = NULL
  ) {
    $membershipTypeDurationInDays = $this->membershipTypeDurationCalculator->calculateOriginalInDays();
    $calculatedDurationInDays = $this->membershipTypeDurationCalculator->calculateDaysBasedOnDates($startDate, $endDate, $joinDate);
    $membershipAmount = $membershipType->minimum_fee;

    $proRata = ($membershipAmount/$membershipTypeDurationInDays) * $calculatedDurationInDays;
    $tax = $this->membershipTypeTaxAmount->calculateTax($membershipType, $proRata);

    return MoneyUtilities::roundToPrecision(($proRata + $tax), 2);
  }

}
