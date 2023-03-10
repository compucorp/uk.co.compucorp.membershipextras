<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_AbstractPeriodTypeCalculator as PeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

class CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator extends PeriodTypeCalculator implements Calculator {

  /**
   * @var array
   */
  private $membershipTypes;
  /**
   * @var float|null
   */
  private $totalAmountAfterDiscount;

  public function __construct(array $membershipTypes, $totalAmountAfterDiscount = NULL) {
    $this->instalmentTaxAmountCalculator = new MembershipInstalmentTaxAmountCalculator();
    $this->membershipTypes = $membershipTypes;
    $this->totalAmountAfterDiscount = $totalAmountAfterDiscount;
  }

  /**
   *
   *
   * @throws Exception
   */
  public function calculate() {
    foreach ($this->membershipTypes as $membershipType) {
      $discount = 1;
      $amount = $membershipType->minimum_fee;
      $taxAmount = $this->instalmentTaxAmountCalculator->calculateByMembershipType($membershipType, $amount);

      // calculate the discount amount
      if (!empty($this->totalAmountAfterDiscount)) {
        $discount = $this->totalAmountAfterDiscount / ($amount + $taxAmount);
      }

      $amount = $amount * $discount;
      $taxAmount = $taxAmount * $discount;

      $this->amount += $amount;
      $this->taxAmount += $taxAmount;

      $this->generateLineItem($membershipType->financial_type_id, $amount, $taxAmount);
    }
  }

}
