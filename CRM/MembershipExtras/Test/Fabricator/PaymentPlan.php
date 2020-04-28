<?php
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringLineItem as RecurringLineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contribution as ContributionFabricator;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_PaymentPlan
 */
class CRM_MembershipExtras_Test_Fabricator_PaymentPlan {

  /**
   * Fabricates a payment plan with the provided information.
   *
   * @param array $recurringContributionParams
   * @param array $lineItemParams
   * @param array $contributionParams
   *
   * @return array
   * @throws \Exception
   */
  public static function fabricate($recurringContributionParams, $lineItemParams, $contributionParams) {
    $recurringContribution = self::createRecurringContribution($recurringContributionParams);
    $lineItems = self::createRecurringLineItems($recurringContribution, $lineItemParams);
    self::updateRecurringContributionAmount($recurringContribution['id']);
    self::createInstallments($recurringContribution, $contributionParams, $lineItems);

    return $recurringContribution;
  }

  /**
   * Creates the recurring contribution.
   *
   * @param array $recurringContributionParams
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function createRecurringContribution($recurringContributionParams) {
    return RecurringContributionFabricator::fabricate($recurringContributionParams);
  }

  /**
   * Creates the recurring line items and associates.
   *
   * Creates the given line items and associates the to the recurring
   * contribution.
   *
   * @param array $recurringContribution
   * @param array $lineItems
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function createRecurringLineItems($recurringContribution, $lineItems) {
    $createdLines = [];

    foreach ($lineItems as $line) {
      $priceFieldValue = self::getPriceFieldValue($line['price_field_value_id']);
      if (self::isMembershipLineItem($line, $priceFieldValue)) {
        $membershipID = self::createMembership($recurringContribution, $line, $priceFieldValue);
        $line['entity_id'] = $membershipID;
        $line['entity_table'] = 'civicrm_membership';
      }

      $newLineItem = LineItemFabricator::fabricate($line);
      $recurringLineItem = RecurringLineItemFabricator::fabricate([
        'contribution_recur_id' => $recurringContribution['id'],
        'line_item_id' => $newLineItem['id'],
        'start_date' => $recurringContribution['start_date'],
        'auto_renew' => $recurringContribution['auto_renew'],
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
   * @param $recurringContributionID
   *
   * @throws \CiviCRM_API3_Exception
   */
  private static function updateRecurringContributionAmount($recurringContributionID) {
    $totalAmount = self::calculateRecurringContributionTotalAmount($recurringContributionID);
    civicrm_api3('ContributionRecur', 'create', [
      'id' => $recurringContributionID,
      'amount' => $totalAmount,
    ]);
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
   * Creates the payment plan's installments.
   *
   * @param array $recurringContribution
   * @param array $contribution
   * @param array $lineItems
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  private static function createInstallments($recurringContribution, $contribution, $lineItems) {
    self::createFirstInstallment($recurringContribution, $contribution, $lineItems);
    self::createRemainingInstallments($recurringContribution);
  }

  /**
   * Creates the first contribution for the payment plan.
   *
   * @param array $recurringContribution
   * @param array $contributionParams
   * @param array $lineItems
   *
   * @throws \CRM_Core_Exception
   * @throws \CiviCRM_API3_Exception
   */
  private static function createFirstInstallment($recurringContribution, $contributionParams, &$lineItems) {
    $params = array_merge(
      [
        'is_pay_later' => TRUE,
        'skipLineItem' => 1,
        'skipCleanMoney' => TRUE,
        'receive_date' => $recurringContribution['start_date'],
        'contribution_recur_id' => $recurringContribution['id'],
      ],
      $contributionParams
    );
    $contribution = ContributionFabricator::fabricate($params);

    foreach($lineItems as &$line) {
      unset($line['line_item']['id']);
      $line['line_item']['contribution_id'] = $contribution['id'];

      if ($line['line_item']['entity_table'] === 'civicrm_contribution') {
        $line['line_item']['entity_id'] = $contribution['id'];
      }

      self::createLineItemForContribution($contribution['id'], $line);
    }
  }

  /**
   * Creates the line item for the contribution identified by the given ID.
   *
   * @param int $contributionID
   * @param array $line
   *
   * @throws \CiviCRM_API3_Exception
   */
  private static function createLineItemForContribution($contributionID, $line) {
    $contribution = CRM_Contribute_BAO_Contribution::findById($contributionID);
    $newLineItem = CRM_Price_BAO_LineItem::create($line['line_item']);
    CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution);

    if (!empty($contribution->tax_amount) && !empty($newLineItem->tax_amount)) {
      CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution, TRUE);
    }

    if (self::isMembershipLineItem($line)) {
      CRM_Member_BAO_MembershipPayment::create([
        'membership_id' => $line['entity_id'],
        'contribution_id' => $contribution->id,
      ]);
    }
  }

  /**
   * Creates remaining installments for the payment plan.
   *
   * @param array $recurringContribution
   */
  private static function createRemainingInstallments($recurringContribution) {
    $installmentsHandler = new CRM_MembershipExtras_Service_MembershipInstallmentsHandler($recurringContribution['id']);
    $installmentsHandler->createRemainingInstalmentContributionsUpfront();
  }

  /**
   * Checks if the given line item is a membership.
   *
   * @param array $priceFieldValue
   * @param array $lineItem
   *
   * @return bool
   */
  private static function isMembershipLineItem($lineItem, $priceFieldValue = NULL) {
    if (isset($lineItem) && isset($lineItem['entity_table']) && $lineItem['entity_table'] == 'civicrm_membership') {
      return TRUE;
    }

    if (isset($priceFieldValue) && !empty($priceFieldValue['membership_type_id'])) {
      return TRUE;
    }

    return FALSE;
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
   * @param array $recurringContribution
   * @param array $line
   * @param array $priceFieldValue
   *
   * @return int
   * @throws \CiviCRM_API3_Exception
   */
  private static function createMembership($recurringContribution, $line, $priceFieldValue) {
    $membershipCreateResult = MembershipFabricator::fabricate([
      'contact_id' => $recurringContribution['contact_id'],
      'membership_type_id' => $priceFieldValue['membership_type_id'],
      'join_date' => CRM_Utils_Array::value('join_date', $line, date('YmdHis')),
      'start_date' => CRM_Utils_Array::value('start_date', $line, $recurringContribution['start_date']),
      'end_date' => CRM_Utils_Array::value('start_date', $line, 'null'),
      'contribution_recur_id' => $recurringContribution['id'],
      'financial_type_id' => $recurringContribution['financial_type_id'],
    ]);

    return $membershipCreateResult['id'];
  }

}
