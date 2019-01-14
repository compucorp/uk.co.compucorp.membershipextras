<?php

/**
 * Post processes contributions.
 */
class CRM_MembershipExtras_Hook_Post_Contribution {

  /**
   * Operation being done on the contribution.
   *
   * @var string
   */
  private $operation;

  /**
   * ID of contribution.
   *
   * @var int
   */
  private $id;

  /**
   * Reference to Contribution BAO.
   *
   * @var \CRM_Contribute_BAO_Contribution
   */
  private $contribution;

  /**
   * Recurring contribution associated to the contribution.
   *
   * @var array
   */
  private $recurringContribution;

  /**
   * CRM_MembershipExtras_Hook_Post_Contribution constructor.
   *
   * @param $operation
   * @param $objectId
   * @param \CRM_Contribute_BAO_Contribution $objectRef
   */
  public function __construct($operation, $objectId, CRM_Contribute_BAO_Contribution &$objectRef) {
    $this->operation = $operation;
    $this->id = $objectId;
    $this->contribution = $objectRef;
    $this->recurringContribution = $this->getRecurringContribution($this->contribution->contribution_recur_id);
  }

  /**
   * Loads recurring contribution and returns its data in an array.
   *
   * @param int $id
   *
   * @return array
   */
  private function getRecurringContribution($id) {
    if (empty($id)) {
      return [];
    }

    $previousPeriodFieldID = $this->getCustomFieldID('related_payment_plan_periods', 'previous_period');

    $result = civicrm_api3('ContributionRecur', 'getsingle', [
      'sequential' => 1,
      'id' => $id,
      'return' => [
        'id',
        'start_date',
        'installments',
        'payment_processor_id',
        'auto_renew',
        'custom_' . $previousPeriodFieldID,
      ],
    ]);

    $previousPeriodID = CRM_Utils_Array::value(
      'custom_' . $previousPeriodFieldID,
      $result,
      NULL
    );
    $result['previous_period'] = $previousPeriodID;

    return $result;
  }

  /**
   * Post processes create/update operations done on Contributions.
   */
  public function postProcess() {
    if ($this->isFirstContributionForFirstPaymentPlan()) {
      $this->copyLineItemsForRecurringContribution();
    }
  }

  /**
   * Checks if the contribution is the first one for a payment plan and if line
   * items have been copied to its recurring contribution.
   *
   * @return bool
   */
  private function isFirstContributionForFirstPaymentPlan() {
    if (empty($this->contribution->contribution_recur_id)) {
      return FALSE;
    }

    $recurringContributionID = CRM_Utils_Array::value('id', $this->recurringContribution, 0);
    $processorID = CRM_Utils_Array::value('payment_processor_id', $this->recurringContribution, 0);
    $previousPeriod = CRM_Utils_Array::value('previous_period', $this->recurringContribution, 0);
    $recurringLineItemCount = $this->getRecurringLineItemsCount($recurringContributionID);

    if ($this->isManualPaymentPlan($processorID) && $previousPeriod == 0 && $recurringLineItemCount == 0) {
      $contributionCount = civicrm_api3('Contribution', 'getcount', [
        'contribution_recur_id' => $recurringContributionID,
      ]);

      if ($contributionCount == 1) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Returns number of recurring line items associated to the recurring
   * contribution.
   *
   * @param int $recurringContributionID
   *
   * @return mixed
   */
  private function getRecurringLineItemsCount($recurringContributionID) {
    $recurringLineItemsBAO = new CRM_MembershipExtras_BAO_ContributionRecurLineItem();
    $recurringLineItemsBAO->contribution_recur_id = $recurringContributionID;

    return $recurringLineItemsBAO->find();
  }

  /**
   * Obtains ID for custom field name in given group.
   *
   * @param $fieldGroup
   * @param $fieldName
   *
   * @return int
   */
  private function getCustomFieldID($fieldGroup, $fieldName) {
    $result = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => $fieldGroup,
      'name' => $fieldName,
    ]);

    if ($result['count'] > 0) {
      return $result['values'][0]['id'];
    }

    return 0;
  }

  /**
   * Determines if the given payment processor is offline (pay later).
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
   * Copies line items associated to the contribution and associates them to the
   * recurring contribution.
   */
  private function copyLineItemsForRecurringContribution() {
    $lineItems = $this->getContributionLineItems($this->id);

    foreach ($lineItems as $lineItemCopyParams) {
      unset($lineItemCopyParams['id']);
      unset($lineItemCopyParams['contribution_id']);

      $lineItemCopy = civicrm_api3('LineItem', 'create', $lineItemCopyParams);

      CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
        'contribution_recur_id' => $this->recurringContribution['id'],
        'line_item_id' => $lineItemCopy['id'],
        'start_date' => $this->recurringContribution['start_date'],
        'auto_renew' => CRM_Utils_String::strtobool($this->recurringContribution['auto_renew']),
      ]);
    }
  }

  /**
   * Returns an array with the line items associated to the given contribution
   * ID.
   * 
   * @param int $contributionID
   * 
   * @return array
   */
  private function getContributionLineItems($contributionID) {
    $result = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $contributionID,
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return [];
  }

}
