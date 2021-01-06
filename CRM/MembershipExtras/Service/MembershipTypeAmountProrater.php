<?php

use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_Member_BAO_MembershipType as MembershipType;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeAmountProrater
 */
class CRM_MembershipExtras_Service_MembershipTypeAmountProrater {

  /**
   * @var \CRM_MembershipExtras_Service_MembershipTypeDurationCalculator
   */
  private $membershipTypeDurationCalculator;

  /**
   * @var \CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
   */
  private $membershipInstalmentTaxAmountCalculator;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeAmount constructor.
   *
   * @param \CRM_MembershipExtras_Service_MembershipTypeDurationCalculator $membershipTypeDurationCalculator
   * @param CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator $membershipInstalmentTaxAmountCalculator
   */
  public function __construct(
    MembershipTypeDurationCalculator $membershipTypeDurationCalculator,
    MembershipInstalmentTaxAmountCalculator $membershipInstalmentTaxAmountCalculator
  ) {
    $this->membershipTypeDurationCalculator = $membershipTypeDurationCalculator;
    $this->membershipInstalmentTaxAmountCalculator = $membershipInstalmentTaxAmountCalculator;
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

    $proRata = ($membershipAmount / $membershipTypeDurationInDays) * $calculatedDurationInDays;
    $tax = $this->membershipInstalmentTaxAmountCalculator->calculateByMembershipType($membershipType, $proRata);

    return MoneyUtilities::roundToPrecision(($proRata + $tax), 2);
  }

}
