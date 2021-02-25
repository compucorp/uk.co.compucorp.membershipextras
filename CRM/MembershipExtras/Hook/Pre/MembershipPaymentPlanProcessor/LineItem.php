<?php

use CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_AbstractProcessor as AbstractProcessor;
use CRM_Member_BAO_MembershipType as MembershipType;

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_LineItem extends AbstractProcessor {

  public function __construct(&$params) {
    $this->params = &$params;
    $this->assignInstalmentDetails();
  }

  /**
   * Alters the contribution 'to be created' line item parameters
   * before saving it.
   *
   * We here adjust the line total, unit price and tax amount
   * of the line item to be inline with the new contribution amount.
   *
   */
  public function alterLineItemParameters() {
    if (isset($this->params['membership_type_id'])) {
      $this->handleMembershipTypeLineItem();
    }
    else {
      $this->calculateLineItemAmounts();
    }
  }

  /**
   * Adjusts line items when line item has membeship type.
   */
  private function handleMembershipTypeLineItem() {
    $lineItemMembershipType = CRM_Member_BAO_MembershipType::findById($this->params['membership_type_id']);
    if ($this->isMonthlyPaymentWithFixedMembershipPriceSet($lineItemMembershipType)) {
      $instalmentCount = $this->getInstalmentCountForFixedMembeship($lineItemMembershipType);
      $this->calculateProRataLineItemAmounts($lineItemMembershipType, $instalmentCount);
    }
    elseif ($this->isAnnualPaymentWithFixedMembershipPriceSet($lineItemMembershipType)) {
      $this->calculateProRataLineItemAmounts($lineItemMembershipType);
    }
    elseif ($this->isNonPriceSetFixedMembership($lineItemMembershipType)) {
      $instalmentCount = $this->getInstalmentCountForFixedMembeship($lineItemMembershipType);
      $this->calculateLineItemAmounts($instalmentCount);
    }
    else {
      $this->calculateLineItemAmounts();
    }
  }

  /**
   * Checks if line item is price set, membemrship type is fixed and payment schedule is monthly.
   *
   * @param CRM_Member_BAO_MembershipType $membershipType
   */
  private function isMonthlyPaymentWithFixedMembershipPriceSet($membershipType) {
    $isMonthlySchedule = $this->paymentPlanSchedule == CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::MONTHLY;
    $isFixedMembershipType = $membershipType->period_type == 'fixed';
    return $this->isUsingPriceSet() && $isFixedMembershipType && $isMonthlySchedule;
  }

  /**
   * Checks if line item is price set, membemrship type is fixed and payment schedule is annual.
   *
   * @param \CRM_Member_BAO_MembershipType $membershipType
   */
  private function isAnnualPaymentWithFixedMembershipPriceSet($membershipType) {
    $isNonMonthlySchedule = $this->paymentPlanSchedule == CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::ANNUAL;
    $isFixedMembershipType = $membershipType->period_type == 'fixed';
    return $this->isUsingPriceSet() && $isFixedMembershipType && $isNonMonthlySchedule;
  }

  /**
   * Checks if line item is fixed membership and not using price set.
   *
   * @param \CRM_Member_BAO_MembershipType $membershipType
   */
  private function isNonPriceSetFixedMembership($membershipType) {
    $isFixedMembershipType = $membershipType->period_type == 'fixed';
    return !$this->isUsingPriceSet() && $isFixedMembershipType;
  }

  /**
   * Calcuclates line item amounts and assign amounts to line item.
   *
   * @param int $instalmentCount
   */
  private function calculateLineItemAmounts($instalmentCount = NULL) {
    $this->params['line_total'] = $this->calculateSingleInstalmentAmount($this->params['line_total'], $instalmentCount);
    $this->params['unit_price'] = $this->calculateSingleInstalmentAmount($this->params['unit_price'], $instalmentCount);
    if (!empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($this->params['tax_amount'], $instalmentCount);
    }
  }

  /**
   * Calculates pro rata amounts for line item for fixed period membership type
   *
   * @param \CRM_Member_BAO_MembershipType $membershipType
   * @param int $instalmentCount
   */
  private function calculateProRataLineItemAmounts(MembershipType $membershipType, int $instalmentCount = NULL) {
    if (is_null($instalmentCount)) {
      $instalmentCount = $this->instalmentsCount;
    }
    //Make sure we pro rated using line item total amount
    $membershipType->minimum_fee = $this->params['unit_price'];
    $instalmentAmount = $this->getProRatedInstalmentAmount([$membershipType]);
    $this->params['line_total'] = $this->calculateSingleInstalmentAmount($instalmentAmount->getCalculator()->getAmount(), $instalmentCount);
    $this->params['unit_price'] = $this->calculateSingleInstalmentAmount($instalmentAmount->getCalculator()->getAmount(), $instalmentCount);
    $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($instalmentAmount->getCalculator()->getTaxAmount(), $instalmentCount);
  }

}
