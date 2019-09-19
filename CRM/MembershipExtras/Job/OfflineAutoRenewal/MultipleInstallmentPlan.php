<?php
use CRM_MembershipExtras_Service_MembershipInstallmentsHandler as MembershipInstallmentsHandler;
use CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator as InstallmentReceiveDateCalculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Renews a payment plan with multiple installments.
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstallmentPlan extends CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentPlan {

  /**
   * Returns a list of payment plans with multiple installments that have at
   * least one line item ready to be renewed (ie. has an end date, is not
   * removed and is set to auto renew), mmeting these conditions:
   *
   * 1- is using an offline payment processor (payment manual class).
   * 2- has an end date.
   * 3- is set to auto-renew
   * 4- is not in status cancelled
   * 5- "Next Payment Plan Period" is empty
   * 6- has either of the following conditions:
   *    - end date of at least one membership is equal to or smaller than today
   *    - there are no related line items with memberships to be renewed and
   *      line items have an end date
   *
   * @return array
   */
  protected function getRecurringContributions() {
    $manualPaymentProcessorsIDs = implode(',', $this->manualPaymentProcessorIDs);
    $cancelledStatusID = $this->contributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $this->contributionStatusesNameMap['Refunded'];
    $daysToRenewInAdvance = $this->daysToRenewInAdvance;

    $query = "
      SELECT ccr.id as contribution_recur_id, ccr.installments 
        FROM civicrm_contribution_recur ccr
   LEFT JOIN membershipextras_subscription_line msl ON msl.contribution_recur_id = ccr.id
   LEFT JOIN civicrm_line_item cli ON msl.line_item_id = cli.id
   LEFT JOIN civicrm_membership cm ON (cm.id = cli.entity_id AND cli.entity_table = 'civicrm_membership')
   LEFT JOIN civicrm_value_payment_plan_periods ppp ON ppp.entity_id = ccr.id
       WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN ({$manualPaymentProcessorsIDs}))
         AND ccr.installments > 1
         AND ccr.end_date IS NOT NULL
         AND ccr.auto_renew = 1 
         AND (
          ccr.contribution_status_id != {$cancelledStatusID} 
          AND ccr.contribution_status_id != {$refundedStatusID}
         )
         AND ppp.next_period IS NULL
         AND msl.auto_renew = 1
         AND msl.is_removed = 0
         AND msl.end_date IS NOT NULL
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
    $this->createMissingPaymentPlanMemberships();
    $this->buildLineItemsParams();
    $this->setTotalAndTaxAmount();
    $this->recordPaymentPlanFirstContribution();

    $installmentsHandler = new MembershipInstallmentsHandler(
      $this->newRecurringContributionID
    );
    $installmentsHandler->createRemainingInstalmentContributionsUpfront();
  }

  /**
   * Renews the current membership recurring contribution by creating a new one
   * based on its data.
   *
   * The new recurring contribution will then be set to be the current recurring
   * contribution.
   */
  private function createRecurringContribution() {
    $currentRecurContribution = $this->currentRecurringContribution;
    $paymentProcessorID = !empty($currentRecurContribution['payment_processor_id']) ? $currentRecurContribution['payment_processor_id'] : NULL;

    $installmentReceiveDateCalculator = new InstallmentReceiveDateCalculator($currentRecurContribution);
    $this->paymentPlanStartDate = $installmentReceiveDateCalculator->calculate($currentRecurContribution['installments'] + 1);
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

    // The new recurring contribution is now the current one.
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
   * Uses given ID's to set 'previous period' on new payment plan, and 'next
   * period' on current payment plan.
   *
   * @param int $currentContributionID
   * @param int $nextContributionID
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
   */
  private function copyRecurringLineItems($currentContribution, $nextContribution) {
    $recurringLineItems = $this->getRecurringContributionLineItemsToBeRenewed($currentContribution['id']);

    if (count($recurringLineItems)) {
      foreach ($recurringLineItems as $lineItem) {
        unset($lineItem['id']);

        $isUpgraded = $this->autoUpgradeMembershipLineItem($lineItem, $currentContribution);
        if ($isUpgraded) {
          return;
        }

        $lineItem['unit_price'] = $this->calculateLineItemUnitPrice($lineItem);
        $lineItem['line_total'] = MoneyUtilities::roundToCurrencyPrecision($lineItem['unit_price'] * $lineItem['qty']);
        $lineItem['tax_amount'] = $this->calculateLineItemTaxAmount($lineItem['line_total'], $lineItem['financial_type_id']);

        $newLineItem = civicrm_api3('LineItem', 'create', $lineItem);
        CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
          'contribution_recur_id' => $nextContribution['id'],
          'line_item_id' => $newLineItem['id'],
          'start_date' => $nextContribution['start_date'],
          'auto_renew' => 1,
        ]);
      }
    }
  }

  /**
   * @inheritdoc
   */
  protected function getRecurringContributionLineItemsToBeRenewed($recurringContributionID) {
    $lineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'auto_renew' => 1,
      'is_removed' => 0,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
    ]);

    if (!$lineItems['count']) {
      return [];
    }

    $result = [];
    foreach ($lineItems['values'] as $line) {
      $lineData = $line['api.LineItem.getsingle'];
      $result[] =  $lineData;
    }

    return $result;
  }

  private function autoUpgradeMembershipLineItem($lineItem, $currentRecurContribution) {
    $priceFieldValue = !empty($lineItem['price_field_value_id']) ? $this->getPriceFieldValue($lineItem['price_field_value_id']) : NULL;
    if (!$this->isMembershipLineItem($lineItem, $priceFieldValue)) {
      return FALSE;
    }

    if ($lineItem['entity_table'] == 'civicrm_membership') {
      $currentMembershipTypeId = civicrm_api3('Membership', 'getsingle', [
        'id' => $lineItem['entity_id'],
      ])['membership_type_id'];
    } else {
      $currentMembershipTypeId = $priceFieldValue['membership_type_id'];
    }

    $currentMembershipTypeName = civicrm_api3('MembershipType', 'getvalue', [
      'return' => 'name',
      'id' => $currentMembershipTypeId,
    ]);

    // todo : student replaced with JSON grabber
    if ($currentMembershipTypeName == 'Student') {
      $membershipTypeAllowedLimitNum = 1; // todo : fetch from JSON
      $membershipTypeAllowedLimitTerm = 'year'; // todo : fetch from JSON
      $totalPeriodsCount = 0;
      $membershipPeriods = CRM_MembershipExtras_BAO_MembershipPeriod::getOrderedMembershipPeriods($lineItem['entity_id']);
      while ($membershipPeriods->N && $membershipPeriods->fetch()) {
        if ($membershipPeriods->is_active && !empty($membershipPeriods->end_date)) {
          $startDate = new DateTime($membershipPeriods->start_date);
          $endDate = new DateTime($membershipPeriods->end_date);
          $endDate->add(new DateInterval('P1D'));
          $diff = $endDate->diff($startDate);

          $timeAmount = 0;
          switch($membershipTypeAllowedLimitTerm) {
            case 'year':
              $timeAmount = $diff->format('%y');
              break;
            case 'month':
              $timeAmount = $diff->format('%m'); //todo : change  and add other time types
              break;
            case 'day':
              $timeAmount = $diff->format('%a');
              break;
          }

          $totalPeriodsCount += $timeAmount;
        }
      }

      if ($totalPeriodsCount >= $membershipTypeAllowedLimitNum) {
        $newMembershipType = civicrm_api3('MembershipType', 'get', [
          'sequential' => 1,
          'name' => 'Test 1 Year', // todo : fetch from JSON
        ])['values'][0];

        $newPriceFieldValue= civicrm_api3('PriceFieldValue', 'get', [
          'sequential' => 1,
          'membership_type_id' => $newMembershipType['id'],
          'options' => ['limit' => 1, 'sort' => 'id asc'],
        ])['values'][0];

        $newMembershipLineItem = civicrm_api3('LineItem', 'create', [
          'label' => $newMembershipType['name'],
          'entity_id' => $currentRecurContribution['id'],
          'qty' => 1.0,
          'unit_price' => 15,
          'line_total' => 15,
          'tax_amount' => 0,
          'financial_type_id' => $newMembershipType['financial_type_id'],
          'price_field_id' => $newPriceFieldValue['price_field_id'],
          'price_field_value_id' => $newPriceFieldValue['id'],
          'entity_table' => 'civicrm_contribution_recur',
        ]);

        $newContributionRecurLineItem = civicrm_api3('ContributionRecurLineItem', 'create', [
          'contribution_recur_id' => $currentRecurContribution['id'],
          'line_item_id' => $newMembershipLineItem['id'],
          'auto_renew' => 1,
        ]);


        //todo : create activity : see js file

        return;
      }
    }
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
    ]);

    if (!$lineItems['count']) {
      return [];
    }

    $result = [];
    foreach ($lineItems['values'] as $line) {
      $lineData = $line['api.LineItem.getsingle'];
      $result[] =  $lineData;
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
        $totalAmount += $lineItemData['api.LineItem.getsingle']['line_total'];
        $totalAmount += $lineItemData['api.LineItem.getsingle']['tax_amount'];
      }
    }

    return MoneyUtilities::roundToCurrencyPrecision($totalAmount);
  }

}
