<?php


class CRM_MembershipExtras_DTO_ScheduleInstalmentAmount {

  private $amount;
  private $taxAmount;
  private $totalAmount;

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
  public function getTotalAmount() {
    return $this->totalAmount;
  }

  /**
   * @param float $totalAmount
   */
  public function setTotalAmount(float $totalAmount) {
    $this->totalAmount = $totalAmount;
  }

}
