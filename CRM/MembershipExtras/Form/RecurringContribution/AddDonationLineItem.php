<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_MembershipExtras_Form_RecurringContribution_AddDonationLineItem extends CRM_MembershipExtras_Form_RecurringContribution_AddLineItem {

  public function preProcess() {
    parent::preProcess();
  }

  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Add %1', ['1' => $this->lineItemParams['item']]) . '?');

    parent::buildQuickForm();
  }

  public function postProcess() {
    $tx = new CRM_Core_Transaction();

    try {
      $recurringLineItem = $this->createRecurringLineItem();
      $this->addLineItemToPendingContributions($recurringLineItem);

      CRM_Core_Session::setStatus(
        "{$this->membershipType['name']} has been added to the active order.",
        "Add {$this->membershipType['name']}",
        'success'
      );

      if ($this->lineItemParams['auto_renew']) {
        CRM_Core_Session::setStatus(
          "{$this->membershipType['name']} will now be continued in the next period.",
          "Add {$this->membershipType['name']}",
          'success'
        );
      }
    } catch (Exception $e) {
      $tx->rollback();

      CRM_Core_Session::setStatus(
        "An error ocurred trying to add {$this->membershipType['name']} to the current recurring contribution: " . $e->getMessage(),
        "Error Adding {$this->membershipType['name']}",
        'error'
      );
    }
  }

  /**
   * Creates new line item associated to the recurring contribution.
   *
   * @return array
   */
  protected function createRecurringLineItem() {
    $taxRate = $this->getTaxRateForFinancialType($this->lineItemParams['financial_type_id']);
    $taxAmount = round($this->lineItemParams['amount'] * $taxRate / 100, 2);

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
