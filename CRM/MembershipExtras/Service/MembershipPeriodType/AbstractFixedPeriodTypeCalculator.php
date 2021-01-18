<?php

abstract class CRM_MembershipExtras_Service_MembershipPeriodType_AbstractFixedPeriodTypeCalculator {

  const TWELVE_MONTHS = 12;

  /**
   * @var float
   */
  protected $taxAmount = 0;

  /**
   * @var float
   */
  protected $amount = 0;

  /**
   * @var DateTime|null
   */
  protected $startDate = NULL;

  /**
   * Calculate pro rated amount base on amount, duration and different.
   * Amount, Tax amount can use this function.
   *
   * @param $amount
   * @param $duration
   * @param $diff
   * @return float|int
   */
  protected function calculateProRatedAmount($amount, $duration, $diff) {
    return ($amount / $duration) * $diff;
  }

  /**
   * @return float
   */
  public function getTaxAmount() {
    return $this->taxAmount;
  }

  /**
   * @param float $taxAmount
   */
  public function setTaxAmount(float $taxAmount) {
    $this->taxAmount = $taxAmount;
  }

  /**
   * @return float
   */
  public function getAmount() {
    return $this->amount;
  }

  /**
   * @param float $amount
   */
  public function setAmount(float $amount) {
    $this->amount = $amount;
  }

  /**
   * @return DateTime|null
   */
  public function getStartDate() {
    return $this->startDate;
  }

  /**
   * @param DateTime $startDate
   */
  public function setStartDate(DateTime $startDate) {
    $this->startDate = $startDate;
  }

  /**
   * @return float|int
   */
  public function getTotalAmount() {
    return $this->amount + $this->taxAmount;
  }

}
