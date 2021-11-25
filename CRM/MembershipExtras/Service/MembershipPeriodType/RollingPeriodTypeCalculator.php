<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_AbstractPeriodTypeCalculator as PeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

class CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator extends PeriodTypeCalculator implements Calculator {

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
   *
   * @throws Exception
   */
  public function calculate() {
    foreach ($this->membershipTypes as $membershipType) {
      $amount = $membershipType->minimum_fee;
      $taxAmount = $this->instalmentTaxAmountCalculator->calculateByMembershipType($membershipType, $membershipType->minimum_fee);

      $this->amount += $amount;
      $this->taxAmount += $taxAmount;

      $this->generateLineItem($membershipType->financial_type_id, $amount, $taxAmount);
    }
  }

}
