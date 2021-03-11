<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;

class CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator {

  /**
   * @var \CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface
   */
  private $calculator;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeInstalmentCalculator constructor.
   * @param \CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface $calculator
   */
  public function __construct(Calculator $calculator) {
    $this->calculator = $calculator;
  }

  /**
   * @return \CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface
   */
  public function getCalculator() {
    return $this->calculator;
  }

}
