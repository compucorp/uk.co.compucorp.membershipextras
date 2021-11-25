<?php

use CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_AbstractProcessor as AbstractProcessor;

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_LineItem extends AbstractProcessor {

  use CRM_MembershipExtras_Helper_InstalmentCalculatorTrait;

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
      $this->handleNonMembershipTypeLineItem();
    }
  }

  /**
   * Adjusts line items when line item has membership type.
   */
  private function handleMembershipTypeLineItem() {
    $lineItemMembershipType = CRM_Member_BAO_MembershipType::findById($this->params['membership_type_id']);
    if ($this->isUsingPriceSet()) {
      //Since line item amount can be different from membership type amount
      //Make sure we are using line item total amount when using PriceSet
      $lineItemMembershipType->minimum_fee = $this->params['line_total'];
    }
    $instalmentAmountCalculator = $this->getInstalmentAmountCalculator([$lineItemMembershipType], $lineItemMembershipType->period_type);
    $instalmentAmount = $instalmentAmountCalculator->calculateInstalmentAmount($this->getLineItemInstalmentCount($lineItemMembershipType));
    $this->params['line_total'] = $instalmentAmount->getAmount();
    $this->params['unit_price'] = $instalmentAmount->getAmount();
    $this->params['tax_amount'] = $instalmentAmount->getTaxAmount();
  }

  /**
   * @throws Exception
   */
  private function getLineItemInstalmentCount($lineItemMembershipType) {
    if ($lineItemMembershipType->period_type == 'fixed') {
      return $this->getInstalmentCount($lineItemMembershipType);
    }

    return $this->instalmentsCount;
  }

  /**
   * Adjusts line items when line item is a non membership type.
   */
  private function handleNonMembershipTypeLineItem() {
    $instalmentCount = $this->getInstalmentCount();

    $this->params['line_total'] = $this->calculateSingleInstalmentAmount($this->params['line_total'], $instalmentCount);
    $this->params['unit_price'] = $this->calculateSingleInstalmentAmount($this->params['unit_price'], $instalmentCount);
    if (!empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($this->params['tax_amount'], $instalmentCount);
    }
  }

}
