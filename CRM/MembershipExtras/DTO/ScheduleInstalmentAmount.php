<?php


class CRM_MembershipExtras_DTO_ScheduleInstalmentAmount {

  private $amount;
  private $taxAmount;

  /**
   * @return mixed
   */
  public function getAmount() {
    return $this->amount;
  }

  /**
   * @param mixed $amount
   */
  public function setAmount($amount) {
    $this->amount = $amount;
  }

  /**
   * @return mixed
   */
  public function getTaxAmount() {
    return $this->taxAmount;
  }

  /**
   * @param mixed $taxAmount
   */
  public function setTaxAmount($taxAmount) {
    $this->taxAmount = $taxAmount;
  }

}
