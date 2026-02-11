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
   * @var CRM_MembershipExtras_DTO_ScheduleInstalmentAmount|null
   */
  private $lastInstalmentAmount;

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
    $lineItems = $this->calculator->getLineItems();
    if ($instalmentCount == 1) {
      $instalmentAmount = $this->buildInstalmentAmount($lineItems);
      $this->lastInstalmentAmount = $this->buildInstalmentAmount($lineItems);

      return $instalmentAmount;
    }

    $instalmentLineItems = $this->setLineItemsAmountPerInstalment($lineItems, $instalmentCount);
    $instalmentAmount = $this->buildInstalmentAmount($instalmentLineItems['regular']);
    $this->lastInstalmentAmount = $this->buildInstalmentAmount($instalmentLineItems['last']);

    return $instalmentAmount;

  }

  /**
   * Calculates Line item amount per instalment
   *
   * @param array $lineItems
   * @param int $instalmentCount
   * @return array
   */
  private function setLineItemsAmountPerInstalment(array $lineItems, int $instalmentCount) {
    $newInstalmentLineItems = ['regular' => [], 'last' => []];
    foreach ($lineItems as $lineItem) {
      $regularLineItem = clone $lineItem;
      $lastLineItem = clone $lineItem;

      $instalmentUnitPrice = $this->calculateSingleInstalmentAmount($lineItem->getUnitPrice(), $instalmentCount);
      $instalmentSubTotal = $this->calculateSingleInstalmentAmount($lineItem->getSubTotal(), $instalmentCount);
      $instalmentTaxAmount = $this->calculateSingleInstalmentAmount($lineItem->getTaxAmount(), $instalmentCount);

      $lastInstalmentUnitPrice = MoneyUtils::roundToPrecision($lineItem->getUnitPrice() - ($instalmentUnitPrice * ($instalmentCount - 1)), 2);
      $lastInstalmentSubTotal = MoneyUtils::roundToPrecision($lineItem->getSubTotal() - ($instalmentSubTotal * ($instalmentCount - 1)), 2);
      $lastInstalmentTaxAmount = MoneyUtils::roundToPrecision($lineItem->getTaxAmount() - ($instalmentTaxAmount * ($instalmentCount - 1)), 2);

      $instalmentTotal = MoneyUtils::roundToPrecision($instalmentSubTotal + $instalmentTaxAmount, 2);
      $lastInstalmentTotal = MoneyUtils::roundToPrecision($lastInstalmentSubTotal + $lastInstalmentTaxAmount, 2);

      $regularLineItem->setUnitPrice($instalmentUnitPrice);
      $regularLineItem->setSubTotal($instalmentSubTotal);
      $regularLineItem->setTaxAmount($instalmentTaxAmount);
      $regularLineItem->setTotalAmount($instalmentTotal);

      $lastLineItem->setUnitPrice($lastInstalmentUnitPrice);
      $lastLineItem->setSubTotal($lastInstalmentSubTotal);
      $lastLineItem->setTaxAmount($lastInstalmentTaxAmount);
      $lastLineItem->setTotalAmount($lastInstalmentTotal);

      $newInstalmentLineItems['regular'][] = $regularLineItem;
      $newInstalmentLineItems['last'][] = $lastLineItem;
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
    $nonMembershipPriceFieldValueLastLineItems = [];
    foreach ($nonMembershipPriceFieldValues as $priceFieldValue) {
      $quantity = $priceFieldValue['quantity'];
      $amount = $priceFieldValue['values']['amount'];
      $subTotal = (float) $amount * (float) $quantity;
      $totalNonMembershipPriceFieldValueAmount += $subTotal;
      $salesTax = $instalmentTaxAmountCalculator->calculateByPriceFieldValue($priceFieldValue['values']) * (float) $quantity;
      $totalNonMembershipPriceFieldValueTaxAmount += $salesTax;

      $scheduleInstalmentLineItem = new CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem();
      $lastScheduleInstalmentLineItem = new CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem();
      $financialTypeId = $priceFieldValue['values']['financial_type_id'];
      $scheduleInstalmentLineItem->setFinancialTypeId($financialTypeId);
      $scheduleInstalmentLineItem->setQuantity($quantity);
      $lineItemUnitPrice = $this->calculateSingleInstalmentAmount($amount, $instalmentCount);
      $lineItemSubTotal = $this->calculateSingleInstalmentAmount($subTotal, $instalmentCount);
      $lineItemTaxAmount = $this->calculateSingleInstalmentAmount($salesTax, $instalmentCount);
      $scheduleInstalmentLineItem->setUnitPrice($lineItemUnitPrice);
      $scheduleInstalmentLineItem->setSubTotal($lineItemSubTotal);
      $scheduleInstalmentLineItem->setTaxRate($instalmentTaxAmountCalculator->getTaxRateByFinancialTypeId($financialTypeId));
      $scheduleInstalmentLineItem->setTaxAmount($lineItemTaxAmount);
      $lineItemTotalAmount = $scheduleInstalmentLineItem->getSubTotal() + $scheduleInstalmentLineItem->getTaxAmount();
      $scheduleInstalmentLineItem->setTotalAmount($lineItemTotalAmount);

      $lastScheduleInstalmentLineItem->setFinancialTypeId($financialTypeId);
      $lastScheduleInstalmentLineItem->setQuantity($quantity);
      $lastScheduleInstalmentLineItem->setUnitPrice(MoneyUtils::roundToPrecision($amount - ($lineItemUnitPrice * ($instalmentCount - 1)), 2));
      $lastScheduleInstalmentLineItem->setSubTotal(MoneyUtils::roundToPrecision($subTotal - ($lineItemSubTotal * ($instalmentCount - 1)), 2));
      $lastScheduleInstalmentLineItem->setTaxRate($instalmentTaxAmountCalculator->getTaxRateByFinancialTypeId($financialTypeId));
      $lastScheduleInstalmentLineItem->setTaxAmount(MoneyUtils::roundToPrecision($salesTax - ($lineItemTaxAmount * ($instalmentCount - 1)), 2));
      $lastScheduleInstalmentLineItem->setTotalAmount($lastScheduleInstalmentLineItem->getSubTotal() + $lastScheduleInstalmentLineItem->getTaxAmount());

      $nonMembershipPriceFieldValueLineItems[] = $scheduleInstalmentLineItem;
      $nonMembershipPriceFieldValueLastLineItems[] = $lastScheduleInstalmentLineItem;
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

    if ($this->lastInstalmentAmount) {
      $lastLineItems = $this->lastInstalmentAmount->getLineItems();
      foreach ($nonMembershipPriceFieldValueLastLineItems as $priceFieldLineItem) {
        $lastLineItems[] = $priceFieldLineItem;
      }
      $this->lastInstalmentAmount = $this->buildInstalmentAmount($lastLineItems);
    }
  }

  /**
   * Gets calculated last instalment amount.
   */
  public function getLastInstalmentAmount(): ?CRM_MembershipExtras_DTO_ScheduleInstalmentAmount {
    return $this->lastInstalmentAmount;
  }

  /**
   * Builds instalment amount object for the provided line items.
   */
  private function buildInstalmentAmount(array $lineItems): CRM_MembershipExtras_DTO_ScheduleInstalmentAmount {
    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalmentAmount();
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
