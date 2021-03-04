<?php


/**
 * Class CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem
 */
class CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem {

  /**
   * @var int
   */
  private $financialTypeId;
  /**
   * @var int
   */
  private $quantity;
  /**
   * @var float
   */
  private $unitPrice;
  /**
   * @var float
   */
  private $subTotal;
  /**
   * @var float
   */
  private $taxRate;
  /**
   * @var float
   */
  private $taxAmount;
  /**
   * @var float
   */
  private $totalAmount;

  /**
   * @return int
   */
  public function getFinancialTypeId() {
    return $this->financialTypeId;
  }

  /**
   * @param int $financialTypeId
   */
  public function setFinancialTypeId($financialTypeId) {
    $this->financialTypeId = $financialTypeId;
  }

  /**
   * @return int
   */
  public function getQuantity() {
    return $this->quantity;
  }

  /**
   * @param int $quantity
   */
  public function setQuantity($quantity) {
    $this->quantity = $quantity;
    return $this;
  }

  /**
   * @return float
   */
  public function getUnitPrice() {
    return $this->unitPrice;
  }

  /**
   * @param float $unitPrice
   */
  public function setUnitPrice($unitPrice) {
    $this->unitPrice = $unitPrice;
  }

  /**
   * @return float
   */
  public function getSubTotal() {
    return $this->subTotal;
  }

  /**
   * @param float $subTotal
   */
  public function setSubTotal($subTotal) {
    $this->subTotal = $subTotal;
  }

  /**
   * @return float
   */
  public function getTaxRate() {
    return $this->taxRate;
  }

  /**
   * @param float $taxRate
   */
  public function setTaxRate($taxRate) {
    $this->taxRate = $taxRate;
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
  public function setTaxAmount($taxAmount) {
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
  public function setTotalAmount($totalAmount) {
    $this->totalAmount = $totalAmount;
  }

}
