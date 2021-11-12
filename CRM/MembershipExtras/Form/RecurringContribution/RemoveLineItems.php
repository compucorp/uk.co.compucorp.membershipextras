<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Form controller class to allow removal of line items from a recurring
 * contribution.
 */
class CRM_MembershipExtras_Form_RecurringContribution_RemoveLineItems extends CRM_Core_Form {

  private $recurringContributionID;
  private $lineItemID;
  private $recurringLineItemData = [];
  private $lineItemEndDate;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->recurringContributionID = CRM_Utils_Request::retrieve('contribution_recur_id', 'Positive', $this);
    $this->lineItemID = CRM_Utils_Request::retrieve('line_item_id', 'Positive', $this);
    $this->recurringLineItemData = $this->getRecurringLineItemData();
    $this->lineItemEndDate = date('Y-m-d');
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    return [
      'end_date' => date('Y-m-d'),
    ];
  }

  /**
   * Returns data for the line item identified by the ID sent in the request.
   *
   * @return array
   */
  private function getRecurringLineItemData() {
    $result = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContributionID,
      'line_item_id' => $this->lineItemID,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
    ]);

    // Flatten array
    if ($result['count'] > 0) {
      $lineItemData = $result['values'][0];
      $lineDetails = $lineItemData['api.LineItem.getsingle'];
      unset($lineItemData['api.LineItem.getsingle']);
      unset($lineDetails['id']);

      return array_merge($lineItemData, $lineDetails);
    }

    return [];
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    $lineItemLabel = $this->recurringLineItemData['label'];
    CRM_Utils_System::setTitle(E::ts('Remove ' . $lineItemLabel . '?'));

    $this->assign('lineItem', $this->recurringLineItemData);

    $this->add('checkbox', 'adjust_end_date', ts('Adjust End Date?'));
    $this->add(
      'datepicker',
      'end_date',
      ts('End Date'),
      '',
      FALSE,
      ['minDate' => time(), 'time' => FALSE]
    );

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Apply'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
        'isDefault' => FALSE,
      ],
    ]);
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    if ($this->getElementValue('adjust_end_date')) {
      $this->lineItemEndDate = $this->getElementValue('end_date');
    }

    $tx = new CRM_Core_Transaction();
    try {
      if ($this->isLineItemAMembership()) {
        $this->cancelMembership();
      }

      $this->adjustPendingContributions();
      $this->updateRecurringLineItem();
      $this->updateRecurringContributionAmount();

      CRM_Core_Session::setStatus(
        "{$this->recurringLineItemData['label']} has been removed from the active order.",
        "Remove {$this->recurringLineItemData['label']}",
        'success'
      );

      CRM_Core_Session::setStatus(
        "{$this->recurringLineItemData['label']} should no longer be continued in the next period.",
        "Remove {$this->recurringLineItemData['label']}",
        'success'
      );
    }
    catch (Exception $e) {
      $tx->rollback();

      CRM_Core_Session::setStatus(
        "An error ocurred trying to remove {$this->recurringLineItemData['label']} from the current recurring contribution: " . $e->getMessage(),
        "Error Removing {$this->recurringLineItemData['label']}",
        'error'
      );
    }
  }

  /**
   * Updates the amount of the recurring contribution checking list of line
   * items associated to it.
   */
  private function updateRecurringContributionAmount() {
    $totalAmount = $this->calculateRecurringContributionTotalAmount();

    civicrm_api3('ContributionRecur', 'create', [
      'sequential' => 1,
      'amount' => $totalAmount,
      'id' => $this->recurringContributionID,
    ]);
  }

  /**
   * Calculates amount for current recurring contribution from related line
   * items.
   */
  private function calculateRecurringContributionTotalAmount() {
    $totalAmount = 0;

    $result = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContributionID,
      'start_date' => ['IS NOT NULL' => 1],
      'end_date' => ['IS NULL' => 1],
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
    ]);

    if ($result['count'] > 0) {
      foreach ($result['values'] as $lineItemData) {
        $totalAmount += $lineItemData['api.LineItem.getsingle']['line_total'];
        $totalAmount += $lineItemData['api.LineItem.getsingle']['tax_amount'];
      }
    }

    return MoneyUtilities::roundToCurrencyPrecision($totalAmount);
  }

  /**
   * Checks if line item being removed is for a membership.
   *
   * @return bool
   */
  private function isLineItemAMembership() {
    return CRM_MembershipExtras_Service_FinancialTransactionManager::isMembership($this->recurringLineItemData['line_item_id']);
  }

  /**
   * Cancels membership identified by entity_id of line item by setting selected
   * date as the membership's end date.
   */
  private function cancelMembership() {
    civicrm_api3('Membership', 'create', [
      'id' => $this->recurringLineItemData['entity_id'],
      'status_override_end_date' => '',
      'contribution_recur_id' => '',
      'end_date' => $this->lineItemEndDate,
    ]);
  }

  /**
   * Makes adjustment for remaining contributions for payment plan, cancelling
   * line items and adjusting amounts.
   */
  private function adjustPendingContributions() {
    $pendingContributions = $this->getPendingContributions();
    if (count($pendingContributions) < 1) {
      throw new CRM_Core_Exception('No pending installments found for the payment plan after the selected end date: ' . $this->lineItemEndDate . '.');
    }

    foreach ($pendingContributions as $contribution) {
      $lineItemBefore = $this->getCorrespondingContributionLineItem($contribution['id']);
      if (!isset($lineItemBefore['id']) || empty($lineItemBefore['id'])) {
        continue;
      }

      // change total_price and qty of current line item to 0
      civicrm_api3('LineItem', 'create', [
        'id' => $lineItemBefore['id'],
        'qty' => 0,
        'participant_count' => 0,
        'line_total' => 0.00,
        'tax_amount' => 0.00,
      ]);

      // calculate balance, tax and paid amount later used to adjust transaction
      $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($contribution['id']);
      $taxAmount = CRM_MembershipExtras_Service_FinancialTransactionManager::calculateTaxAmountTotalFromContributionID($contribution['id']);

      // Record adjusted amount by updating contribution info
      CRM_MembershipExtras_Service_FinancialTransactionManager::recordAdjustedAmount($contribution, $updatedAmount, $taxAmount);

      // Record financial item on cancellation of lineitem
      CRM_MembershipExtras_Service_FinancialTransactionManager::insertFinancialItemOnLineItemDeletion($lineItemBefore);

      // Remove line item from contribution
      civicrm_api3('LineItem', 'create', [
        'id' => $lineItemBefore['id'],
        'label' => $lineItemBefore['label'] . " - Line removed from contribution [{$lineItemBefore['contribution_id']}]",
        'contribution_id' => 'null',
      ]);

      // Remove the link between the membership and the contribution
      $query = "DELETE FROM civicrm_membership_payment
                WHERE membership_id = {$this->recurringLineItemData['entity_id']}
                AND contribution_id = {$contribution['id']}";
      CRM_Core_DAO::executeQuery($query);
    }
  }

  /**
   * Returns an array with the information of pending recurring contributions
   * for the current recurring contribution.
   *
   * @return array
   */
  private function getPendingContributions() {
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContributionID,
      'contribution_status_id' => 'Pending',
      'receive_date' => ['>=' => $this->lineItemEndDate],
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return [];
  }

  /**
   * Returns line item to be deleted from the contribution.
   *
   * Obtains information about the line item that corresponds to the one being
   * deleted from the recurring contribution for the given contribution ID.
   *
   * @param int $contributionID
   *
   * @return array
   */
  private function getCorrespondingContributionLineItem($contributionID) {
    $entityID = $this->recurringLineItemData['entity_table'] == 'civicrm_contribution' ? $contributionID : $this->recurringLineItemData['entity_id'];

    $lineItem = [];
    try {
      $apiResponse = civicrm_api3('LineItem', 'get', [
        'sequential' => 1,
        'entity_table' => $this->recurringLineItemData['entity_table'],
        'contribution_id' => $contributionID,
        'entity_id' => $entityID,
        'price_field_id' => $this->recurringLineItemData['price_field_id'],
        'price_field_value_id' => $this->recurringLineItemData['price_field_value_id'],
        'financial_type_id' => $this->recurringLineItemData['financial_type_id'],
        'qty' => $this->recurringLineItemData['qty'],
        'unit_price' => $this->recurringLineItemData['unit_price'],
        'options' => ['limit' => 1],
      ]);
      if ($apiResponse['count'] > 0) {
        $lineItem = $apiResponse['values'][0];
      }
    }
    catch (Exception $e) {
      return [];
    }

    return $lineItem;
  }

  /**
   * Updates recurring contribution line item to set end date and remove auto
   * renew option.
   */
  private function updateRecurringLineItem() {
    civicrm_api3('ContributionRecurLineItem', 'create', [
      'id' => $this->recurringLineItemData['id'],
      'auto_renew' => FALSE,
      'is_removed' => TRUE,
      'end_date' => $this->lineItemEndDate,
    ]);
  }

}
