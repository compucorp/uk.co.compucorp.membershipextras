<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Form controller class to confirm addition of a new membership as a line item
 * in a recurring contribution.
 */
class CRM_MembershipExtras_Form_RecurringContribution_AddMembershipLineItem extends CRM_Core_Form {

  private $recurringContribution;
  private $lineItemParams;
  private $membershipType;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $recurringContributionID = CRM_Utils_Request::retrieve('contribution_recur_id', 'Text', $this);
    $this->recurringContribution = $this->getRecurringContribution($recurringContributionID);
    $this->lineItemParams = CRM_Utils_Request::retrieve('line_item', 'Text', $this);
    $this->membershipType = $this->getMembershipType($this->lineItemParams['membership_type_id']);
  }

  /**
   * Returns information for the recurring contribution identified by $id.
   *
   * @param int $id
   *
   * @return array
   */
  private function getRecurringContribution($id) {
    return civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $id
    ]);
  }

  /**
   * Gets information for the given membership type ID.
   *
   * @param int $membershipID
   *
   * @return array
   */
  private function getMembershipType($membershipID) {
    return civicrm_api3('MembershipType', 'getsingle', [
      'id' => $membershipID
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
    CRM_Utils_System::setTitle(E::ts('Add %1', ['1' => $this->membershipType['name']]) . '?');

    $this->add('checkbox', 'adjust_first_amount', ts('Adjust the amount of the first instalment?'), [], FALSE);
    $this->addMoney('first_installment_amount', ts('First Installment Amount'), FALSE, [], FALSE);

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
      if (!$this->membershipExists()) {
        $membership = $this->createMembership();
      } else {
        $membership = $this->updateMembership();
      }

      $recurringLineItem = $this->createRecurringLineItem($membership);

      $this->addLineItemToPendingContributions($membership, $recurringLineItem);

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
   * Adds new line item to pending contributions and updates their amounts,
   * recording appropriate financial transactions.
   *
   * @param $membership
   * @param $recurringLineItem
   */
  private function addLineItemToPendingContributions($membership, $recurringLineItem) {
    $firstContribution = TRUE;

    foreach ($this->getPendingContributions() as $contribution) {
      $lineItemParams = $recurringLineItem;
      $lineItemParams['contribution_id'] = $contribution['id'];
      unset($lineItemParams['id']);

      if ($firstContribution && $this->getElementValue('adjust_first_amount')) {
        $firstAmountTotal = $this->getElementValue('first_installment_amount');
        $taxRates = CRM_Core_PseudoConstant::getTaxRates();
        $rate = CRM_Utils_Array::value($this->params['financial_type_id'], $taxRates, 0);

        $lineItemParams['tax_amount'] = round(($firstAmountTotal * ($rate / 100)) / (1 + ($rate / 100)), 2);
        $lineItemParams['unit_price'] = round($firstAmountTotal - $lineItemParams['tax_amount'], 2);
        $lineItemParams['line_total'] = $lineItemParams['unit_price'];

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
   * Checks if line item exists for the contribution and returns it.
   *
   * @param $lineItemParams
   *
   * @return array
   */
  private function findExistingLineItemForContribution($lineItemParams) {
    $result = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'entity_table' => $lineItemParams['entity_table'],
      'entity_id' => $lineItemParams['entity_id'],
      'contribution_id' => $lineItemParams['contribution_id'],
      'price_field_value_id' => $lineItemParams['price_field_value_id'],
      'price_field_id' => $lineItemParams['price_field_id'],
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Returns an array with the information of pending recurring contributions
   * after selected start date.
   *
   * @return array
   */
  private function getPendingContributions() {
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
   * Creates new line item associaated to the rcurring contribution.
   *
   * @param array $membership
   *
   * @return array
   */
  private function createRecurringLineItem($membership) {
    $priceFieldValue = $this->getDefaultPriceFieldValueForMembershipType($membership['membership_type_id']);
    $taxRate = $this->getTaxRateForFinancialType($priceFieldValue['financial_type_id']);
    $taxAmount = round($this->lineItemParams['amount'] * $taxRate / 100, 2);

    $lineItem = civicrm_api3('LineItem', 'create', [
      'sequential' => 1,
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membership['id'],
      'price_field_id' => $priceFieldValue['price_field_id'],
      'label' => $this->membershipType['name'],
      'qty' => 1,
      'unit_price' => $this->lineItemParams['amount'],
      'line_total' => $this->lineItemParams['amount'],
      'price_field_value_id' => $priceFieldValue['id'],
      'financial_type_id' => $priceFieldValue['financial_type_id'],
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
   * Returns tax rate used for given financial type ID.
   *
   * @param $financialTypeID
   *
   * @return double
   */
  private function getTaxRateForFinancialType($financialTypeID) {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = round(CRM_Utils_Array::value($financialTypeID, $taxRates, 0), 2);

    return $rate;
  }

  /**
   * Returns the default price field id to be used for the given membership
   * type.
   *
   * @param $membershipTypeID
   *
   * @return array
   */
  private function getDefaultPriceFieldValueForMembershipType($membershipTypeID) {
    $result = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $membershipTypeID,
      'price_field_id.price_set_id.name' => 'default_membership_type_amount',
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Updates membership with new line item data.
   */
  private function updateMembership() {
    $membership = $this->getMembershipForContact($this->recurringContribution['contact_id'], $this->membershipType['id']);

    $result = civicrm_api3('Membership', 'create', [
      'sequential' => 1,
      'id' => $membership['id'],
      'start_date' => $this->lineItemParams['start_date'],
      'end_date' => $this->lineItemParams['end_date'],
      'contribution_recur_id' => $this->recurringContribution['id'],
    ]);

    return array_shift($result['values']);
  }

  private function getMembershipForContact($contactID, $membershipTypeID, $getCount = false) {
    $action = $getCount ? 'getcount' : 'get';

    $membershipResult = civicrm_api3('Membership', $action, [
      'sequential' => 1,
      'contact_id' => $contactID,
      'membership_type_id' => $membershipTypeID,
      'options' => ['sort' => 'id desc'],
    ]);

    if ($getCount) {
      return $membershipResult;
    } else {
      return array_shift($membershipResult['values']);
    }
  }

  /**
   * Creates a new membership for the recurring contribution.
   */
  private function createMembership() {
    $result = civicrm_api3('Membership', 'create', [
      'sequential' => 1,
      'contact_id' => $this->recurringContribution['contact_id'],
      'membership_type_id' => $this->membershipType['id'],
      'join_date' => date('YmdHis'),
      'start_date' => $this->lineItemParams['start_date'],
      'end_date' => $this->lineItemParams['end_date'],
      'contribution_recur_id' => $this->recurringContribution['id'],
    ]);

    return array_shift($result['values']);
  }

  /**
   * Checks if there is an active membership of the same type for the current
   * recurring contribution.
   */
  private function membershipExists() {
    $membershipCount = $this->getMembershipForContact(
      $this->recurringContribution['contact_id'],
      $this->membershipType['id'],
      TRUE
    );

    if ($membershipCount > 0) {
      return TRUE;
    }

    return FALSE;
  }

}
