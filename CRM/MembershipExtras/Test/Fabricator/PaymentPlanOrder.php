<?php
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringLineItem as RecurringLineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contribution as ContributionFabricator;
use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrderEntity;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_PaymentPlan
 */
class CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder {

  private static $paymentPlanMembershipOrder;

  /**
   * Fabricates payment plan order.
   *
   * @param CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder $paymentPlanMembershipOrder
   * @param bool $createUpfrontContributions
   *
   * @return array
   */
  public static function fabricate(PaymentPlanMembershipOrderEntity $paymentPlanMembershipOrder, $createUpfrontContributions = TRUE) {
    self::$paymentPlanMembershipOrder = $paymentPlanMembershipOrder;
    self::updatePaymentPlanMissingParams();

    $recurringContribution = self::createRecurringContribution();
    self::updateNextContributionDate($recurringContribution['id']);
    $lineItems = self::createRecurringLineItems($recurringContribution);
    self::updateRecurringContributionAmount($recurringContribution);
    self::createInstalments($recurringContribution, $lineItems, $createUpfrontContributions);

    return $recurringContribution;
  }

  /**
   * Sets the missing payment plan
   * order parameters to sensible default
   * values if they are not set.
   *
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private static function updatePaymentPlanMissingParams() {
    if (empty(self::$paymentPlanMembershipOrder->contactId)) {
      self::$paymentPlanMembershipOrder->contactId = CRM_MembershipExtras_Test_Fabricator_Contact::fabricate()['id'];
    }

    if (empty(self::$paymentPlanMembershipOrder->paymentPlanFrequency)) {
      self::$paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
    }

    if (empty(self::$paymentPlanMembershipOrder->paymentPlanStatus)) {
      self::$paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    }

    if (empty(self::$paymentPlanMembershipOrder->paymentProcessor)) {
      self::$paymentPlanMembershipOrder->paymentProcessor = 'Offline Recurring Contribution';
    }

    if (empty(self::$paymentPlanMembershipOrder->financialType)) {
      self::$paymentPlanMembershipOrder->financialType = 'Member Dues';
    }

    if (empty(self::$paymentPlanMembershipOrder->paymentMethod)) {
      self::$paymentPlanMembershipOrder->paymentMethod = 'EFT';
    }

    if (empty(self::$paymentPlanMembershipOrder->membershipStartDate)) {
      self::$paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d');
    }

    if (empty(self::$paymentPlanMembershipOrder->membershipJoinDate)) {
      self::$paymentPlanMembershipOrder->membershipJoinDate = self::$paymentPlanMembershipOrder->membershipStartDate;
    }

    if (empty(self::$paymentPlanMembershipOrder->paymentPlanStartDate)) {
      self::$paymentPlanMembershipOrder->paymentPlanStartDate = self::$paymentPlanMembershipOrder->membershipStartDate;
    }

    if (empty(self::$paymentPlanMembershipOrder->nextContributionDate)) {
      self::$paymentPlanMembershipOrder->nextContributionDate = self::$paymentPlanMembershipOrder->membershipStartDate;
    }

    return self::$paymentPlanMembershipOrder;
  }

  /**
   * Creates the recurring contribution.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function createRecurringContribution() {
    switch (self::$paymentPlanMembershipOrder->paymentPlanFrequency) {
      case 'Yearly':
        $frequencyUnit = 'year';
        $frequencyInterval = 1;
        $instalments = 1;
        break;

      case 'Monthly':
        $frequencyUnit = 'month';
        $frequencyInterval = 1;
        $instalments = 12;
        break;

      case 'Quarterly':
        $frequencyUnit = 'month';
        $frequencyInterval = 3;
        $instalments = 4;
        break;
    }

    $isActivePaymentPlanFieldId = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => 'payment_plan_extra_attributes',
      'name' => 'is_active',
    ])['id'];

    $recurringContributionParams = [
      'sequential' => 1,
      'contact_id' => self::$paymentPlanMembershipOrder->contactId,
      'amount' => 0,
      'frequency_unit' => $frequencyUnit,
      'frequency_interval' => $frequencyInterval,
      'installments' => $instalments,
      'contribution_status_id' => self::$paymentPlanMembershipOrder->paymentPlanStatus,
      'is_test' => 0,
      'auto_renew' => isset(self::$paymentPlanMembershipOrder->autoRenew) ? self::$paymentPlanMembershipOrder->autoRenew : 1,
      'cycle_day' => CRM_MembershipExtras_Service_CycleDayCalculator::calculate(self::$paymentPlanMembershipOrder->paymentPlanStartDate, $frequencyUnit),
      'payment_processor_id' => self::$paymentPlanMembershipOrder->paymentProcessor,
      'financial_type_id' => self::$paymentPlanMembershipOrder->financialType,
      'payment_instrument_id' => self::$paymentPlanMembershipOrder->paymentMethod,
      'start_date' => self::$paymentPlanMembershipOrder->paymentPlanStartDate,
      'custom_' . $isActivePaymentPlanFieldId => 1,
    ];

    return RecurringContributionFabricator::fabricate($recurringContributionParams);
  }

  public static function updateNextContributionDate($recurringContributionId) {
    $query = 'UPDATE civicrm_contribution_recur SET next_sched_contribution_date = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($query, [
      1 => [self::$paymentPlanMembershipOrder->nextContributionDate, 'String'],
      2 => [$recurringContributionId, 'Integer'],
    ]);
  }

  /**
   * Creates the recurring line items and associates.
   *
   * Creates the given line items and associates the to the recurring
   * contribution.
   *
   * @param array $recurringContribution
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function createRecurringLineItems($recurringContribution) {
    $createdLines = [];

    foreach (self::$paymentPlanMembershipOrder->lineItems as $lineItem) {
      if (self::isMembershipLineItem($lineItem)) {
        $existingMembershipId = self::getMembershipIdWithSameTypeIfExist($lineItem['price_field_value_id']);
        if ($existingMembershipId) {
          $lineItem['entity_id'] = $existingMembershipId;
          $lineItem['entity_table'] = 'civicrm_membership';
        }
        else {
          $membershipID = self::createMembership($lineItem, $recurringContribution);
          $lineItem['entity_id'] = $membershipID;
          $lineItem['entity_table'] = 'civicrm_membership';
        }
      }

      $newLineItem = LineItemFabricator::fabricate($lineItem);
      $recurringLineItem = RecurringLineItemFabricator::fabricate([
        'contribution_recur_id' => $recurringContribution['id'],
        'line_item_id' => $newLineItem['id'],
        'start_date' => self::$paymentPlanMembershipOrder->membershipStartDate,
        'auto_renew' => isset($lineItem['auto_renew']) ? $lineItem['auto_renew'] : 1,
      ]);

      $createdLines[] = ['line_item' => $newLineItem, 'recurring_line' => $recurringLineItem];
    }

    return $createdLines;
  }

  /**
   * Updates the total amount of the given recurring contribution.
   *
   * Updates total amount by evaluating the total of each related line item
   * associated to the recurring contribution.
   *
   * @param $recurringContribution
   *
   * @throws \CiviCRM_API3_Exception
   */
  private static function updateRecurringContributionAmount(&$recurringContribution) {
    $totalAmount = self::calculateRecurringContributionTotalAmount($recurringContribution['id']);
    civicrm_api3('ContributionRecur', 'create', [
      'id' => $recurringContribution['id'],
      'amount' => $totalAmount,
    ]);

    $recurringContribution['amount'] = $totalAmount;
  }

