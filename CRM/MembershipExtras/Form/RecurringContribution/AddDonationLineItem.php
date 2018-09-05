<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_MembershipExtras_Form_RecurringContribution_AddDonationLineItem extends CRM_MembershipExtras_Form_RecurringContribution_AddLineItem {

  /**
   * @inheritdoc
   */
  public function preProcess() {
    parent::preProcess();
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Add %1', ['1' => $this->lineItemParams['item']]) . '?');

    parent::buildQuickForm();
  }

  /**
   * @inheritdoc
   */
  protected function showOnSuccessNotifications() {
    CRM_Core_Session::setStatus(
      "{$this->lineItemParams['item']} has been added to the active order.",
      "Add {$this->lineItemParams['item']}",
      'success'
    );

    if ($this->lineItemParams['auto_renew']) {
      CRM_Core_Session::setStatus(
        "{$this->lineItemParams['item']} will now be continued in the next period.",
        "Add {$this->lineItemParams['item']}",
        'success'
      );
    }
  }

  /**
   * @inheritdoc
   */
  protected function showErrorNotification(Exception $e) {
    CRM_Core_Session::setStatus(
      "An error ocurred trying to add {$this->lineItemParams['item']} to the current recurring contribution: " . $e->getMessage(),
      "Error Adding {$this->lineItemParams['item']}",
      'error'
    );
  }

  /**
   * Creates new line item associated to the recurring contribution.
   *
   * @return array
   */
  protected function createRecurringLineItem() {
    $taxRate = $this->getTaxRateForFinancialType($this->lineItemParams['financial_type_id']);
    $taxAmount = MoneyUtilities::roundToCurrencyPrecision(
      $this->lineItemParams['amount'] * $taxRate / 100
    );

    $lineItem = civicrm_api3('LineItem', 'create', [
      'sequential' => 1,
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id' => $this->recurringContribution['id'],
      'label' => $this->lineItemParams['item'],
      'qty' => 1,
      'unit_price' => $this->lineItemParams['amount'],
      'line_total' => $this->lineItemParams['amount'],
      'financial_type_id' => $this->lineItemParams['financial_type_id'],
      'tax_amount' => $taxAmount,
    ]);

    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
      'contribution_recur_id' => $this->recurringContribution['id'],
      'line_item_id' => $lineItem['id'],
      'start_date' => $this->lineItemParams['start_date'],
      'auto_renew' => $this->lineItemParams['auto_renew'],
    ]);

    return array_shift($lineItem['values']);
  }

  /**
   * For adding new donations, contributions don't repeat.
   *
   * @param $lineItemParams
   *
   * @return array
   */
  protected function findExistingLineItemForContribution($lineItemParams) {
    return [];
  }

}
