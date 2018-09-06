<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Helper class to deal with operations done on contribution line items.
 */
class CRM_MembershipExtras_Service_FinancialTransactionManager {

  /**
   * Stores updated amounts for given contribution.
   *
   * @param array $contribution
   * @param double $updatedAmount
   * @param double $taxAmount
   */
  public static function recordAdjustedAmount($contribution, $updatedAmount, $taxAmount = NULL) {
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
   * Creates financial transaction records for the addition of the given line
   * item to its contribution.
   *
   * @param array $lineItem
   */
  public static function insertFinancialItemOnLineItemAddition($lineItem) {
    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'id' => $lineItem['contribution_id']
    ]);

    $trxnId = ['id' => self::createFinancialTrxnEntry($contribution['id'], $lineItem['line_total'])];
    $accountRelName = self::getFinancialAccountRelationship($contribution['id'], $lineItem['id']);
    $revenueFinancialAccountID = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount(
      $lineItem['financial_type_id'],
      $accountRelName
    );

    $newFinancialItem = array(
      'transaction_date' => date('YmdHis'),
      'contact_id' => $contribution['contact_id'],
      'description' => ($lineItem['qty'] != 1 ? $lineItem['qty'] . ' of ' : '') . $lineItem['label'],
      'amount' => $lineItem['line_total'],
      'currency' => $contribution['currency'],
      'financial_account_id' => $revenueFinancialAccountID,
      'status_id' => array_search('Unpaid', CRM_Core_PseudoConstant::get('CRM_Financial_DAO_FinancialItem', 'status_id')),
      'entity_table' => 'civicrm_line_item',
      'entity_id' => $lineItem['id'],
    );

    // create financial item for added line item
    $newFinancialItemDAO = CRM_Financial_BAO_FinancialItem::create($newFinancialItem, NULL, $trxnId);
    if (!empty($lineItem['tax_amount']) && $lineItem['tax_amount'] != 0) {
      $taxTerm = CRM_Utils_Array::value('tax_term', Civi::settings()->get('contribution_invoice_settings'));
      $taxFinancialItemInfo = array_merge($newFinancialItem, array(
        'amount' => $lineItem['tax_amount'],
        'description' => $taxTerm,
        'financial_account_id' => self::getTaxFinancialAccountId($lineItem['financial_type_id']),
      ));
      // create financial item for tax amount related to added line item
      CRM_Financial_BAO_FinancialItem::create($taxFinancialItemInfo, NULL, $trxnId);
    }

