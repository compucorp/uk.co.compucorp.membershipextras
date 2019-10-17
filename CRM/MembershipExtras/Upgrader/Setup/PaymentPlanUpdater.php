<?php

/**
 * Class CRM_MembershipExtras_Upgrader_Setup_PaymentPlanUpdater
 *
 * Updates existing payment plans so they are compatible with membership extras
 * extension.
 */
class CRM_MembershipExtras_Upgrader_Setup_PaymentPlanUpdater {

  /**
   * Array mapping existing memberships to their last payment plan.
   *
   * @var array
   */
  private $membershipsToPaymentPlanMap = [];

  /**
   * Updates current payment plans so they're compatible wit extension.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function run() {
    $sortedRecurContributions = $this->getSortedManualPaymentPlans();

    foreach ($sortedRecurContributions as $paymentPlan) {
      $this->recalculateCycleDay($paymentPlan);

      $lastInstalment = $this->getLastInstalmentForPaymentPlan($paymentPlan['id']);
      if (empty($lastInstalment['id'])) {
        continue;
      }

      $lineItems = $this->getLineItemsForContribution($lastInstalment['id']);
      $this->copyLastInstalmentLineItemsToRecurContrib($paymentPlan, $lineItems);

      $isAutoRenewPlanWithMultipleInstallments = ($paymentPlan['installments'] > 0) && $paymentPlan['auto_renew'];
      if ($isAutoRenewPlanWithMultipleInstallments) {
        $this->setPeriodLinkCustomFieldValues($paymentPlan['id'], $lineItems);
      }
    }
  }

  /**
   * Returns all existing manual payment plans.
   *
   * @return array
   *   List of payment plans, sorted by ID.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getSortedManualPaymentPlans() {
    $manualPaymentProcessors = CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs();
    $result = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'payment_processor_id' => ['IN' => $manualPaymentProcessors],
      'options' => ['limit' => 0, 'sort' => 'id'],
    ]);

    $paymentPlans = [];
    if ($result['count'] > 0) {
      $paymentPlans = $result['values'];
    }

    return $paymentPlans;
  }

  /**
   * Updates cycle day by recalculating it from the plan's start date.
   *
   * @param $paymentPlan
   *   Data for the payment plan.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function recalculateCycleDay($paymentPlan) {
    $cycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($paymentPlan['start_date'], $paymentPlan['frequency_unit']);
    civicrm_api3('ContributionRecur', 'create', [
      'id' => $paymentPlan['id'],
      'cycle_day' => $cycleDay,
    ]);
  }

  /**
   * Gets the last instalment of a payment plan.
   *
   * @param string $paymentPlanId
   *
   * @return array
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getLastInstalmentForPaymentPlan($paymentPlanId) {
    if (empty($paymentPlanId)) {
      return [];
    }

    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'options' => ['sort' => 'id DESC', 'limit' => 1],
      'contribution_recur_id' => $paymentPlanId,
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Get the contributions associated to a contribution id
   *
   * @param string $contributionId
   *
   * @return array
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getLineItemsForContribution($contributionId) {
    $result = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 0],
      'contribution_id' => $contributionId,
    ]);

    $lineItems = [];
    if ($result['count'] > 0) {
      $lineItems = $result['values'];
    }

    return $lineItems;
  }

  /**
   * Copy a payment plan's line items and create subscription
   * lines for each copied line item
   *
   * @param array $paymentPlan
   * @param array $lineItems
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function copyLastInstalmentLineItemsToRecurContrib($paymentPlan, $lineItems) {
    /*
     * If there is an exception thrown at some point, we need to make this
     * idempotent, so line items are not duplicated if the upgrader is ran more
     * than once.
     */
    if ($this->linesExistForPaymentPlan($paymentPlan)) {
      return;
    }

    foreach ($lineItems as $lineItem) {
      unset($lineItem['id']);
      unset($lineItem['contribution_id']);

      $params = array_merge($lineItem, [
        'api.ContributionRecurLineItem.create' => [
          'contribution_recur_id' => $paymentPlan['id'],
          'line_item_id' => '$value.id',
          'start_date' => $paymentPlan['start_date'],
          'end_date' => $paymentPlan['end_date'],
          'auto_renew' => $paymentPlan['auto_renew'],
          'is_removed' => 0,
        ],
      ]);
      civicrm_api3('LineItem', 'create', $params);
    }
  }

  /**
   * Checks if payment plan already has lines.
   *
   * @param array $paymentPlan
   *   Data for the payment plan.
   *
   * @return bool
   *   TRUE if it finds a line item, FALSE otherwise.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function linesExistForPaymentPlan($paymentPlan) {
    $result = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlan['id'],
    ]);

    if ($result['count'] > 0) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if a date is older than 1 month
   *
   * @param string $date
   *
   * @return bool
   */
  private function isMoreThanOneMonthOld($date = null) {
    return $date && (strtotime($date) < strtotime('-30 days'));
  }

  /**
   * Creates values on new custom groups to link previous and next periods.
   *
   * A new custom group, with next_period and previous_period fields has been
   * added to record the relationship between successive recurring contributions
   * created as part of an auto-renewable payment plan with multiple
   * installments. This fields are used by auto-renew job to check which payment
   * plans are eligible for renewal (plans with next_period = NULL). Thus, old
   * plans need to be updated and these values set to avoid reneweing plans that
   * should not be renewed.
   *
   * @param $paymentPlanId
   * @param $lineItems
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setPeriodLinkCustomFieldValues($paymentPlanId, $lineItems) {
    $nextPeriodCustomFieldId = $this->getCustomFieldId('related_payment_plan_periods', 'next_period');
    $prevPeriodCustomFieldId = $this->getCustomFieldId('related_payment_plan_periods', 'previous_period');

    foreach ($lineItems as $line) {
      $isMembership = $line['entity_table'] == 'civicrm_membership' && !empty($line['entity_id']);
      if (!$isMembership) {
        continue;
      }

      $previousPeriodID = 'null';
      if (isset($this->membershipsToPaymentPlanMap[$line['entity_id']])) {
        $previousPeriodID = $this->membershipsToPaymentPlanMap[$line['entity_id']];
        civicrm_api3('ContributionRecur', 'create', [
          'id' => $previousPeriodID,
          'custom_' . $nextPeriodCustomFieldId => $paymentPlanId,
        ]);
      }

      $this->membershipsToPaymentPlanMap[$line['entity_id']] = $paymentPlanId;
    }

    civicrm_api3('ContributionRecur', 'create', [
      'id' => $paymentPlanId,
      'custom_' . $nextPeriodCustomFieldId => 'null',
      'custom_' . $prevPeriodCustomFieldId => $previousPeriodID,
    ]);
  }

  /**
   * Obtains ID of the field that has given group name and field name.
   *
   * @param $customGroupName
   *   Name of the custom group the field belongs to.
   * @param $customFieldName
   *   Machine name of the field.
   *
   * @return int | array
   *   ID of the field.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getCustomFieldId($customGroupName, $customFieldName) {
    return civicrm_api3('CustomField', 'getvalue', [
      'return' => 'id',
      'custom_group_id' => $customGroupName,
      'name' => $customFieldName,
    ]);
  }

}
