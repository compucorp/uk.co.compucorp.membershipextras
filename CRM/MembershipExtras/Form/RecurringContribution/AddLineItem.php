<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Abstract class defining methods used to add a new line item to a recurring
 * contribution.
 */
abstract class CRM_MembershipExtras_Form_RecurringContribution_AddLineItem extends CRM_Core_Form {

  /**
   * Recurring contribution's data.
   *
   * @var array
   */
  protected $recurringContribution;

  /**
   * Parameters to be used to create the new line item.
   *
   * @var
   */
  protected $lineItemParams;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $recurringContributionID = CRM_Utils_Request::retrieve('contribution_recur_id', 'Text', $this);
    $this->recurringContribution = $this->getRecurringContribution($recurringContributionID);
    $this->lineItemParams = CRM_Utils_Request::retrieve('line_item', 'Text', $this);
  }

  /**
   * Returns information for the recurring contribution identified by $id.
   *
   * @param int $id
   *
   * @return array
   */
  protected function getRecurringContribution($id) {
    return civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $id
    ]);
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    return [
      'first_installment_amount' => $this->lineItemParams['amount']
    ];
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    $this->add('checkbox', 'adjust_first_amount', ts('Adjust the amount of the first instalment?'), [], FALSE);
    $this->addMoney('first_installment_amount', ts('First Installment Amount'), FALSE, [], FALSE);
    $this->assign('newLineItem', $this->lineItemParams);

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
    $tx = new CRM_Core_Transaction();

    try {
      $recurringLineItem = $this->createRecurringLineItem();
      $this->updateRecurringContributionAmount();
      $this->addLineItemToPendingContributions($recurringLineItem);
      $this->showOnSuccessNotifications();
    } catch (Exception $e) {
      $tx->rollback();
      $this->showErrorNotification($e);
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
      'id' => $this->recurringContribution['id'],
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
      'contribution_recur_id' => $this->recurringContribution['id'],
      'start_date' => ['IS NOT NULL' => 1],
      'is_removed' => 0,
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
   * Returns tax rate used for given financial type ID.
   *
   * @param $financialTypeID
   *
   * @return double
   */
  protected function getTaxRateForFinancialType($financialTypeID) {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = MoneyUtilities::roundToPrecision(
      CRM_Utils_Array::value($financialTypeID, $taxRates, 0),
      2
    );

    return $rate;
  }

  /**
   * Adds new line item to pending contributions and updates their amounts,
   * recording appropriate financial transactions.
   *
   * @param $recurringLineItem
   */
  protected function addLineItemToPendingContributions($recurringLineItem) {
    $firstContribution = TRUE;

    foreach ($this->getPendingContributions() as $contribution) {
      $lineItemParams = $recurringLineItem;
      $lineItemParams['contribution_id'] = $contribution['id'];
      unset($lineItemParams['id']);

      if ($firstContribution && $this->getElementValue('adjust_first_amount')) {
        $firstAmountTotal = $this->getElementValue('first_installment_amount');
        $taxRates = CRM_Core_PseudoConstant::getTaxRates();
        $rate = CRM_Utils_Array::value($recurringLineItem['financial_type_id'], $taxRates, 0);

        $lineItemParams['unit_price'] = MoneyUtilities::roundToCurrencyPrecision($firstAmountTotal);
        $lineItemParams['line_total'] = MoneyUtilities::roundToCurrencyPrecision($lineItemParams['unit_price'] * $lineItemParams['qty']);
        $lineItemParams['tax_amount'] = MoneyUtilities::roundToCurrencyPrecision($lineItemParams['line_total'] * ($rate / 100));

        $firstContribution = FALSE;
      }

      $existingLineItem = $this->findExistingLineItemForContribution($lineItemParams);
      if (CRM_Utils_Array::value('id', $existingLineItem, FALSE)) {
        $lineItemParams['id'] = $existingLineItem['id'];
      }

      $lineItemCreateResult = civicrm_api3('LineItem', 'create', $lineItemParams);
      $lineItem = array_shift($lineItemCreateResult['values']);

      // calculate balance, tax and paid amount later used to adjust transaction
      $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($contribution['id']);
      $taxAmount = CRM_MembershipExtras_Service_FinancialTransactionManager::calculateTaxAmountTotalFromContributionID($contribution['id']);

      // Record adjusted amount by updating contribution info
      CRM_MembershipExtras_Service_FinancialTransactionManager::recordAdjustedAmount($contribution, $updatedAmount, $taxAmount);

      // Record financial item on adding of line item
      CRM_MembershipExtras_Service_FinancialTransactionManager::insertFinancialItemOnLineItemAddition($lineItem);
    }
  }

  /**
   * Returns an array with the information of pending recurring contributions
   * after selected start date.
   *
   * @return array
   */
  protected function getPendingContributions() {
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'contribution_status_id' => 'Pending',
      'receive_date' => ['>=' => $this->lineItemParams['start_date']],
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return [];
  }

  /**
   * Creates new line item associated to the recurring contribution.
   *
   * @return array
   */
  abstract protected function createRecurringLineItem();

  /**
   * Looks for an existing line item for the contribution.
   *
   * @param $lineItemParams
   *
   * @return array
   */
  abstract protected function findExistingLineItemForContribution($lineItemParams);

  /**
   * Shows notifications after all entities have been created and updated
   * succesfully.
   *
   * @return mixed
   */
  abstract protected function showOnSuccessNotifications();

  /**
   * Shows notification with the given exception's message.
   *
   * @param \Exception $e
   *
   * @return mixed
   */
  abstract protected function showErrorNotification(Exception $e);

}
