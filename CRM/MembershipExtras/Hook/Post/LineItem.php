<?php

/**
 * Post-processes Line items.
 */
class CRM_MembershipExtras_Hook_Post_LineItem {

  /**
   * Operation being done on the line item
   *
   * @var string
   */
  private $op;

  /**
   * ID of line item.
   *
   * @var int
   */
  private $id;

  /**
   * Reference to Line Item BAO.
   *
   * @var \CRM_Price_BAO_LineItem
   */
  private $lineItem;

  /**
   * List of contributions for line items being processed.
   *
   * @var array
   */
  private static $contributions = [];

  public function __construct($op, $objectId, CRM_Price_BAO_LineItem &$objectRef) {
    $this->op = $op;
    $this->id = $objectId;
    $this->lineItem = $objectRef;
  }

  /**
   * Post processes the set line item object.
   */
  public function postProcess() {
    if ($this->op == 'create' && $this->isFirstContributionLineItemForPaymentPlan()) {
      $this->createLineItemForRecurringContribution();
    }
  }

  /**
   * Checks if the current line item is being created for the first contribution
   * of a payment plan.
   *
   * @return bool
   */
  private function isFirstContributionLineItemForPaymentPlan() {
    $paymentData = $this->getPaymentData();
    $recurringContributionID = CRM_Utils_Array::value('contribution_id.contribution_recur_id', $paymentData, 0);

    if (!empty($recurringContributionID)) {
      $contributionCount = civicrm_api3('Contribution', 'getcount', [
        'contribution_recur_id' => $recurringContributionID,
      ]);

      if ($contributionCount == 1) {
        return true;
      }
    }

    return false;
  }

  /**
   * Obtains information for the contribution and the recurring contribution
   * created for the payment plan.
   *
   * @return array
   */
  private function getPaymentData() {
    $contributionKeys = array_keys(self::$contributions);

    if (!in_array($this->lineItem->contribution_id, $contributionKeys)) {
      $result = civicrm_api3('MembershipPayment', 'get', [
        'sequential' => 1,
        'contribution_id' => $this->lineItem->contribution_id,
        'contribution_id.contribution_recur_id' => ['IS NOT NULL' => 1],
        'return' => [
          'contribution_id.contribution_recur_id',
          'contribution_id.contribution_recur_id.start_date',
          'contribution_id.contribution_recur_id.auto_renew',
          'contribution_id.id',
          'membership_id'
        ],
      ]);

      if ($result['count'] > 0) {
        self::$contributions[$this->lineItem->contribution_id] = $result['values'][0];
      }
      else {
        self::$contributions[$this->lineItem->contribution_id] = array();
      }
    }

    return self::$contributions[$this->lineItem->contribution_id];
  }

  /**
   * Creates a copy of the line item and associates it to the recurring
   * contribution.
   */
  private function createLineItemForRecurringContribution() {
    $lineItemCopyParams = $this->lineItem->toArray();
    unset($lineItemCopyParams['id']);
    unset($lineItemCopyParams['contribution_id']);

    civicrm_api3('LineItem', 'create', $lineItemCopyParams);

    $paymentData = $this->getPaymentData();
    $startDate = date('YmdHis', strtotime($paymentData['contribution_id.contribution_recur_id.start_date']));
    $q = '
      INSERT INTO membershipextras_contribution_recur_line_item (contribution_recur_id, line_item_id, start_date, end_date, auto_renew) 
      VALUES (%1, %2, %3, NULL, %4)
    ';
    CRM_Core_DAO::executeQuery($q, [
      1 => [$paymentData['contribution_id.contribution_recur_id'], 'Integer'],
      2 => [$this->lineItem->id, 'Integer'],
      3 => [$startDate, 'Timestamp'],
      4 => [$paymentData['contribution_id.contribution_recur_id.auto_renew'], 'Boolean'],
    ]);
  }

}
