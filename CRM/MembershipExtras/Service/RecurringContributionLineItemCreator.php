<?php

use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;
use CRM_MembershipExtras_Service_MembershipEndDateCalculator as MembershipEndDateCalculator;

class CRM_MembershipExtras_Service_RecurringContributionLineItemCreator {

  private $recurContributionID;

  private $recurContribution;

  private $calculateAutorenewalFlag = FALSE;

  public function __construct($recurContributionID) {
    $this->recurContributionID = $recurContributionID;
    $this->setRecurContribution();
  }

  private function setRecurContribution() {
    $recurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'return' => ['start_date', 'payment_processor_id'],
      'id' => $this->recurContributionID,
    ]);
    if ($recurContribution['count'] < 1) {
      $this->recurContribution = NULL;
      return;
    }

    $this->recurContribution = $recurContribution['values'][0];
  }

  /**
   * When called, it will force the class
   * to calculate the auto-renew flag if
   * it should be set or not when
   * creating the line items.
   */
  public function forceAutorenewalFlagCalculation() {
    $this->calculateAutorenewalFlag = TRUE;
  }

  public function create() {
    $processorID = CRM_Utils_Array::value('payment_processor_id', $this->recurContribution);
    if (!ManualPaymentProcessors::isManualPaymentProcessor($processorID)) {
      return;
    }

    $lastContributionLineItems = $this->getLastContributionLineItems();
    if (empty($lastContributionLineItems)) {
      return;
    }

    $earliestStartDate = $this->getEarliestMembershipStartDate($lastContributionLineItems);
    foreach ($lastContributionLineItems as $lineItemParams) {
      $this->createRecurLineItem($lineItemParams, $earliestStartDate);
    }
  }

  private function getLastContributionLineItems() {
    try {
      $lastContributionId = civicrm_api3('Contribution', 'getvalue', [
        'return' => 'id',
        'contribution_recur_id' => $this->recurContributionID,
        'options' => ['limit' => 1, 'sort' => 'id DESC'],
      ]);
      $lineItemsFilterParams = [
        'sequential' => 1,
        'return' => [
          'entity_table', 'entity_id', 'price_field_id', 'label', 'qty',
          'unit_price', 'line_total', 'participant_count', 'id',
          'price_field_value_id', 'financial_type_id', 'non_deductible_amount',
          'tax_amount',
        ],
        'contribution_id' => $lastContributionId,
        'api.Membership.get' => ['id' => '$value.entity_id'],
        'options' => ['limit' => 0],
      ];

      $lastContributionLineItems = civicrm_api3('LineItem', 'get', $lineItemsFilterParams);
    }
    catch (CiviCRM_API3_Exception $exception) {
      return [];
    }

    if ($lastContributionLineItems['count'] < 1) {
      return [];
    }

    return $lastContributionLineItems['values'];
  }

  /**
   * Obtains earliest membership start date from the given line items.
   *
   * @param array $lineItems
   *   List of line items to check.
   *
   * @return null|string
   *   Earliest membership end date, if at least one membership is found. Null
   *   if no memberships are part of the payment plan.
   *
   * @throws \Exception
   */
  private function getEarliestMembershipStartDate($lineItems) {
    $latestEndDate = NULL;

    foreach ($lineItems as $line) {
      if ($line['entity_table'] !== 'civicrm_membership') {
        continue;
      }

      $previousEndDate = MembershipEndDateCalculator::calculatePreviousEndDate($line['entity_id']);
      $endDate = new DateTime($previousEndDate);

      if (!isset($latestEndDate)) {
        $latestEndDate = $endDate;
      }
      elseif ($latestEndDate < $endDate) {
        $latestEndDate = $endDate;
      }
    }

    if ($latestEndDate) {
      $latestEndDate->add(new DateInterval('P1D'));

      return $latestEndDate->format('Y-m-d');
    }

    return NULL;
  }

  private function createRecurLineItem($lineItemParams, $lineStartDate) {
    $autoRenew = TRUE;
    if ($this->calculateAutorenewalFlag) {
      $autoRenew = $this->calculateAutorenewalFlag($lineItemParams);
    }

    unset($lineItemParams['id']);
    $lineItemCopy = civicrm_api3('LineItem', 'create', $lineItemParams);

    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
      'contribution_recur_id' => $this->recurContributionID,
      'line_item_id' => $lineItemCopy['id'],
      'start_date' => $lineStartDate,
      'auto_renew' => $autoRenew,
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

  private function calculateAutorenewalFlag($lineItemParams) {
    if ($lineItemParams['entity_table'] != 'civicrm_membership') {
      return FALSE;
    }

    $autoRenew = TRUE;
    if (empty($lineItemParams['api.Membership.get']['values'][0]['contribution_recur_id'])) {
      $autoRenew = FALSE;
    }

    return $autoRenew;
  }

}