  /**
   * Calculates the total amount due for the given recurring contribution.
   *
   * @param int $recurringContributionID
   *
   * @return float
   * @throws \CiviCRM_API3_Exception
   */
  private static function calculateRecurringContributionTotalAmount($recurringContributionID) {
    $totalAmount = 0;

    $result = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'start_date' => ['IS NOT NULL' => 1],
      'is_removed' => 0,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      foreach ($result['values'] as $lineItemData) {
        $totalAmount += floatval($lineItemData['api.LineItem.getsingle']['line_total']);

        if (isset($lineItemData['api.LineItem.getsingle']['tax_amount'])) {
          $totalAmount += floatval($lineItemData['api.LineItem.getsingle']['tax_amount']);
        }
      }
    }

    return CRM_MembershipExtras_Service_MoneyUtilities::roundToCurrencyPrecision($totalAmount);
  }

  /**
   * Creates the payment plan's instalments.
   *
   * @param array $recurringContribution
   * @param array $lineItems
   * @param bool $createUpfrontContributions
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  private static function createInstalments($recurringContribution, $lineItems, $createUpfrontContributions) {
    self::createFirstInstalment($recurringContribution, $lineItems);

    if ($createUpfrontContributions) {
      self::createRemainingInstalments($recurringContribution['id']);
    }
  }

  /**
   * Creates the first contribution for the payment plan.
   *
   * @param array $recurringContribution
   * @param array $lineItems
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  private static function createFirstInstalment($recurringContribution, &$lineItems) {
    $params = [
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => self::$paymentPlanMembershipOrder->nextContributionDate,
      'contribution_recur_id' => $recurringContribution['id'],
      'contact_id' => $recurringContribution['contact_id'],
      'fee_amount' => 0,
      'net_amount' => "{$recurringContribution['amount']}",
      'total_amount' => "{$recurringContribution['amount']}",
      'payment_instrument_id' => self::$paymentPlanMembershipOrder->paymentMethod,
      'financial_type_id' => self::$paymentPlanMembershipOrder->financialType,
      'contribution_status_id' => self::$paymentPlanMembershipOrder->paymentPlanStatus,
    ];
    $contribution = ContributionFabricator::fabricate($params);

    foreach ($lineItems as &$line) {
      unset($line['line_item']['id']);
      $line['line_item']['contribution_id'] = $contribution['id'];

      if ($line['line_item']['entity_table'] === 'civicrm_contribution') {
        $line['line_item']['entity_id'] = $contribution['id'];
      }

      self::createLineItemForContribution($line);
    }
  }

  /**
   * Creates the line item for the contribution identified by the given ID.
   *
   * @param array $line
   *
   * @throws \CiviCRM_API3_Exception
   */
  private static function createLineItemForContribution($line) {
    $contribution = CRM_Contribute_BAO_Contribution::findById($line['line_item']['contribution_id']);
    $newLineItem = CRM_Price_BAO_LineItem::create($line['line_item']);
    CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution);

    if (!empty($contribution->tax_amount) && !empty($newLineItem->tax_amount)) {
      CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution, TRUE);
    }

    if (self::isMembershipLineItem($line['line_item'])) {
      CRM_Member_BAO_MembershipPayment::create([
        'membership_id' => $line['line_item']['entity_id'],
        'contribution_id' => $contribution->id,
      ]);
    }
  }

  /**
   * Creates remaining instalments for the payment plan.
   *
   * @param int $recurringContributionId
   */
  private static function createRemainingInstalments($recurringContributionId) {
    $instalmentsHandler = new CRM_MembershipExtras_Service_MembershipInstalmentsHandler($recurringContributionId);
    $instalmentsHandler->createRemainingInstalmentContributionsUpfront();
  }

  /**
   * Checks if the given line item is a membership.
   *
   * @param array $lineItem
   *
   * @return bool
   */
  private static function isMembershipLineItem($lineItem) {
    if ($lineItem['entity_table'] == 'civicrm_membership') {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if the contact has a membership
   * with the same type and return its id
   * if there is any.
   *
   * @param int $priceFieldValueId
   *
   * @return int|NULL
   */
  private static function getMembershipIdWithSameTypeIfExist($priceFieldValueId) {
    $priceFieldValue = self::getPriceFieldValue($priceFieldValueId);
    $membershipTypeId = $priceFieldValue['membership_type_id'];
    $membership = civicrm_api3('Membership', 'get', [
      'sequential'   => 1,
      'membership_type_id' => $membershipTypeId,
      'contact_id' => self::$paymentPlanMembershipOrder->contactId,
    ]);

    if (!empty($membership['count'])) {
      return $membership['values'][0]['id'];
    }

    return NULL;
  }

  /**
   * Obtains data for price field value identified with given ID.
   *
   * @param int $priceFieldValueID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function getPriceFieldValue($priceFieldValueID) {
    return civicrm_api3('PriceFieldValue', 'getsingle', [
      'id' => $priceFieldValueID,
    ]);
  }

  /**
   * Creates the membership.
   *
   * @param array $lineItem
   * @param array $recurringContribution
   *
   * @return int
   * @throws \CiviCRM_API3_Exception
   */
  private static function createMembership($lineItem, $recurringContribution) {
    $priceFieldValue = self::getPriceFieldValue($lineItem['price_field_value_id']);

    $membershipCreateResult = MembershipFabricator::fabricate([
      'contact_id' => $recurringContribution['contact_id'],
      'membership_type_id' => $priceFieldValue['membership_type_id'],
      'join_date' => CRM_Utils_Array::value('join_date', $lineItem, self::$paymentPlanMembershipOrder->membershipJoinDate),
      'start_date' => CRM_Utils_Array::value('start_date', $lineItem, self::$paymentPlanMembershipOrder->membershipStartDate),
      'end_date' => CRM_Utils_Array::value('end_date', $lineItem, self::$paymentPlanMembershipOrder->membershipEndDate),
      'contribution_recur_id' => $recurringContribution['id'],
      'financial_type_id' => $lineItem['financial_type_id'],
      'skipLineItem' => 1,
    ]);

    return $membershipCreateResult['id'];
  }

}
