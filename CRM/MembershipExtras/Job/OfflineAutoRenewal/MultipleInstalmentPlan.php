<?php
use CRM_MembershipExtras_Service_MembershipInstalmentsHandler as MembershipInstalmentsHandler;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Renews a payment plan with multiple instalments.
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstalmentPlan extends CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentPlan {

  /**
   * Returns a list of payment plans with multiple instalments that have at
   * least one line item ready to be renewed (ie. has an end date, is not
   * removed and is set to auto renew), meeting these conditions:
   *
   * - is using an offline payment processor (payment manual class).
   * - is set to auto-renew
   * - is not in status cancelled
   * - is active
   * - has at least one autorenewal subscription line item
   * - End date of at least one membership is equal to or smaller than today (with 'days to renew in advance' setting in mind)
   *
   * @return array
   */
  protected function getRecurringContributions() {
    $manualPaymentProcessorsIDs = implode(',', $this->manualPaymentProcessorIDs);
    $cancelledStatusID = $this->recurContributionStatusesNameMap['Cancelled'];
    $daysToRenewInAdvance = $this->daysToRenewInAdvance;

    $query = "
      SELECT ccr.id as contribution_recur_id, ccr.installments
        FROM civicrm_contribution_recur ccr
   LEFT JOIN membershipextras_subscription_line msl ON msl.contribution_recur_id = ccr.id
   LEFT JOIN civicrm_line_item cli ON msl.line_item_id = cli.id
   LEFT JOIN civicrm_membership cm ON (cm.id = cli.entity_id AND cli.entity_table = 'civicrm_membership')
   LEFT JOIN civicrm_value_payment_plan_extra_attributes ppea ON ppea.entity_id = ccr.id
       WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorsIDs}))
         AND ccr.installments > 1
         AND ccr.auto_renew = 1
         AND ccr.contribution_status_id != {$cancelledStatusID}
         AND ppea.is_active = 1
         AND msl.auto_renew = 1
         AND msl.is_removed = 0
    GROUP BY ccr.id
      HAVING MIN(cm.end_date) <= DATE_ADD(CURDATE(), INTERVAL {$daysToRenewInAdvance} DAY)
          OR (
            COUNT(cm.id) = 0
            AND COUNT(msl.id) > 0
          )
    ";
    $recurContributions = CRM_Core_DAO::executeQuery($query);

    $recurContributionsList = [];
    while ($recurContributions->fetch()) {
      $recurContribution['contribution_recur_id'] = $recurContributions->contribution_recur_id;
      $recurContribution['installments'] = $recurContributions->installments;
      $recurContributionsList[] = $recurContribution;
    }

    return $recurContributionsList;
  }

  /**
   * @inheritdoc
   */
  public function renew() {
    $this->createRecurringContribution();
    $this->renewPaymentPlanMemberships($this->newRecurringContributionID);
    $this->buildLineItemsParams();
    $this->setTotalAndTaxAmount();
    $this->recordPaymentPlanFirstContribution();

    $instalmentsHandler = new MembershipInstalmentsHandler($this->newRecurringContributionID);
    $instalmentsHandler->createRemainingInstalmentContributionsUpfront();

    $nextContributionDateService = new CRM_MembershipExtras_Service_PaymentPlanNextContributionDate($this->newRecurringContributionID, 'renewal');
    $nextContributionDateService->calculateAndUpdate();
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
    $this->paymentPlanStartDate = $this->currentRecurringContribution['next_sched_contribution_date'];
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
    $this->setActiveRecuringContribution($currentRecurContribution['id'], $newRecurringContribution['id']);
    $this->copyRecurringLineItems($currentRecurContribution, $newRecurringContribution);
    $this->updateRecurringContributionAmount($newRecurringContribution['id']);

    $this->newRecurringContribution = $newRecurringContribution;
    $this->newRecurringContributionID = $newRecurringContribution['id'];
  }

  /**
   * Obtains payment method name, given its ID.
   *
   * @param int $paymentMethodId
   *
   * @return array
   */
  private function getPaymentMethodNameFromItsId($paymentMethodId) {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'name',
      'option_group_id' => 'payment_instrument',
      'value' => $paymentMethodId,
    ]);
  }

  /**
   * Sets the new recurring contribution
   * as active and the previous one as inactive.
   *
   * @param int $currentContributionID
   * @param int $nextContributionID
   */
  private function setActiveRecuringContribution($currentContributionID, $nextContributionID) {
    $isActivePaymentPlanFieldId = $this->getCustomFieldID('payment_plan_extra_attributes', 'is_active');

    civicrm_api3('ContributionRecur', 'create', [
      'id' => $currentContributionID,
      'custom_' . $isActivePaymentPlanFieldId => 0,
    ]);

    civicrm_api3('ContributionRecur', 'create', [
      'id' => $nextContributionID,
      'custom_' . $isActivePaymentPlanFieldId => 1,
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
