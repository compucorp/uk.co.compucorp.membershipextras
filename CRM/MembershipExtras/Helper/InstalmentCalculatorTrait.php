<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtils;

trait CRM_MembershipExtras_Helper_InstalmentCalculatorTrait {

  /**
   * Calculates Single Instalment Amount
   *
   * @param $amount
   * @param $divisor
   * @return float|int
   */
  public function calculateSingleInstalmentAmount(float $amount, float $divisor) {
    return MoneyUtils::roundToPrecision($amount / $divisor, 2);
  }

}
