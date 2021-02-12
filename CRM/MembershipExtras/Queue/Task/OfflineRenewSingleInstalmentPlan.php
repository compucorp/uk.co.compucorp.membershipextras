<?php
use CRM_MembershipExtras_Queue_Task_OfflineRenewPaymentPlan as OfflineRenewPaymentPlanTask;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator as InstalmentReceiveDateCalculator;

/**
 * Renews the payment plan and the related memberships if it paid by once and
 * not using instalments.
 *
 * Paid by once (no instalments) payment plan get renewed by creating single
 * pending contribution that links to the already existing recurring
 * contribution.
 */
class CRM_MembershipExtras_Queue_Task_OfflineRenewSingleInstalmentPlan extends OfflineRenewPaymentPlanTask {

  /**
   * @inheritdoc
   * @throws \Exception
   */
  public function renew() {
    $this->membershipsStartDate = $this->calculateRenewedMembershipsStartDate();
    $this->paymentPlanStartDate = $this->membershipsStartDate;

    if (!$this->areAnyMembershipsFixed()) {
      $this->paymentPlanStartDate = $this->calculateNoInstalmentsPaymentPlanStartDate();
    }

    $this->endCurrentLineItemsAndCreateNewOnesForNextPeriod($this->currentRecurContributionID);
    $this->updateRecurringContributionAmount($this->currentRecurContributionID);
    $this->setNewRecurringContribution();
    $this->renewPaymentPlanMemberships($this->currentRecurContributionID);
    $this->buildLineItemsParams();
    $this->setTotalAndTaxAmount();
    $this->recordPaymentPlanFirstContribution();
  }

  /**
   * Checks if any of the memberships in the plan are fixed.
   *
   * @return bool
   */
  private function areAnyMembershipsFixed() {
    $currentPeriodLines = $this->getRecurringContributionLineItemsToBeRenewed($this->currentRecurContributionID);

    foreach ($currentPeriodLines as $lineItem) {
      if ($lineItem['entity_table'] != 'civicrm_membership') {
        continue;
      }

      if ($lineItem['period_type'] === 'fixed') {
        return TRUE;
      }

    }

    return FALSE;
  }

  /**
   * Sets new recurring contribution from current recurring contribution.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setNewRecurringContribution() {
    $this->newRecurringContributionID = $this->currentRecurContributionID;
    $this->newRecurringContribution = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->newRecurringContributionID,
    ]);
  }

  /**
   * Creates duplicates of line items associated to recurring contribution that
   * are set to auto-renew, don't have an end date and are not removed. It sets
   * end date for the old line items.
   *
   * @param $recurringContributionID
   *
   * @throws \Exception
   */
  private function endCurrentLineItemsAndCreateNewOnesForNextPeriod($recurringContributionID) {
    $newStartDate = new DateTime($this->membershipsStartDate);

    $newEndDate = new DateTime($this->membershipsStartDate);
    $newEndDate->sub(new DateInterval('P1D'));

    $lineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'is_removed' => 0,
      'end_date' => ['IS NULL' => 1],
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
      'options' => ['limit' => 0],
    ]);

    foreach ($lineItems['values'] as $line) {
      $this->endLineItem($line['id'], $newEndDate);

      if (!$line['auto_renew']) {
        continue;
      }

      $lineItemParams = $line['api.LineItem.getsingle'];
      $upgradableMembershipTypeId = NULL;
      if ($this->isMembershipLineItem($lineItemParams)) {
        $upgradableMembershipTypeId = $this->autoUpgradableMembershipCheckService->calculateMembershipTypeToUpgradeTo($lineItemParams['entity_id']);
      }

      if (!empty($upgradableMembershipTypeId)) {
        $this->createUpgradableSubscriptionMembershipLine($upgradableMembershipTypeId, $this->currentRecurContributionID, $newStartDate->format('Y-m-d'));
      }
      else {
        $this->duplicateSubscriptionLine($lineItemParams, $newStartDate->format('Y-m-d'), $this->currentRecurContributionID);
      }
    }
  }

  /**
   * Sets end date for given subscription line item ID.
   *
   * @param $lineID
   * @param \DateTime $endDate
   */
  private function endLineItem($lineID, DateTime $endDate) {
    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
      'id' => $lineID,
      'end_date' => $endDate->format('Y-m-d'),
    ]);
  }

  /**
   * Calculates the new start date for the payment plan
   * if its paid with no instalments.
   *
   * @return string
   * @throws \CiviCRM_API3_Exception
   */
  private function calculateNoInstalmentsPaymentPlanStartDate() {
    $currentRecurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $this->currentRecurContributionID,
    ])['values'][0];
    $instalmentReceiveDateCalculator = new InstalmentReceiveDateCalculator($currentRecurContribution);
    $instalmentReceiveDateCalculator->setStartDate($this->membershipsStartDate);

    return $instalmentReceiveDateCalculator->calculate();
  }

  /**
   * @inheritdoc
   */
  protected function getRecurringContributionLineItemsToBeRenewed($recurringContributionID) {
    $q = '
      SELECT msl.*, li.*, m.end_date AS memberhsip_end_date, cmt.period_type
      FROM membershipextras_subscription_line msl, civicrm_line_item li
      LEFT JOIN civicrm_membership m ON li.entity_id = m.id
      LEFT JOIN civicrm_membership_type cmt on m.membership_type_id = cmt.id
      WHERE msl.line_item_id = li.id
      AND msl.contribution_recur_id = %1
      AND msl.auto_renew = 1
      AND msl.is_removed = 0
      AND msl.end_date IS NULL
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
      AND msl.end_date IS NULL
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
   * @inheritdoc
   * @throws \CiviCRM_API3_Exception
   */
  protected function getNewPaymentPlanActiveLineItems() {
    $lineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->newRecurringContributionID,
      'is_removed' => 0,
      'end_date' => ['IS NULL' => 1],
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
      'end_date' => ['IS NULL' => 1],
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      foreach ($result['values'] as $lineItemData) {
        $totalAmount += $lineItemData['api.LineItem.getsingle']['line_total'];
        $totalAmount += $lineItemData['api.LineItem.getsingle']['tax_amount'];
      }
    }

    return MoneyUtilities::roundToCurrencyPrecision($totalAmount);
  }

}
