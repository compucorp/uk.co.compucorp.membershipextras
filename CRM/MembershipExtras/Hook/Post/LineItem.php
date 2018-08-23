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
  private $operation;

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

  public function __construct($operation, $objectId, CRM_Price_BAO_LineItem &$objectRef) {
    $this->operation = $operation;
    $this->id = $objectId;
    $this->lineItem = $objectRef;
  }

  /**
   * Post processes the set line item object.
   */
  public function postProcess() {
    if ($this->operation == 'create' && $this->isFirstContributionLineItemForPaymentPlan()) {
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
    $processorID = CRM_Utils_Array::value('contribution_id.contribution_recur_id.payment_processor_id', $paymentData, 0);

    if (!empty($recurringContributionID) && $this->isManualPaymentPlan($processorID)) {
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
   * Determines if the recurring contribution is offline (pay later) and is for
   * a payment plan.
   *
   * @param int $processorID
   *
   * @return bool
   */
  private function isManualPaymentPlan($processorID) {
    $manualPaymentProcessors = CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs();

    if (empty($processorID) || in_array($processorID, $manualPaymentProcessors)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Obtains information for the contribution and the recurring contribution
   * created for the payment plan.
   *
   * @return array
   */
  private function getPaymentData() {
    $contributionKeys = array_keys(self::$contributions);
    $contributionID = $this->lineItem->contribution_id;

    if (empty($contributionID)) {
      return array();
    }

    if (!in_array($contributionID, $contributionKeys)) {
      $result = civicrm_api3('MembershipPayment', 'get', [
        'sequential' => 1,
        'contribution_id' => $contributionID,
        'contribution_id.contribution_recur_id' => ['IS NOT NULL' => 1],
        'return' => [
          'contribution_id.contribution_recur_id',
          'contribution_id.contribution_recur_id.start_date',
          'contribution_id.contribution_recur_id.installments',
          'contribution_id.contribution_recur_id.payment_processor_id',
          'contribution_id.contribution_recur_id.auto_renew',
          'contribution_id.id',
          'membership_id'
        ],
      ]);

      if ($result['count'] > 0) {
        self::$contributions[$contributionID] = $result['values'][0];
      }
      else {
        self::$contributions[$contributionID] = array();
      }
    }

    return self::$contributions[$contributionID];
  }

  /**
   * Creates a copy of the line item and associates it to the recurring
   * contribution.
   */
  private function createLineItemForRecurringContribution() {
    $lineItemCopyParams = $this->lineItem->toArray();

    unset($lineItemCopyParams['id']);
    unset($lineItemCopyParams['contribution_id']);

    $lineItemCopy = civicrm_api3('LineItem', 'create', $lineItemCopyParams);
    $paymentData = $this->getPaymentData();

    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
      'contribution_recur_id' => $paymentData['contribution_id.contribution_recur_id'],
      'line_item_id' => $lineItemCopy['id'],
      'start_date' => $paymentData['contribution_id.contribution_recur_id.start_date'],
      'auto_renew' => CRM_Utils_String::strtobool($paymentData['contribution_id.contribution_recur_id.auto_renew']),
    ]);
  }

}
