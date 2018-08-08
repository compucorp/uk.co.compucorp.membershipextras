<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Form controller class to allow removal of line items from a recurring
 * contribution.
 */
class CRM_MembershipExtras_Form_RecurringContribution_RemoveLineItems extends CRM_Core_Form {

  private $recurringContributionID;
  private $lineItemID;
  private $recurringLineItemData = array();

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->recurringContributionID = CRM_Utils_Request::retrieve('contribution_recur_id', 'Positive', $this);
    $this->lineItemID = CRM_Utils_Request::retrieve('line_item_id', 'Positive', $this);
    $this->recurringLineItemData = $this->getRecurringLineItemData();
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    return array(
      'end_date' => date('Y-m-d')
    );
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
        'entity_id' => ['IS NOT NULL' => 1]
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

    return array();
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
    try {
      if ($this->isLineItemAMembership()) {
        $this->cancelMembership();
      }

      $this->adjustPendingContributions();
      $this->updateRecurringLineItem();
    } catch (Exception $e) {
      CRM_Core_Session::setStatus(
        "An error ocurred trying to remove {$this->recurringLineItemData['label']} from the current recurring contribution:" . $e->getMessage(),
        "Error Removing {$this->recurringLineItemData['label']}",
        'error'
      );

      return;
    }

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

  /**
   * Checks if line item being removed is for a membership.
   *
   * @return bool
   */
  private function isLineItemAMembership() {
    return CRM_MembershipExtras_Service_FinancialTransactionManager::isMembership($this->recurringLineItemData['line_item_id']);
  }

  /**
   * Cancels membership identified by entity_id of line item.
   */
  private function cancelMembership() {
    civicrm_api3('Membership', 'create', [
      'id' => $this->recurringLineItemData['entity_id'],
      'is_override' => 1,
      'status_override_end_date' => '',
      'status_id' => 'Cancelled',
      'contribution_recur_id' => 0,
    ]);
  }

  /**
   * Makes adjustment for remaining contributions for payment plan, removing
   * line item and adjustinc amounts.
   */
  private function adjustPendingContributions() {
    foreach ($this->getPendingContributions() as $contribution) {
      $this->cancelContributionLineItem($contribution);
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
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return array();
  }

  /**
   * Executes actions on line item to remove it from its contribution and update
   * its amounts.
   *
   * @param $contribution
   */
  private function cancelContributionLineItem($contribution) {
    $lineItemBefore = $this->getCorrespondingContributionLineItem($contribution['id']);

    // change total_price and qty of current line item to 0
    civicrm_api3('LineItem', 'create', array(
      'id' => $lineItemBefore['id'],
      'qty' => 0,
      'participant_count' => 0,
      'line_total' => 0.00,
      'tax_amount' => 0.00,
    ));

    // calculate balance, tax and paid amount later used to adjust transaction
    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($contribution['id']);
    $taxAmount = CRM_MembershipExtras_Service_FinancialTransactionManager::calculateTaxAmountTotalFromContributionID($contribution['id']);

    // Record adjusted amount by updating contribution info and create necessary financial trxns
    $this->recordAdjustedAmount($contribution, $updatedAmount, $taxAmount);

    // Record financial item on cancel of lineitem
    CRM_MembershipExtras_Service_FinancialTransactionManager::insertFinancialItemOnDeletion($lineItemBefore);
  }

  /**
   * Obtains information about the line item that corresponds to the one being
   * deleted from the recurring contribution for the given contribution.
   *
   * @param $contributionID
   *
   * @return array
   */
  private function getCorrespondingContributionLineItem($contributionID) {
    $lineItem = civicrm_api3('LineItem', 'getsingle', array(
      'contribution_id' => $contributionID,
      'price_field_value_id' => $this->recurringLineItemData['price_field_value_id'],
    ));

    return $lineItem;
  }

  /**
   * Stores updated amounts for given contribution.
   *
   * @param array $contribution
   * @param double $updatedAmount
   * @param double $taxAmount
   */
  private function recordAdjustedAmount($contribution, $updatedAmount, $taxAmount = NULL) {
    $updatedContributionDAO = new CRM_Contribute_BAO_Contribution();
    $updatedContributionDAO->id = $contribution['id'];
    $updatedContributionDAO->total_amount = $updatedAmount;
    $updatedContributionDAO->net_amount = $updatedAmount - CRM_Utils_Array::value('fee_amount', $contribution, 0);

    if ($taxAmount) {
      $updatedContributionDAO->tax_amount = $taxAmount;
    }

    $updatedContributionDAO->save();
  }

  /**
   * Updates recurring contribution line item to set end date and remove auto
   * renew option.
   */
  private function updateRecurringLineItem() {
    $params = [
      'id' => $this->recurringLineItemData['id'],
      'auto_renew' => false,
    ];

    if ($this->getElementValue('adjust_end_date')) {
      $params['end_date'] = $this->getElementValue('end_date');
    } else {
      $params['end_date'] = date('Y-m-d');
    }

    civicrm_api3('ContributionRecurLineItem', 'create', $params);
  }

}
