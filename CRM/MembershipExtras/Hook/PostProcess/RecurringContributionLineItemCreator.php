<?php

use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

class CRM_MembershipExtras_Hook_PostProcess_RecurringContributionLineItemCreator {

  private $recurContributionID;

  private $recurContribution;

  private $previousPeriodFieldID;

  public function __construct($recurContributionID){
    $this->recurContributionID = $recurContributionID;
    $this->previousPeriodFieldID = $this->getCustomFieldID('related_payment_plan_periods', 'previous_period');
    $this->setRecurContribution();
  }

  private function setRecurContribution() {
    $recurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'return' => ['start_date', 'payment_processor_id', 'custom_' . $this->previousPeriodFieldID],
      'id' => $this->recurContributionID,
    ]);
    if ($recurContribution['count'] < 1) {
      $this->recurContribution =  NULL;
      return;
    }

    $this->recurContribution =  $recurContribution['values'][0];
  }

  public function create() {
    $processorID = CRM_Utils_Array::value('payment_processor_id', $this->recurContribution);
    $hasPreviousPeriod = CRM_Utils_Array::value('custom_' . $this->previousPeriodFieldID, $this->recurContribution, FALSE);
    if ($hasPreviousPeriod || !ManualPaymentProcessors::isManualPaymentProcessor($processorID)) {
      return;
    }

    $lastContributionLineItems = $this->getLastContributionLineItems();
    if (empty($lastContributionLineItems)) {
      return;
    }

    foreach ($lastContributionLineItems as $lineItemParams) {
      $this->createRecurLineItem($lineItemParams);
    }
  }

  private function getLastContributionLineItems() {
    try {
      $lastContributionId = civicrm_api3('Contribution', 'getvalue', [
        'return' => 'id',
        'contribution_recur_id' => $this->recurContributionID,
        'options' => ['limit' => 1, 'sort' => 'id DESC'],
      ]);
    } catch (CiviCRM_API3_Exception $exception) {
      return NULL;
    }

    $lastContributionLineItems = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'return' => ['entity_table', 'entity_id', 'price_field_id',
        'label', 'qty', 'unit_price', 'line_total', 'participant_count', 'id',
        'price_field_value_id', 'financial_type_id', 'non_deductible_amount', 'tax_amount'],
      'contribution_id' => $lastContributionId,
      'options' => ['limit' => 0],
    ]);
    if ($lastContributionLineItems['count'] < 1) {
      return NULL;
    }

    return $lastContributionLineItems['values'];
  }

  private function createRecurLineItem($lineItemParams) {
    unset($lineItemParams['id']);
    $lineItemCopy = civicrm_api3('LineItem', 'create', $lineItemParams);

    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
      'contribution_recur_id' => $this->recurContributionID,
      'line_item_id' => $lineItemCopy['id'],
      'start_date' => $this->recurContribution['start_date'],
      'auto_renew' => TRUE,
    ]);
  }

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

}
