<?php

/**
 * Implements hook to be run before a membership is created/edited.
 */
class CRM_MembershipExtras_Hook_Pre_MembershipCreate {

  /**
   * Parameters that will be used to create the membership.
   *
   * @var array
   */
  private $params;

  public function __construct(&$params) {
    $this->params = &$params;
  }

  /**
   * Preprocesses parameters used for Membership operations.
   */
  public function preProcess() {
    $this->fixTaxAmount();
    $this->fixLineItems();
  }

  /**
   * Recalculates tax amount tax rate according to selected financial type, as
   * this can be broken if paying using payment plan or altering total value.
   */
  private function fixTaxAmount() {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = CRM_Utils_Array::value($this->params['financial_type_id'], $taxRates, 0);

    $this->params['tax_amount'] = ($this->params['total_amount'] * ($rate / 100)) / (1 + ($rate / 100));
  }

  /**
   * Recalculates line total, unit price and tax amount for each line item, as
   * these can get broken when paying with a pyment plan or using a custom total
   * value.
   */
  private function fixLineItems() {
    foreach ($this->params['lineItems'] as $types) {
      foreach ($types as &$line) {
        $total = $line['line_total'] + $line['tax_amount'];
        $line['tax_amount'] = ($total * ($line['tax_rate'] / 100)) / (1 + ($line['tax_rate'] / 100));
        $line['line_total'] = $total - $line['tax_amount'];
        $line['unit_price'] = $line['line_total'] / $line['qty'];
      }
    }
  }

}
