<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as Calculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtils;

class CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator {

  use CRM_MembershipExtras_Helper_InstalmentCalculatorTrait;

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

  /**
   * Calculates instalment amount from line items.
   *
   * We need to ensure that sub total, tax and total amounts
   * are calculate from line items to avoid precision issue
   * when amount is divided by instalment amount.
   *
   * @param int $instalmentCount
   * @return CRM_MembershipExtras_DTO_ScheduleInstalmentAmount
   */
  public function calculateInstalmentAmount(int $instalmentCount) {
    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalmentAmount();

    $lineItems = $this->calculator->getLineItems();
    if ($instalmentCount != 1) {
      $lineItems = $this->setLineItemsAmountPerInstalment($lineItems, $instalmentCount);
    }
    $instalment->setLineItems($lineItems);

    $amount = 0.0;
    $taxAmount = 0.0;
    $totalAmount = 0.0;
    foreach ($lineItems as $lineItem) {
      $amount += $lineItem->getSubTotal();
      $taxAmount += $lineItem->getTaxAmount();
      $totalAmount += $lineItem->getTotalAmount();
    }
    $instalment->setAmount($amount);
    $instalment->setTaxAmount($taxAmount);
    $instalment->setTotalAmount($totalAmount);

    return $instalment;

  }

  /**
   * Calculates Line item amount per instalment
   *
   * @param array $lineItems
   * @param int $instalmentCount
   * @return array
   */
  private function setLineItemsAmountPerInstalment(array $lineItems, int $instalmentCount) {
    $newInstalmentLineItems = [];
    foreach ($lineItems as $lineItem) {
      $instalmentUnitPrice = $this->calculateSingleInstalmentAmount($lineItem->getUnitPrice(), $instalmentCount);
      $instalmentSubTotal = $this->calculateSingleInstalmentAmount($lineItem->getSubTotal(), $instalmentCount);
      $instalmentTaxAmount = $this->calculateSingleInstalmentAmount($lineItem->getTaxAmount(), $instalmentCount);
      $instalmentTotal = MoneyUtils::roundToPrecision($instalmentSubTotal + $instalmentTaxAmount, 2);

      $lineItem->setUnitPrice($instalmentUnitPrice);
      $lineItem->setSubTotal($instalmentSubTotal);
      $lineItem->setTaxAmount($instalmentTaxAmount);
      $lineItem->setTotalAmount($instalmentTotal);

      array_push($newInstalmentLineItems, $lineItem);
    }

    return $newInstalmentLineItems;
  }

