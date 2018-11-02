<?php

/**
 * Class that provides methods to handle currency related amounts.
 */
class CRM_MembershipExtras_Service_MoneyUtilities extends CRM_Utils_Money {

  /**
   * Takes given float value and rounds it up to currency precision.
   *
   * @param float $amount
   * @param string $currency
   *   The three-letter ISO currency code ('USD').
   *
   * @return float
   */
  public static function roundToCurrencyPrecision($amount, $currency = null) {
    $config = CRM_Core_Config::singleton();

    if (!is_float($amount)) {
      $amount = floatval($amount);
    }

    if (!$currency) {
      $currency = $config->defaultCurrency;
    }

    return self::roundToPrecision($amount, self::getCurrencyPrecision($currency));
  }

  /**
   * Rounds an amount o the given precision.
   *
   * @param float $amount
   * @param int $precision
   *
   * @return float
   */
  public static function roundToPrecision($amount, $precision) {
    return round($amount, $precision);
  }

}