    $lineItem['financial_item_id'] = $newFinancialItemDAO->id;
    self::createDeferredTrxn($contribution['id'], $lineItem, 'addLineItem');
  }

  /**
   * Get financial account id has 'Sales Tax Account is' account relationship
   * with financial type.
   *
   * @param int $financialTypeId
   *
   * @return mixed
   */
  public static function getTaxFinancialAccountId($financialTypeId) {
    $accountRel = key(CRM_Core_PseudoConstant::accountOptionValues('account_relationship', NULL, " AND v.name LIKE 'Sales Tax Account is' "));
    $searchParams = array(
      'entity_table' => 'civicrm_financial_type',
      'entity_id' => $financialTypeId,
      'account_relationship' => $accountRel,
    );
    $result = array();
    CRM_Financial_BAO_FinancialTypeAccount::retrieve($searchParams, $result);

    return CRM_Utils_Array::value('financial_account_id', $result);
  }

  /**
   * Inserts financial item to reflect change done on contribution on line item
   * deletion.
   *
   * @param array $lineItemBefore
   */
  public static function insertFinancialItemOnLineItemDeletion($lineItemBefore) {
    $lineItemAfter = civicrm_api3('LineItem', 'getsingle', [
      'id' => $lineItemBefore['id'],
    ]);

    $lineItemBefore['tax_amount'] = CRM_Utils_Array::value('tax_amount', $lineItemBefore, 0);
    $lineItemAfter['tax_amount'] = CRM_Utils_Array::value('tax_amount', $lineItemAfter, 0);

    $deltaTaxAmount = $lineItemAfter['tax_amount'] - $lineItemBefore['tax_amount'];
    $deltaAmount = $lineItemAfter['line_total'] - $lineItemBefore['line_total'];

    if ($deltaAmount != 0 || $deltaTaxAmount != 0) {
      $previousFinancialItem = CRM_Financial_BAO_FinancialItem::getPreviousFinancialItem($lineItemBefore['id']);
      $financialItem = [
        'transaction_date' => date('YmdHis'),
        'contact_id' => $previousFinancialItem['contact_id'],
        'description' => ($lineItemAfter['qty'] > 1 ? $lineItemAfter['qty'] . ' of ' : '') . $lineItemAfter['label'],
        'currency' => $previousFinancialItem['currency'],
        'financial_account_id' => $previousFinancialItem['financial_account_id'],
        'entity_id' => $lineItemBefore['id'],
        'entity_table' => 'civicrm_line_item',
      ];

      self::recordChangeInAmount(
        $lineItemAfter['contribution_id'],
        $financialItem,
        $deltaAmount,
        $deltaTaxAmount
      );
    }
  }

  /**
   * Stores financial transaction entry detailing a change in amount to a
   * contribution.
   *
   * @param int $contributionId
   * @param array $financialItem
   * @param double $deltaAmount
   * @param double $deltaTaxAmount
   */
  public static function recordChangeInAmount($contributionId, $financialItem, $deltaAmount, $deltaTaxAmount) {
    $trxnId = ['id' => self::createFinancialTrxnEntry($contributionId, $deltaAmount + $deltaTaxAmount)];
    $accountRelName = self::getFinancialAccountRelationship($contributionId, $financialItem['entity_id']);
    $lineItem = civicrm_api3('LineItem', 'getsingle', ['id' => $financialItem['entity_id']]);

    $financialItem['amount'] = $deltaAmount;
    $financialItem['status_id'] = CRM_Core_PseudoConstant::getKey('CRM_Financial_DAO_FinancialItem', 'status_id', 'Unpaid');
    $financialItem['financial_account_id'] = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($lineItem['financial_type_id'], $accountRelName);

    $ftItem = CRM_Financial_BAO_FinancialItem::create($financialItem, NULL, $trxnId);

    if ($deltaTaxAmount != 0) {
      $taxTerm = CRM_Utils_Array::value('tax_term', Civi::settings()->get('contribution_invoice_settings'));
      $taxFinancialItemInfo = array_merge($financialItem, [
        'amount' => $deltaTaxAmount,
        'description' => $taxTerm,
        'financial_account_id' => CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($lineItem['financial_type_id'], 'Sales Tax Account is'),
      ]);

      CRM_Financial_BAO_FinancialItem::create($taxFinancialItemInfo, NULL, $trxnId);
    }

    $lineItem['deferred_line_total'] = $deltaAmount;
    $lineItem['financial_item_id'] = $ftItem->id;

    self::createDeferredTrxn($contributionId, $lineItem, 'UpdateLineItem');
  }

  /**
   * Creates a financial transaction for the given contribution.
   *
   * @param int $contributionId
   * @param double $amount
   * @param int $toFinancialAccount
   *
   * @return int
   */
  public static function createFinancialTrxnEntry($contributionId, $amount, $toFinancialAccount = NULL) {
    $contribution = civicrm_api3('Contribution', 'getsingle', ['id' => $contributionId]);
    $isPayment = TRUE;

    if (!$toFinancialAccount) {
      $toFinancialAccount = CRM_Contribute_PseudoConstant::getRelationalFinancialAccount($contribution['financial_type_id'], 'Accounts Receivable Account is');
      $isPayment = FALSE;
    }

    $adjustedTrxnValues = [
      'from_financial_account_id' => NULL,
      'to_financial_account_id' => $toFinancialAccount,
      'total_amount' => $amount,
      'net_amount' => $amount,
      'status_id' => $contribution['contribution_status_id'],
      'payment_instrument_id' => $contribution['payment_instrument_id'],
      'contribution_id' => $contributionId,
      'trxn_date' => date('YmdHis'),
      'currency' => $contribution['currency'],
      'is_payment' => $isPayment,
    ];
    $adjustedTrxn = CRM_Core_BAO_FinancialTrxn::create($adjustedTrxnValues);

    return $adjustedTrxn->id;
  }

  /**
   * Returns financial account relationship name for the given contribution and
   * line item.
   *
   * @param int $contributionId
   * @param int $lineItemId
   *
   * @return string
   */
  public static function getFinancialAccountRelationship($contributionId, $lineItemId = 0) {
    $accountRelName = 'Income Account is';

    $contribution = civicrm_api3('Contribution', 'getsingle', [
      'return' => ['revenue_recognition_date', 'receive_date'],
      'id' => $contributionId,
    ]);

    $revenueDate = CRM_Utils_Array::value('revenue_recognition_date', $contribution, '');
    if (!empty($revenueDate)) {
      $revenueDate = date('Ymd', strtotime($revenueDate));
      $date = CRM_Utils_Array::value('receive_date', $contribution, date('Y-m-d'));
      $date = date('Ymd', strtotime($date));

      if ($revenueDate > $date || self::isMembership($lineItemId)) {
        $accountRelName = 'Deferred Revenue Account is';
      }
    }

    return $accountRelName;
  }

  /**
   * Checks if line item identified by given ID is for a membership.
   *
   * @param int $lineItemID
   *
   * @return bool
   */
  public static function isMembership($lineItemID) {
    if ($lineItemID) {
      $result = civicrm_api3('LineItem', 'getsingle', [
        'return' => ['price_field_value_id.membership_type_id'],
        'id' => $lineItemID,
      ]);

      if (!empty($result['price_field_value_id.membership_type_id'])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Create deferred transaction for given contribution and line item if
   * setting is enabled.
   *
   * @param int $contributionID
   * @param array $lineItem
   * @param string $context
   */
  public static function createDeferredTrxn($contributionID, $lineItem, $context) {
    if (CRM_Contribute_BAO_Contribution::checkContributeSettings('deferred_revenue_enabled')) {
      $lineItem = [$contributionID => [$lineItem['id'] => $lineItem]];

      $contribution = new CRM_Contribute_BAO_Contribution();
      $contribution->id = $contributionID;
      $contribution->find(TRUE);

      CRM_Core_BAO_FinancialTrxn::createDeferredTrxn($lineItem, $contribution, TRUE, $context);
    }
  }

  /**
   * Returns formatted amount for tax of a given contribution by calculating the
   * sum for tax for each line item in that contribution.
   *
   * @param int $contributionID
   *
   * @return string
   */
  public static function calculateTaxAmountTotalFromContributionID($contributionID) {
    $taxAmount = CRM_Core_DAO::singleValueQuery("
      SELECT SUM(COALESCE(tax_amount,0)) 
      FROM civicrm_line_item 
      WHERE contribution_id = $contributionID 
      AND qty > 0 
    ");

    return MoneyUtilities::format($taxAmount, NULL, NULL, TRUE);
  }

}
