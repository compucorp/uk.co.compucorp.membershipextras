<?php

use CRM_MembershipExtras_BAO_ContributionRecurLineItem as ContributionRecurLineItem;

class CRM_MembershipExtras_Service_MembershipLineProRataCalculator {

  /**
   * Calculates the pro-rata amounts for a given membership type
   * within a payment plan within a specified period of time.
   * The period end date will be used as "To Date" if it is not
   * supplied, where the input membership type financial type id
   * will be used if no financial type is supplied.
   *
   * @param int $recurContributionId
   * @param int $membershipTypeId
   * @param string $fromDate
   * @param string $toDate
   * @param int $financialTypeId
   *
   * @return array
   */
  public static function calculateAmounts($recurContributionId, $membershipTypeId, $fromDate, $toDate = NULL, $financialTypeId = NULL) {
    if (empty($toDate)) {
      $toDate = ContributionRecurLineItem::getPeriodEndDate($recurContributionId);
    }

    $membershipType = CRM_Member_BAO_MembershipType::findById($membershipTypeId);
    if (empty($financialTypeId)) {
      $financialTypeId = $membershipType->financial_type_id;
    }

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $membershipDurationCalculator = new CRM_MembershipExtras_Service_MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $membershipStartDate = new DateTime($fromDate);
    $membershipEndDate = new DateTime($toDate);
    $prorataDaysCount = $membershipDurationCalculator->calculateDaysBasedOnDates($membershipStartDate, $membershipEndDate);

    $membershipTypeDurationInDays = $membershipDurationCalculator->calculateOriginalInDays();
    $proratedAmount = ($membershipType->minimum_fee / $membershipTypeDurationInDays) * $prorataDaysCount;
    $proratedAmount = CRM_MembershipExtras_Service_MoneyUtilities::roundToPrecision($proratedAmount, 2);

    $amounts = civicrm_api3('ContributionRecurLineItem', 'calculatetaxamount', [
      'amount_exc_tax' => $proratedAmount,
      'financial_type_id' => $financialTypeId,
    ]);

    return [
      'amount_exc_tax' => $proratedAmount,
      'amount_inc_tax' => $amounts['total_amount'],
      'tax_amount' => $amounts['tax_amount'],
      'prorata_days_count' => $prorataDaysCount,
      'used_financial_type_id' => $financialTypeId,
    ];
  }

}
