<?php

use CRM_MembershipExtras_Service_FinancialTransactionManager as FinancialTransactionManager;
use CRM_MembershipExtras_BAO_ContributionRecurLineItem as ContributionRecurLineItem;

class CRM_MembershipExtras_Service_MembershipTypeSwitcher {

  const PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS = 1;

  const PAYMENT_TYPE_ONE_OFF_PAYMENT = 2;

  private $recurLineItemId;

  private $recurLineItemData;

  private $newMembershipTypeId;

  private $switchDate;

  private $periodEndDate;

  private $paymentType;

  private $newMembershipType;

  private $newMembership;

  private $numberOfRemainingInstalments;

  private $oneOffFeeParams;

  public function __construct($recurLineItemId, $newMembershipTypeId, $switchDate, $paymentType, $oneOffFeeParams = NULL) {
    $this->recurLineItemId = $recurLineItemId;
    $this->recurLineItemData = $this->getRecurringLineItemData();
    $this->newMembershipTypeId = $newMembershipTypeId;
    $this->switchDate = $switchDate;
    $this->paymentType = $paymentType;
    $this->oneOffFeeParams = $oneOffFeeParams;
    $this->newMembershipType = CRM_Member_BAO_MembershipType::findById($this->newMembershipTypeId);
    $this->periodEndDate = ContributionRecurLineItem::getPeriodEndDate($this->recurLineItemData['contribution_recur_id']);
  }

