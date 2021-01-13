<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

class CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator implements Calculator {

  /**
   * @var \CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
   */
  private $instalmentTaxAmountCalculator;

  /**
   * @var float
   */
  private $amount = 0;

  /**
   * @var float
   */
  private $taxAmount = 0;
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
      $this->amount += $membershipType->minimum_fee;
      $this->taxAmount += $this->instalmentTaxAmountCalculator->calculateByMembershipType($membershipType, $membershipType->minimum_fee);
    }
  }

  /**
   * @return float
   */
  public function getAmount() {
    return $this->amount;
  }

  /**
   * @return float
   */
  public function getTaxAmount() {
    return $this->taxAmount;
  }

  public function getTotalAmount() {
    return $this->amount + $this->taxAmount;
  }

}
