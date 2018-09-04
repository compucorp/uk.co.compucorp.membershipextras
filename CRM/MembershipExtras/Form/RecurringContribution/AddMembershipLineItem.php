<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Form controller class to confirm addition of a new membership as a line item
 * in a recurring contribution.
 */
class CRM_MembershipExtras_Form_RecurringContribution_AddMembershipLineItem extends CRM_MembershipExtras_Form_RecurringContribution_AddLineItem {

  /**
   * Data for the membership type to be used to create the new line item.
   *
   * @var array
   */
  private $membershipType;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    parent::preProcess();

    $this->membershipType = $this->getMembershipType($this->lineItemParams['membership_type_id']);
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
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Add %1', ['1' => $this->membershipType['name']]) . '?');

    parent::buildQuickForm();
  }

  /**
   * @inheritdoc
   */
  protected function processLineItemAddition() {
    if (!$this->membershipExists()) {
      $membership = $this->createMembership();
    } else {
      $membership = $this->updateMembership();
    }

    $recurringLineItem = $this->createRecurringLineItem($membership);

    $this->addLineItemToPendingContributions($recurringLineItem);
  }

  /**
   * @inheritdoc
   */
  protected function showOnSuccessNotifications() {
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
  }

  /**
   * @inheritdoc
   */
  protected function showErrorNotification(Exception $e) {
    CRM_Core_Session::setStatus(
      "An error ocurred trying to add {$this->membershipType['name']} to the current recurring contribution: " . $e->getMessage(),
      "Error Adding {$this->membershipType['name']}",
      'error'
    );
  }

  /**
   * Checks if line item exists for the contribution and returns it.
   *
   * @param $lineItemParams
   *
   * @return array
   */
  protected function findExistingLineItemForContribution($lineItemParams) {
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
   * Creates new line item associaated to the rcurring contribution.
   *
   * @param array $membership
   *
   * @return array
   */
  protected function createRecurringLineItem($membership = []) {
    $priceFieldValue = $this->getDefaultPriceFieldValueForMembershipType($membership['membership_type_id']);
    $taxRate = $this->getTaxRateForFinancialType($priceFieldValue['financial_type_id']);
    $taxAmount = MoneyUtilities::roundToCurrencyPrecision(
      $this->lineItemParams['amount'] * $taxRate / 100
    );

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

  /**
   * Searches for membership type in contact's active memberships.
   *
   * @param $contactID
   * @param $membershipTypeID
   * @param bool $getCount
   *
   * @return array|int
   */
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