  private function getRecurringLineItemData() {
    $result = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'line_item_id' => $this->recurLineItemId,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
    ]);

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
   * Switches the payment plan membership line from
   * one type to another.
   *
   * @return void
   * @throws Exception
   */
  public function switchType() {
    $tx = new CRM_Core_Transaction();
    try {
      $this->endCurrentMembership();
      $this->newMembership = $this->createNewMembershipRecord();

      if ($this->paymentType == self::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS) {
        $this->adjustFuturePendingContributions();
      }
      else {
        $this->createOneOffPayment();
      }
    }
    catch (Exception $e) {
      $tx->rollback();
      throw $e;
    }
  }

  /**
   * Ends the current membership and
   * marks it as non-renewable.
   *
   * @return void
   * @throws CiviCRM_API3_Exception
   */
  private function endCurrentMembership() {
    $membershipId = $this->recurLineItemData['entity_id'];
    civicrm_api3('Membership', 'create', [
      'id' => $membershipId,
      'contribution_recur_id' => '',
      'end_date' => $this->switchDate,
    ]);

    // make the subscription line item non-renewable
    civicrm_api3('ContributionRecurLineItem', 'create', [
      'id' => $this->recurLineItemData['id'],
      'auto_renew' => FALSE,
      'is_removed' => TRUE,
      'end_date' => $this->switchDate,
    ]);
  }

  private function createNewMembershipRecord() {
    $newMembershipStartDate = new DateTime($this->switchDate);
    $newMembershipEndDate = new DateTime($this->periodEndDate);

    return civicrm_api3('Membership', 'create', [
      'sequential' => 1,
      'contact_id' => $this->getMemberContactId(),
      'membership_type_id' => $this->newMembershipTypeId,
      'join_date' => $newMembershipStartDate->format('Y-m-d'),
      'start_date' => $newMembershipStartDate->format('Y-m-d'),
      'end_date' => $newMembershipEndDate->format('Y-m-d'),
      'contribution_recur_id' => $this->recurLineItemData['contribution_recur_id'],
      'source' => 'Manage Instalments form / Switch Membership Type',
    ])['values'][0];
  }

  private function getMemberContactId() {
    return civicrm_api3('Membership', 'getvalue', [
      'return' => 'contact_id',
      'id' => $this->recurLineItemData['entity_id'],
    ]);
  }

  private function adjustFuturePendingContributions() {
    $pendingContributions = $this->getFuturePendingInstalments();
    $this->numberOfRemainingInstalments = count($pendingContributions);
    if ($this->numberOfRemainingInstalments < 1) {
      throw new CRM_Core_Exception('No pending instalments found for the payment plan after the selected date: ' . $this->switchDate . '.');
    }

    $contributionNumber = 1;
    foreach ($pendingContributions as $contribution) {
      $this->removeCurrentMembershipLineFromContribution($contribution);
      $this->addNewMembershipLineToContribution($contribution, $contributionNumber);
      $this->updateContributionAmounts($contribution, $contributionNumber);
      $contributionNumber++;
    }
  }

  private function getFuturePendingInstalments() {
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurLineItemData['contribution_recur_id'],
      'contribution_status_id' => 'Pending',
      'receive_date' => ['>=' => $this->switchDate],
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return [];
  }

  private function removeCurrentMembershipLineFromContribution($contribution) {
    $lineItemBefore = $this->getCorrespondingContributionLineItem($contribution['id']);
    if (empty($lineItemBefore)) {
      return;
    }

    civicrm_api3('LineItem', 'create', [
      'id' => $lineItemBefore['id'],
      'qty' => 0,
      'line_total' => 0.00,
      'tax_amount' => 0.00,
    ]);

    FinancialTransactionManager::insertFinancialItemOnLineItemDeletion($lineItemBefore, $contribution['receive_date']);

    // Remove line item from contribution
    civicrm_api3('LineItem', 'create', [
      'id' => $lineItemBefore['id'],
      'label' => $lineItemBefore['label'] . " - Line removed from contribution [{$lineItemBefore['contribution_id']}]",
      'contribution_id' => 'null',
    ]);

    // delete the link between the contribution and the membership
    $query = "DELETE FROM civicrm_membership_payment
                WHERE membership_id = {$this->recurLineItemData['entity_id']}
                AND contribution_id = {$contribution['id']}";
    CRM_Core_DAO::executeQuery($query);
  }

  private function getCorrespondingContributionLineItem($contributionID) {
    $lineItem = [];

    $apiResponse = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $contributionID,
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->recurLineItemData['entity_id'],
      'options' => ['limit' => 1],
    ]);
    if ($apiResponse['count'] > 0) {
      $lineItem = $apiResponse['values'][0];
    }

    return $lineItem;
  }

  private function addNewMembershipLineToContribution($contribution, $contributionNumber) {
    $lineAmounts = $this->calculateLineAmountsPerRemainingInstalment();
    $priceFieldValue = $this->getDefaultPriceFieldValueForMembershipType($this->newMembershipTypeId);

    $lineItemParams = [
      'sequential' => 1,
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->newMembership['id'],
      'price_field_id' => $priceFieldValue['price_field_id'],
      'label' => $this->newMembershipType->name,
      'qty' => 1,
      'unit_price' => $lineAmounts['line_total'],
      'line_total' => $lineAmounts['line_total'],
      'price_field_value_id' => $priceFieldValue['id'],
      'financial_type_id' => $this->newMembershipType->financial_type_id,
      'tax_amount' => $lineAmounts['tax_amount'],
    ];

    $isFirstContribution = $contributionNumber == 1;
    if ($isFirstContribution) {
      // create the subscription line item
      $recurLineItem = civicrm_api3('LineItem', 'create', $lineItemParams);

      $recurringSubscriptionLineParams = [
        'contribution_recur_id' => $this->recurLineItemData['contribution_recur_id'],
        'line_item_id' => $recurLineItem['id'],
        'start_date' => $this->newMembership['start_date'],
        'auto_renew' => 1,
      ];
      CRM_MembershipExtras_BAO_ContributionRecurLineItem::create($recurringSubscriptionLineParams);
    }

    // create the contribution line item
    $lineItemParams['contribution_id'] = $contribution['id'];
    $lineItem = civicrm_api3('LineItem', 'create', $lineItemParams);
    $lineItem = array_shift($lineItem['values']);
    FinancialTransactionManager::insertFinancialItemOnLineItemAddition($lineItem, $contribution['receive_date']);
  }

  private function calculateLineAmountsPerRemainingInstalment() {
    $newMembershipStartDate = new DateTime($this->newMembership['start_date']);
    $newMembershipEndDate = new DateTime($this->newMembership['end_date']);

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $membershipDurationCalculator = new CRM_MembershipExtras_Service_MembershipTypeDurationCalculator($this->newMembershipType, $membershipTypeDatesCalculator);
    $prorataDaysCount = $membershipDurationCalculator->calculateDaysBasedOnDates($newMembershipStartDate, $newMembershipEndDate, $newMembershipStartDate);

    $membershipTypeDurationInDays = $membershipDurationCalculator->calculateOriginalInDays($newMembershipStartDate, $newMembershipEndDate);
    $proratedAmount = ($this->newMembershipType->minimum_fee / $membershipTypeDurationInDays) * $prorataDaysCount;
    $proratedAmountExcTax = CRM_MembershipExtras_Service_MoneyUtilities::roundToPrecision($proratedAmount, 2);

    $calcAmountsResult = civicrm_api3('ContributionRecurLineItem', 'calculatetaxamount', [
      'amount_exc_tax' => $proratedAmountExcTax,
      'financial_type_id' => $this->newMembershipType->financial_type_id,
    ]);
    $taxAmount = $calcAmountsResult['tax_amount'];

    // calculate amount per remaining instalment
    $lineTotalAmount = CRM_MembershipExtras_Service_MoneyUtilities::roundToPrecision(($proratedAmountExcTax / $this->numberOfRemainingInstalments), 2);
    $lineTaxAmount = CRM_MembershipExtras_Service_MoneyUtilities::roundToPrecision(($taxAmount / $this->numberOfRemainingInstalments), 2);

    return ['line_total' => $lineTotalAmount, 'tax_amount' => $lineTaxAmount];
  }

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

  private function updateContributionAmounts($contribution, $contributionNumber) {
    $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($contribution['id']);
    $taxAmount = FinancialTransactionManager::calculateTaxAmountTotalFromContributionID($contribution['id']);

    FinancialTransactionManager::recordAdjustedAmount($contribution, $updatedAmount, $taxAmount);

    // set the recurring contribution amount to match the new amount per instalment
    if ($contributionNumber == 1) {
      civicrm_api3('ContributionRecur', 'create', [
        'sequential' => 1,
        'amount' => $updatedAmount,
        'id' => $this->recurLineItemData['contribution_recur_id'],
      ]);
    }
  }

  private function createOneOffPayment() {
    $recurringContribution = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->recurLineItemData['contribution_recur_id'],
    ]);

    $createdLineItemParams = $this->createOneOffFeeSubscriptionLineItem();
    $totalIncTax = $this->oneOffFeeParams['amount_inc_tax'] ?? 0;

    $contribution = civicrm_api3('Contribution', 'create', [
      'financial_type_id' => $this->oneOffFeeParams['financial_type_id'],
      'receive_date' => $this->oneOffFeeParams['scheduled_charge_date'],
      'total_amount' => $totalIncTax,
      'fee_amount' => 0,
      'net_amount' => $totalIncTax,
      'tax_amount' => $createdLineItemParams['tax_amount'],
      'contact_id' => $recurringContribution['contact_id'],
      'contribution_status_id' => 'Pending',
      'currency' => $recurringContribution['currency'],
      'payment_instrument_id' => $recurringContribution['payment_instrument_id'],
      'source' => 'Manage Instalments form - Switch Membership - One off payment',
      'contribution_recur_id' => $recurringContribution['id'],
      'is_pay_later' => TRUE,
    ]);

    civicrm_api3('MembershipPayment', 'create', [
      'membership_id' => $this->newMembership['id'],
      'contribution_id' => $contribution['id'],
    ]);

    if (!empty($this->oneOffFeeParams['send_confirmation_email'])) {
      civicrm_api3('Contribution', 'sendconfirmation', [
        'id' => $contribution['id'],
      ]);
    }
  }

  private function createOneOffFeeSubscriptionLineItem() {
    $amountExcTax = $this->oneOffFeeParams['amount_exc_tax'] ?? 0;
    $amountIncTax = $this->oneOffFeeParams['amount_inc_tax'] ?? 0;
    $taxAmount = $amountIncTax - $amountExcTax;
    $priceFieldValue = $this->getDefaultPriceFieldValueForMembershipType($this->newMembershipTypeId);
    $lineItemParams = [
      'sequential' => 1,
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->newMembership['id'],
      'price_field_id' => $priceFieldValue['price_field_id'],
      'label' => $this->newMembershipType->name,
      'qty' => 1,
      'unit_price' => $amountExcTax,
      'line_total' => $amountExcTax,
      'price_field_value_id' => $priceFieldValue['id'],
      'financial_type_id' => $this->newMembershipType->financial_type_id,
      'tax_amount' => $taxAmount,
    ];

    $recurLineItem = civicrm_api3('LineItem', 'create', $lineItemParams);

    $recurringSubscriptionLineParams = [
      'contribution_recur_id' => $this->recurLineItemData['contribution_recur_id'],
      'line_item_id' => $recurLineItem['id'],
      'start_date' => $this->newMembership['start_date'],
      'auto_renew' => 1,
    ];
    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create($recurringSubscriptionLineParams);

    return $lineItemParams;
  }

}
