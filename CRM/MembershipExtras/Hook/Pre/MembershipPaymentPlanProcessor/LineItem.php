<?php

use CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_AbstractProcessor as AbstractProcessor;

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
   */
  public function alterLineItemParameters() {
    $this->params['line_total'] = $this->calculateSingleInstalmentAmount($this->params['line_total']);
    $this->params['unit_price'] = $this->calculateSingleInstalmentAmount($this->params['unit_price']);

    if (!empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($this->params['tax_amount']);
    }
  }

}
