<?php

/**
 * Class CRM_MembershipExtras_Service_MembershipPeriodType_AbstractPeriodTypeCalculator
 */
abstract class CRM_MembershipExtras_Service_MembershipPeriodType_AbstractPeriodTypeCalculator {

  /**
   * @var float
   */
  protected $taxAmount = 0;

  /**
   * @var float
   */
  protected $amount = 0;

  /**
   * @var array
   */
  protected $lineItems = [];

  /**
   * @var int
   */
  protected $quantity = 1;

  /**
   * @var \CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
   */
  protected $instalmentTaxAmountCalculator;

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

  /**
   * @return flaot
   */
  public function getTotalAmount() {
    return $this->amount + $this->taxAmount;
  }

  /**
   * @return array
   */
  public function getLineItems() {
    return $this->lineItems;
  }

  /**
   * Generates line item for each instalment
   *
   * @param int $fiancialTypeId
   * @param float $amount
   * @param float $taxAmount
   */
  protected function generateLineItem(int $fiancialTypeId, float $amount, float $taxAmount) {
    $subTotal = $amount * $this->quantity;
    $totalAmount = $subTotal + $taxAmount;
    $taxRate = $this->instalmentTaxAmountCalculator->getTaxRateByFinancialTypeId($fiancialTypeId);
    $scheduleInstalmentLineItem = new CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem();
    $scheduleInstalmentLineItem->setFinancialTypeId($fiancialTypeId);
    $scheduleInstalmentLineItem->setQuantity($this->quantity);
    $scheduleInstalmentLineItem->setUnitPrice($amount);
    $scheduleInstalmentLineItem->setSubTotal($subTotal);
    $scheduleInstalmentLineItem->setTaxRate($taxRate);
    $scheduleInstalmentLineItem->setTaxAmount($taxAmount);
    $scheduleInstalmentLineItem->setTotalAmount($totalAmount);
    $this->lineItems[] = $scheduleInstalmentLineItem;
  }

}
