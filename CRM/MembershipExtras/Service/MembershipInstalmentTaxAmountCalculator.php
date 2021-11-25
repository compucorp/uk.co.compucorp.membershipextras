<?php

use CRM_Member_BAO_MembershipType as MembershipType;

/**
 * Class CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
 */
class CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator {

  /**
   * @var array
   */
  private $taxRates;

  /**
   * CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator constructor.
   */
  public function __construct() {
    $this->taxRates = CRM_Core_PseudoConstant::getTaxRates();
  }

  /**
   * Calculates Tax amount for the membership type.
   *
   * @param \CRM_Member_BAO_MembershipType $membershipType
   * @param float $membershipTypeAmount
   *
   * @return mixed
   */
  public function calculateByMembershipType(MembershipType $membershipType, $membershipTypeAmount = NULL) {
    $membershipTypeAmount = $membershipTypeAmount ? $membershipTypeAmount : $membershipType->minimum_fee;
    $taxRate = $this->getTaxRateByFinancialTypeId($membershipType->financial_type_id);

    return $this->calculateTaxAmount($membershipTypeAmount, $taxRate);
  }

  /**
   * Calculates Tax amount for the price field value
   *
   * @param array $priceFieldValue
   * @return float|int
   */
  public function calculateByPriceFieldValue(array $priceFieldValue) {
    $taxRate = $this->getTaxRateByFinancialTypeId($priceFieldValue['financial_type_id']);

    return $this->calculateTaxAmount($priceFieldValue['amount'], $taxRate);
  }

  /**
   * Gets Tax Rate by Financial Type Id
   *
   * @params int $id
   * @return float
   */
  public function getTaxRateByFinancialTypeId(int $id) {
    return CRM_Utils_Array::value($id, $this->taxRates, 0);
  }

  /**
   * Calculate tax amount from amount and tax rate
   *
   * @param $amount
   * @param $taxRate
   * @return float|int
   */
  private function calculateTaxAmount($amount, $taxRate) {
    $taxAmount = 0;
    if (empty($taxRate)) {
      return $taxAmount;
    }

    return ($taxRate / 100) * $amount;
  }

}
