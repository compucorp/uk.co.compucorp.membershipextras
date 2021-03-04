<?php


/**
 * Class CRM_MembershipExtras_DTO_ScheduleInstalmentAmount
 */
class CRM_MembershipExtras_DTO_ScheduleInstalmentAmount {

  /**
   * @var float
   */
  private $amount;
  /**
   * @var float
   */
  private $taxAmount;
  /**
   * @var float
   */
  private $totalAmount;
  /**
   * @var array
   */
  private $lineItems;

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

  /**
   * @param array $lineItems
   */
  public function setLineItems(array $lineItems) {
    $this->lineItems = $lineItems;
  }

  /**
   * @return array
   */
  public function getLineItems() {
    return $this->lineItems;
  }

}