  /**
   * Applies amount, tax amount from Non Membership Price Field Value.
   * to instalment amount
   *
   * @param CRM_MembershipExtras_DTO_ScheduleInstalmentAmount $instalmentAmount
   * @param array $nonMembershipPriceFieldValues
   * @param CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator $instalmentTaxAmountCalculator
   * @param int $instalmentCount
   */
  public function setNonMembershipPriceFieldValueAmount(
    CRM_MembershipExtras_DTO_ScheduleInstalmentAmount $instalmentAmount,
    array $nonMembershipPriceFieldValues,
    CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator $instalmentTaxAmountCalculator,
    int $instalmentCount
  ) {
    $totalNonMembershipPriceFieldValueAmount = 0;
    $totalNonMembershipPriceFieldValueTaxAmount = 0;
    $nonMembershipPriceFieldValueLineItems = [];
    foreach ($nonMembershipPriceFieldValues as $priceFieldValue) {
      $quantity = $priceFieldValue['quantity'];
      $amount = $priceFieldValue['values']['amount'];
      $subTotal = (float) $amount * (float) $quantity;
      $totalNonMembershipPriceFieldValueAmount += $subTotal;
      $salesTax = $instalmentTaxAmountCalculator->calculateByPriceFieldValue($priceFieldValue['values']) * (float) $quantity;
      $totalNonMembershipPriceFieldValueTaxAmount += $salesTax;

      $scheduleInstalmentLineItem = new CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem();
      $financialTypeId = $priceFieldValue['values']['financial_type_id'];
      $scheduleInstalmentLineItem->setFinancialTypeId($financialTypeId);
      $scheduleInstalmentLineItem->setQuantity($quantity);
      $scheduleInstalmentLineItem->setUnitPrice($this->calculateSingleInstalmentAmount($amount, $instalmentCount));
      $scheduleInstalmentLineItem->setSubTotal($this->calculateSingleInstalmentAmount($subTotal, $instalmentCount));
      $scheduleInstalmentLineItem->setTaxRate($instalmentTaxAmountCalculator->getTaxRateByFinancialTypeId($financialTypeId));
      $scheduleInstalmentLineItem->setTaxAmount($this->calculateSingleInstalmentAmount($salesTax, $instalmentCount));
      $lineItemTotalAmount = $scheduleInstalmentLineItem->getSubTotal() + $scheduleInstalmentLineItem->getTaxAmount();
      $scheduleInstalmentLineItem->setTotalAmount($lineItemTotalAmount);
      $nonMembershipPriceFieldValueLineItems[] = $scheduleInstalmentLineItem;
    }

    $totalNonMembershipPriceFieldValueAmountPerInstalment = $this->calculateSingleInstalmentAmount($totalNonMembershipPriceFieldValueAmount, $instalmentCount);
    $newInstalmentAmount = $totalNonMembershipPriceFieldValueAmountPerInstalment + $instalmentAmount->getAmount();
    $totalNonMembershipPriceFieldValueTaxAmountPerInstalment = $this->calculateSingleInstalmentAmount($totalNonMembershipPriceFieldValueTaxAmount, $instalmentCount);
    $newInstalmentTaxAmount = $totalNonMembershipPriceFieldValueTaxAmountPerInstalment + $instalmentAmount->getTaxAmount();

    $instalmentAmount->setAmount($newInstalmentAmount);
    $instalmentAmount->setTaxAmount($newInstalmentTaxAmount);

    $nonMembershipPriceFieldValueTotalAmount = $totalNonMembershipPriceFieldValueAmountPerInstalment + $totalNonMembershipPriceFieldValueTaxAmountPerInstalment;
    $currentTotalAmount = $instalmentAmount->getTotalAmount();
    $totalAmount = $nonMembershipPriceFieldValueTotalAmount + $currentTotalAmount;
    $instalmentAmount->setTotalAmount($totalAmount);

    if (empty($nonMembershipPriceFieldValueLineItems)) {
      return;
    }

    $currentLineItems = $instalmentAmount->getLineItems();
    foreach ($nonMembershipPriceFieldValueLineItems as $priceFieldLineItem) {
      array_push($currentLineItems, $priceFieldLineItem);
    }
    $instalmentAmount->setLineItems($currentLineItems);
  }

  /**
   * Calculates Instalments Sub Total Amount.
   *
   * @param array $instalments
   * @return float
   */
  public function getInstalmentsSubTotalAmount(array $instalments) {
    $subTotalAmount = 0.0;
    foreach ($instalments as $instalment) {
      $subTotalAmount += $instalment->getInstalmentAmount()->getAmount();
    }
    return $subTotalAmount;
  }

  /**
   * Calculates Instalments Tax Amount.
   *
   * @param array $instalments
   * @return float
   */
  public function getInstalmentsTaxAmount(array $instalments) {
    $taxAmount = 0.0;
    foreach ($instalments as $instalment) {
      $taxAmount += $instalment->getInstalmentAmount()->getTaxAmount();
    }
    return $taxAmount;
  }

  /**
   * Calculates instalments total amount.
   *
   * @param array $instalments
   * @return float
   */
  public function getInstalmentsTotalAmount(array $instalments) {
    $totalAmount = 0.0;
    foreach ($instalments as $instalment) {
      $totalAmount += $instalment->getInstalmentAmount()->getTotalAmount();
    }
    return $totalAmount;
  }

}
