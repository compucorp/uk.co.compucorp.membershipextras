<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtils;

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
   * @return float
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
   * @param int $financialTypeId
   * @param float $amount
   * @param float $taxAmount
   */
  protected function generateLineItem(int $financialTypeId, float $amount, float $taxAmount) {
    $roundedAmount = MoneyUtils::roundToPrecision($amount, 2);
    $roundedTaxAmount = MoneyUtils::roundToPrecision($taxAmount, 2);

    $subTotal = $roundedAmount * $this->quantity;
    $totalAmount = $subTotal + $roundedTaxAmount;
    $taxRate = $this->instalmentTaxAmountCalculator->getTaxRateByFinancialTypeId($financialTypeId);
    $scheduleInstalmentLineItem = new CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem();
    $scheduleInstalmentLineItem->setFinancialTypeId($financialTypeId);
    $scheduleInstalmentLineItem->setQuantity($this->quantity);
    $scheduleInstalmentLineItem->setUnitPrice($roundedAmount);
    $scheduleInstalmentLineItem->setSubTotal($subTotal);
    $scheduleInstalmentLineItem->setTaxRate($taxRate);
    $scheduleInstalmentLineItem->setTaxAmount($roundedTaxAmount);
    $scheduleInstalmentLineItem->setTotalAmount($totalAmount);
    $this->lineItems[] = $scheduleInstalmentLineItem;
  }

}
