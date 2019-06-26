<?php

use CRM_Member_BAO_MembershipType as MembershipType;

class CRM_MembershipExtras_Service_MembershipTypeTaxAmount {

  /**
   * Calculate Tax amount for the membership type.
   *
   * @param \CRM_Member_BAO_MembershipType $membershipType
   *
   * @return mixed
   */
  public function calculateTax(MembershipType $membershipType) {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $taxAmount = 0;
    $taxRate = CRM_Utils_Array::value($membershipType->financial_type_id, $taxRates, 0);

    if (empty($taxRate)) {
      return $taxAmount;
    }

    return ($taxRate/100) * $membershipType->minimum_fee;
  }
}
