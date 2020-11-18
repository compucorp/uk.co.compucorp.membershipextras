<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Form controller class to add a new membership as a line item in a payment plan.
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
      'id' => $membershipID,
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
   * Checks if recurring line item exists for membership type in next period,
   * and updates it if finds one. Otherwise, creates new line item associated to
   * the recurring contribution.
   *
   * @return array
   */
  protected function createRecurringLineItem() {
    $membership = $this->saveMembership();

    $priceFieldValue = $this->getDefaultPriceFieldValueForMembershipType($membership['membership_type_id']);
    $taxRate = $this->getTaxRateForFinancialType($priceFieldValue['financial_type_id']);
    $taxAmount = MoneyUtilities::roundToCurrencyPrecision(
      $this->lineItemParams['amount'] * $taxRate / 100
    );

    $lineItemParams = [
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
    ];

    $existingLineItem = $this->getExistingLineItemForMembershipType($membership['membership_type_id']);
    if (CRM_Utils_Array::value('id', $existingLineItem, FALSE)) {
      $lineItemParams['id'] = $existingLineItem['line_item_id'];
    }
    $lineItem = civicrm_api3('LineItem', 'create', $lineItemParams);

    $recurringSubscriptionLineParams = [
      'contribution_recur_id' => $this->recurringContribution['id'],
      'line_item_id' => $lineItem['id'],
      'start_date' => $this->lineItemParams['start_date'],
      'auto_renew' => $this->lineItemParams['auto_renew'],
    ];

    if (CRM_Utils_Array::value('id', $existingLineItem, FALSE)) {
      $recurringSubscriptionLineParams['id'] = $existingLineItem['id'];
    }
    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create($recurringSubscriptionLineParams);

    return array_shift($lineItem['values']);
  }

  /**
   * Loops through line items on next period and returns the first line item
   * with the same membership type as the given one.
   *
   * @param int $membershipTypeID
   *
   * @return array
   */
  private function getExistingLineItemForMembershipType($membershipTypeID) {
    $nextPeriodLines = $this->getNextPeriodLineItems();

    foreach ($nextPeriodLines as $lineItem) {
      $priceFieldValue = $lineItem['price_field_value'];
      $lineMembershipType = $priceFieldValue['membership_type_id'];

      if ($membershipTypeID == $lineMembershipType) {
        return $lineItem;
      }
    }

    return [];
  }

  /**
   * Obtains list of line items for the next period.
   *
   * @return array
   */
  private function getNextPeriodLineItems() {
    $lineItems = array();

    $params = [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'auto_renew' => TRUE,
      'is_removed' => 0,
      'options' => ['limit' => 0],
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
        'api.PriceFieldValue.getsingle' => [
          'id' => '$value.price_field_value_id',
        ],
      ],
    ];

    $installments = CRM_Utils_Array::value('installments', $this->recurringContribution, 0);
    if ($installments <= 1) {
      $params['end_date'] = ['IS NULL' => 1];
    }

    $result = civicrm_api3('ContributionRecurLineItem', 'get', $params);
    if ($result['count'] > 0) {
      foreach ($result['values'] as $lineItemData) {
        $lineDetails = $lineItemData['api.LineItem.getsingle'];
        $lineDetails['price_field_value'] = $lineDetails['api.PriceFieldValue.getsingle'];

        unset($lineDetails['id']);
        unset($lineDetails['api.PriceFieldValue.getsingle']);
        unset($lineItemData['api.LineItem.getsingle']);
        $lineItems[] = array_merge($lineItemData, $lineDetails);
      }
    }

    return $lineItems;
  }

  /**
   * If membership for the membership type doesn't exist, it creates it. If it
   * does, it updates it.
   *
   * @return mixed
   */
  private function saveMembership() {
    if (!$this->membershipExists()) {
      $membership = $this->createMembership();
    }
    else {
      $membership = $this->updateMembership();
    }

    return $membership;
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
      'end_date' => $this->calculateEndDateForMembership(),
      'contribution_recur_id' => $this->recurringContribution['auto_renew'] ? $this->recurringContribution['id'] : '',
    ]);

    return array_shift($result['values']);
  }

  /**
   * Calculates end date that shuld be used for the membership.
   *
   * @return string|null
   */
  private function calculateEndDateForMembership() {
    if ($this->membershipType['duration_unit'] == 'lifetime') {
      return NULL;
    }

    return $this->lineItemParams['end_date'];
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
  private function getMembershipForContact($contactID, $membershipTypeID, $getCount = FALSE) {
    $action = $getCount ? 'getcount' : 'get';

    $membershipResult = civicrm_api3('Membership', $action, [
      'sequential' => 1,
      'contact_id' => $contactID,
      'membership_type_id' => $membershipTypeID,
      'options' => ['sort' => 'id desc'],
    ]);

    if ($getCount) {
      return $membershipResult;
    }
    else {
      return array_shift($membershipResult['values']);
    }
  }

  /**
   * Creates a new membership for the recurring contribution.
   *
   * @return array
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function createMembership() {
    $autoRenew = $this->recurringContribution['auto_renew'] && $this->lineItemParams['auto_renew'];
    $result = civicrm_api3('Membership', 'create', [
      'sequential' => 1,
      'contact_id' => $this->recurringContribution['contact_id'],
      'membership_type_id' => $this->membershipType['id'],
      'join_date' => $this->lineItemParams['start_date'],
      'start_date' => $this->lineItemParams['start_date'],
      'end_date' => $this->calculateEndDateForMembership(),
      'contribution_recur_id' => $autoRenew ? $this->recurringContribution['id'] : '',
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
