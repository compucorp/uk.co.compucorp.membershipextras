<?php
use CRM_MembershipExtras_Queue_Task_OfflineRenewPaymentPlan as OfflineRenewPaymentPlanTask;
use CRM_MembershipExtras_Service_MembershipInstallmentsHandler as MembershipInstalmentsHandler;
use CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator as InstalmentReceiveDateCalculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Renews a payment plan with multiple instalments.
 */
class CRM_MembershipExtras_Queue_Task_OfflineRenewMultipleInstalmentPlan extends OfflineRenewPaymentPlanTask {

  /**
   * @inheritdoc
   * @throws \Exception
   */
  public function renew() {
    $this->createRecurringContribution();
    $this->renewPaymentPlanMemberships($this->newRecurringContributionID);
    $this->buildLineItemsParams();
    $this->setTotalAndTaxAmount();
    $this->recordPaymentPlanFirstContribution();

    $instalmentsHandler = new MembershipInstalmentsHandler(
      $this->newRecurringContributionID
    );
    $instalmentsHandler->createRemainingInstalmentContributionsUpfront();
  }

  /**
   * Renews the current membership recurring contribution by creating a new one
   * based on its data.
   *
   * The new recurring contribution will then be set to be the current recurring
   * contribution.
   *
   * @throws \Exception
   */
  private function createRecurringContribution() {
    $currentRecurContribution = $this->currentRecurringContribution;
    $paymentProcessorID = !empty($currentRecurContribution['payment_processor_id']) ? $currentRecurContribution['payment_processor_id'] : NULL;

    $this->membershipsStartDate = $this->calculateRenewedMembershipsStartDate();
    $this->paymentPlanStartDate = $this->calculateNewPeriodStartDate();
    $paymentInstrumentName = $this->getPaymentMethodNameFromItsId($currentRecurContribution['payment_instrument_id']);

    $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', [
      'sequential' => 1,
      'contact_id' => $currentRecurContribution['contact_id'],
      'amount' => 0,
      'currency' => $currentRecurContribution['currency'],
      'frequency_unit' => $currentRecurContribution['frequency_unit'],
      'frequency_interval' => $currentRecurContribution['frequency_interval'],
      'installments' => $currentRecurContribution['installments'],
      'contribution_status_id' => 'Pending',
      'is_test' => $currentRecurContribution['is_test'],
      'auto_renew' => 1,
      'cycle_day' => $currentRecurContribution['cycle_day'],
      'payment_processor_id' => $paymentProcessorID,
      'financial_type_id' => $currentRecurContribution['financial_type_id'],
      'payment_instrument_id' => $paymentInstrumentName,
      'start_date' => $this->paymentPlanStartDate,
    ])['values'][0];

    CRM_MembershipExtras_Service_CustomFieldsCopier::copy(
      $currentRecurContribution['id'],
      $newRecurringContribution['id'],
      'ContributionRecur'
    );
    $this->updateFieldsLinkingPeriods($currentRecurContribution['id'], $newRecurringContribution['id']);
    $this->copyRecurringLineItems($currentRecurContribution, $newRecurringContribution);
    $this->updateRecurringContributionAmount($newRecurringContribution['id']);

    $this->newRecurringContribution = $newRecurringContribution;
    $this->newRecurringContributionID = $newRecurringContribution['id'];
  }

  /**
   * Calculates the new period's start date.
   *
   * @return string
   *   The new period's start date.
   * @throws \Exception
   */
  private function calculateNewPeriodStartDate() {
    $instalmentReceiveDateCalculator = new InstalmentReceiveDateCalculator($this->currentRecurringContribution);
    $instalmentReceiveDateCalculator->setStartDate($this->membershipsStartDate);
    return $instalmentReceiveDateCalculator->calculate();
  }

  /**
   * Obtains payment method name, given its ID.
   *
   * @param int $paymentMethodId
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getPaymentMethodNameFromItsId($paymentMethodId) {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'name',
      'option_group_id' => 'payment_instrument',
      'value' => $paymentMethodId,
    ]);
  }

  /**
   * Uses given ID's to set 'previous period' on new payment plan, and 'next
   * period' on current payment plan.
   *
   * @param int $currentContributionID
   * @param int $nextContributionID
   *
   * @throws \Exception
   */
  private function updateFieldsLinkingPeriods($currentContributionID, $nextContributionID) {
    $previousPeriodFieldID = $this->getCustomFieldID('related_payment_plan_periods', 'previous_period');
    $nextPeriodFieldID = $this->getCustomFieldID('related_payment_plan_periods', 'next_period');

    civicrm_api3('ContributionRecur', 'create', [
      'id' => $currentContributionID,
      'custom_' . $nextPeriodFieldID => $nextContributionID,
    ]);

    civicrm_api3('ContributionRecur', 'create', [
      'id' => $nextContributionID,
      'custom_' . $previousPeriodFieldID => $currentContributionID,
    ]);
  }

  /**
   * Creates copies of all line items set to auto-renew in previous recurring
   * contribution and associates them with the new recurring contribution.
   *
   * @param array $currentContribution
   * @param array $nextContribution
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function copyRecurringLineItems($currentContribution, $nextContribution) {
    $recurringLineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $currentContribution['id'],
      'auto_renew' => 1,
      'is_removed' => 0,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
      'options' => ['limit' => 0],
    ]);

    if (count($recurringLineItems['values']) == 0) {
      return;
    }

    foreach ($recurringLineItems['values'] as $lineItem) {
      $lineItemParams = $lineItem['api.LineItem.getsingle'];
      $upgradableMembershipTypeId = NULL;
      if ($this->isMembershipLineItem($lineItemParams)) {
        $upgradableMembershipTypeId = $this->autoUpgradableMembershipCheckService->calculateMembershipTypeToUpgradeTo($lineItemParams['entity_id']);
      }

      if (!empty($upgradableMembershipTypeId)) {
        $this->createUpgradableSubscriptionMembershipLine($upgradableMembershipTypeId, $nextContribution['id'], $this->membershipsStartDate);
        $this->makeSubscriptionLineItemNonRenewable($lineItem['id']);
      }
      else {
        $this->duplicateSubscriptionLine($lineItemParams, $this->membershipsStartDate, $nextContribution['id']);
      }
    }
  }

  private function makeSubscriptionLineItemNonRenewable($subscriptionLineItemId) {
    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
      'id' => $subscriptionLineItemId,
      'auto_renew' => 0,
    ]);
  }

  /**
   * @inheritdoc
   */
  protected function getRecurringContributionLineItemsToBeRenewed($recurringContributionID) {
    $q = '
      SELECT msl.*, li.*, m.end_date AS memberhsip_end_date
      FROM membershipextras_subscription_line msl, civicrm_line_item li
      LEFT JOIN civicrm_membership m ON li.entity_id = m.id
      WHERE msl.line_item_id = li.id
      AND msl.contribution_recur_id = %1
      AND msl.auto_renew = 1
      AND msl.is_removed = 0
      ';
    $dbResultSet = CRM_Core_DAO::executeQuery($q, [
      1 => [$recurringContributionID, 'Integer'],
    ]);

    $linesToBeRenewed = [];
    while ($dbResultSet->fetch()) {
      $linesToBeRenewed[] = $dbResultSet->toArray();
    }

    return $linesToBeRenewed;
  }

  /**
   * @inheritDoc
   */
  protected function getAllRecurringContributionActiveLineItems($recurringContributionID) {
    $q = '
      SELECT msl.*, li.*, m.end_date AS memberhsip_end_date
      FROM membershipextras_subscription_line msl, civicrm_line_item li
      LEFT JOIN civicrm_membership m ON li.entity_id = m.id
      WHERE msl.line_item_id = li.id
      AND msl.contribution_recur_id = %1
      AND msl.is_removed = 0
      ';
    $dbResultSet = CRM_Core_DAO::executeQuery($q, [
      1 => [$recurringContributionID, 'Integer'],
    ]);

    $activeLineItems = [];
    while ($dbResultSet->fetch()) {
      $activeLineItems[] = $dbResultSet->toArray();
    }

    return $activeLineItems;
  }

  /**
   * @inheritdoc
   * @throws \CiviCRM_API3_Exception
   */
  protected function getNewPaymentPlanActiveLineItems() {
    $lineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->newRecurringContributionID,
      'is_removed' => 0,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
      'options' => ['limit' => 0],
    ]);

    if (!$lineItems['count']) {
      return [];
    }

    $result = [];
    foreach ($lineItems['values'] as $line) {
      $lineData = $line['api.LineItem.getsingle'];
      $result[] = $lineData;
    }

    return $result;
  }

  /**
   * @inheritdoc
   * @throws \CiviCRM_API3_Exception
   */
  protected function calculateRecurringContributionTotalAmount($recurringContributionID) {
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
        $totalAmount += floatval($lineItemData['api.LineItem.getsingle']['tax_amount']);
      }
    }

    return MoneyUtilities::roundToCurrencyPrecision($totalAmount);
  }

}
