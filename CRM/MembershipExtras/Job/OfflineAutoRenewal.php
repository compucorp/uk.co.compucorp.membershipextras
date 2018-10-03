<?php

use CRM_MembershipExtras_Service_MembershipInstallmentsHandler as MembershipInstallmentsHandler;
use CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator as InstallmentReceiveDateCalculator;
use CRM_MembershipExtras_Service_MembershipEndDateCalculator as MembershipEndDateCalculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

class CRM_MembershipExtras_Job_OfflineAutoRenewal {

  /**
   * Mapping between financial types IDs and Machine Names.
   *
   * @var array
   */
  private $financialTypesIDMap = [];

  /**
   * The ID of the previous recurring Contribution linked
   * with the membership that after the renewal.
   *
   * @var int
   */
  private $previousRecurContributionID;

  /**
   * The ID of the recurring Contribution linked
   * with the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentRecurContributionID;

  /**
   * The number of installments of the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentInstallmentsNumber;

  /**
   * The calculated total amount that to be used
   * to create the recurring contribution as well as
   * the installment contributions.
   *
   * @var int
   */
  private $totalAmount;

  /**
   * The calculated tax amount that
   * to be used to create the recurring contribution as well as
   * the installment contributions.
   *
   * @var int
   */
  private $totalTaxAmount = 0;

  /**
   * True if we should use the membership latest price
   * for renewal or false otherwise.
   *
   * @var bool
   */
  private $useMembershipLatestPrice = FALSE;


  /**
   * The last contribution details
   * from the previous payment plan.
   *
   * @var
   */
  private $lastContribution;

  /**
   * The list of line items to be created.
   *
   * @var
   */
  private $lineItems;

  /**
   * The option value "value" for the "pending"
   * contribution status.
   *
   * @var int
   */
  private $contributionPendingStatusValue;

  /**
   * The payment plan to-be-created start date.
   *
   * @var string
   */
  private $paymentPlanStartDate = 'null';

  public function __construct() {
    $this->setFinancialTypesIDMap();
    $this->setUseMembershipLatestPrice();
    $this->setContributionPendingStatusValue();
  }

  /**
   * Sets $financialTypesIDMap
   */
  private function setFinancialTypesIDMap() {
    $financialTypes = civicrm_api3('FinancialType', 'get', [
      'sequential' => 1,
      'return' => ['id', 'name'],
      'options' => ['limit' => 0],
    ])['values'];

    foreach ($financialTypes as $type) {
      $this->financialTypesIDMap[$type['id']] = $type['name'];
    }
  }

  /**
   * Sets $useMembershipLatestPrice
   */
  private function setUseMembershipLatestPrice() {
    $settingFieldName = 'membershipextras_paymentplan_use_membership_latest_price';
    $useMembershipLatestPrice = civicrm_api3('Setting', 'get', [
      'sequential' => 1,
      'return' => [$settingFieldName],
    ]);

    if (!empty($useMembershipLatestPrice['values'][0][$settingFieldName])) {
      $this->useMembershipLatestPrice = TRUE;
    }
  }

  /**
   * Sets $currentRecurContribution
   */
  private function setContributionPendingStatusValue() {
    $this->contributionPendingStatusValue =  civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  /**
   * Starts the scheduled job for renewing offline
   * auto-renewal memberships.
   *
   * @return True
   */
  public function run() {
    $recurContributions = $this->getOfflineAutoRenewalRecurContributions();

    foreach ($recurContributions as $recurContribution) {
      $this->previousRecurContributionID = $recurContribution['contribution_recur_id'];
      $this->currentRecurContributionID = $recurContribution['contribution_recur_id'];

      if (empty($recurContribution['installments'])) {
        $this->currentInstallmentsNumber = 1;
      }
      else {
        $this->currentInstallmentsNumber = $recurContribution['installments'];
      }

      $this->setLastContribution();

      if ($this->currentInstallmentsNumber > 1) {
        $this->renewWithInstallmentsPaymentPlan();
      }
      else {
        $this->renewNoInstallmentsPaymentPlan();
      }

      $this->dispatchMembershipRenewalHook();
    }

    return TRUE;
  }

  /**
   * Gets the list of offline auto-renewal Recurring Contributions
   * to be renewed, the following conditions should Apply:
   *
   * 1- is using an offline payment processor (payment manual class).
   * 2- has an end date.
   * 3- is set to auto-renew
   * 4- is not in status cancelled
   * 5- "Next Payment Plan Period" is empty
   * 6- has any linked membership that meets the following conditions:
   *    - end date is equal to or smaller than today
   *    - the membership's type matches with any
   *      "membershipextras_subscription_line" that is auto-renew and not
   *      is_removed.
   *
   * @return array
   *   Each row Contains :
   *   - The recurring contribution (contribution_recur_id)
   *   - The number of the recurring contribution installments (installments)
   */
  private function getOfflineAutoRenewalRecurContributions() {
    $getContributionStatusesNameMap = $this->getContributionStatusesNameMap();
    $cancelledStatusID = $getContributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $getContributionStatusesNameMap['Refunded'];

    $payLaterProcessorID = 0;
    $manualPaymentProcessors = array_merge([$payLaterProcessorID], CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs());
    $manualPaymentProcessorsIDs = implode(',', $manualPaymentProcessors);

    $daysToRenewInAdvance = CRM_MembershipExtras_SettingsManager::getDaysToRenewInAdvance();

    $query = '
      SELECT ccr.id as contribution_recur_id, ccr.installments 
        FROM civicrm_contribution_recur ccr 
   LEFT JOIN civicrm_membership cm ON ccr.id = cm.contribution_recur_id
   LEFT JOIN civicrm_value_payment_plan_periods ppp ON ppp.entity_id = ccr.id
       WHERE (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN (' . $manualPaymentProcessorsIDs . '))
         AND ccr.end_date IS NOT NULL
         AND ccr.auto_renew = 1 
         AND (ccr.contribution_status_id != ' . $cancelledStatusID . ' OR  ccr.contribution_status_id != ' . $refundedStatusID . ')
         AND ppp.next_period IS NULL
         AND (
          cm.end_date <= DATE_ADD(CURDATE(), INTERVAL ' . $daysToRenewInAdvance . ' DAY)
          OR (
            cm.membership_type_id IN (
              SELECT cpfv.membership_type_id
              FROM membershipextras_subscription_line msl, civicrm_line_item cli, civicrm_price_field_value cpfv
              WHERE msl.contribution_recur_id = ccr.id
              AND cli.id = msl.line_item_id
              AND cli.price_field_value_id = cpfv.id
              AND msl.auto_renew = 1
              AND msl.is_removed = 0
            )
          )
         )
    GROUP BY ccr.id
    ';
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
   * Gets contribution Statuses Name to value Mapping
   *
   * @return array $contributionStatusesNameMap
   */
  private function getContributionStatusesNameMap() {
    $contributionStatuses = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ['name', 'value'],
      'option_group_id' => 'contribution_status',
      'options' => ['limit' => 0],
    ])['values'];

    $contributionStatusesNameMap = [];
    foreach ($contributionStatuses as $status) {
      $contributionStatusesNameMap[$status['name']] = $status['value'];
    }

    return $contributionStatusesNameMap;
  }

  /**
   * Sets $lastContribution
   *
   */
  private function setLastContribution() {
    $contribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['currency', 'contribution_source', 'net_amount',
        'contact_id', 'fee_amount', 'total_amount', 'payment_instrument_id',
        'is_test', 'tax_amount', 'contribution_recur_id', 'financial_type_id'],
      'contribution_recur_id' => $this->currentRecurContributionID,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0];

    $softContribution = civicrm_api3('ContributionSoft', 'get', [
      'sequential' => 1,
      'return' => ['contact_id', 'soft_credit_type_id'],
      'contribution_id' => $contribution['id'],
    ]);
    if (!empty($softContribution['values'][0])) {
      $softContribution = $softContribution['values'][0];
      $contribution['soft_credit'] = [
        'soft_credit_type_id' => $softContribution['soft_credit_type_id'],
        'contact_id' => $softContribution['contact_id'],
      ];
    }

    $this->lastContribution = $contribution;
  }

  /**
   * Builds the list of line items to be created based on line items set to
   * auto-renew on the recurring contribution.
   *
   * @param int $recurringContributionID
   */
  private function buildLineItemsParams($recurringContributionID) {
    $lastContributionLineItems = $this->getRecurringContributionLineItems($recurringContributionID);

    $lineItemsList = [];
    foreach($lastContributionLineItems as $lineItem) {
      $unitPrice = $this->calculateLineItemUnitPrice($lineItem);
      $lineTotal = MoneyUtilities::roundToCurrencyPrecision($unitPrice * $lineItem['qty']);
      $taxAmount = $this->calculateLineItemTaxAmount($lineTotal, $lineItem['financial_type_id']);

      switch ($lineItem['entity_id']) {
        case 'civicrm_contribution':
        case 'civicrm_contribution_recur':
          $entityID = 'null';
          break;

        default:
          $entityID = $lineItem['entity_id'];
      }

      $lineItemParams = [
        'entity_table' => $lineItem['entity_table'],
        'entity_id' => $entityID,
        'contribution_id' => 'null',
        'price_field_id' => $lineItem['price_field_id'],
        'label' => $lineItem['label'],
        'qty' => $lineItem['qty'],
        'unit_price' => $unitPrice,
        'line_total' => $lineTotal,
        'price_field_value_id' => $lineItem['price_field_value_id'],
        'financial_type_id' => $lineItem['financial_type_id'],
        'non_deductible_amount' => $lineItem['non_deductible_amount'],
      ];

      if (!empty($taxAmount)) {
        $lineItemParams['tax_amount'] = $taxAmount;
      }

      $lineItemsList[] = $lineItemParams;
    }

    $this->lineItems = $lineItemsList;
  }

  /**
   * Obtains list of line items associated to given recurring contribution.
   *
   * @param int $recurringContributionID
   *
   * @return array
   */
  private function getRecurringContributionLineItems($recurringContributionID) {
    $lineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'auto_renew' => 1,
      'is_removed' => 0,
      'end_date' => ['IS NULL' => 1],
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
   * Calculates the unit price for
   * the line item.
   *
   * @param array $lineItem
   *
   * @return float
   */
  private function calculateLineItemUnitPrice($lineItem) {
    if ($lineItem['entity_table'] === 'civicrm_contribution') {
      return  $lineItem['unit_price'];
    }

    $optoutUsingLastPriceFieldID = civicrm_api3('CustomField', 'getvalue', [
      'return' => 'id',
      'custom_group_id' => 'offline_autorenew_option',
      'name' => 'optout_last_price_offline_autorenew',
    ]);

    $lineItemMembership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ["custom_$optoutUsingLastPriceFieldID", 'membership_type_id.minimum_fee'],
      'id' => $lineItem['entity_id'],
    ]);

    if (!empty($lineItemMembership['values'][0]['membership_type_id.minimum_fee'])) {
      $membershipMinimumFee = $lineItemMembership['values'][0]['membership_type_id.minimum_fee'];
    }

    $isOptoutUsingLastPrice = FALSE;
    if (!empty($lineItemMembership['values'][0]["custom_$optoutUsingLastPriceFieldID"])) {
      $isOptoutUsingLastPrice = TRUE;
    }


    if ($this->useMembershipLatestPrice && !$isOptoutUsingLastPrice) {
      $unitPrice = $this->calculateSingleInstallmentAmount($membershipMinimumFee);
    }
    else {
      $unitPrice = $lineItem['unit_price'];
    }

    return $unitPrice;
  }

  private function calculateSingleInstallmentAmount($amount) {
    $resultAmount =  $amount;
    if ($this->currentInstallmentsNumber > 1) {
      $resultAmount = MoneyUtilities::roundToCurrencyPrecision(($amount / $this->currentInstallmentsNumber));
    }

    return $resultAmount;
  }

  /**
   * Calculates the tax amount for
   * the line item given the line item
   * total amount and its financial type.
   *
   * @param float $lineTotal
   * @param int $financialTypeId
   *
   * @return float
   */
  private function calculateLineItemTaxAmount($lineTotal, $financialTypeId) {
    $taxAmount = 0;
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();

    if (!empty($taxRates[$financialTypeId])) {
      $taxRate = $taxRates[$financialTypeId];
      $taxAmount = CRM_Contribute_BAO_Contribution_Utils::calculateTaxAmount($lineTotal, $taxRate);
      $taxAmount = MoneyUtilities::roundToCurrencyPrecision($taxAmount['tax_amount']);
    }

    return $taxAmount;
  }

  /**
   * Sets $totalAmount and $totalTaxAmount
   * based on the line items to be created
   * data.
   */
  private function setTotalAndTaxAmount() {
    $totalAmount = 0;
    $taxAmount = 0;
    foreach ($this->lineItems  as $lineItem) {
      $totalAmount += $lineItem['line_total'];
      if (!empty($lineItem['tax_amount'])) {
        $totalAmount += $lineItem['tax_amount'];
        $taxAmount += $lineItem['tax_amount'];
      }
    }

    $this->totalAmount = $totalAmount;
    $this->totalTaxAmount = $taxAmount;
  }

  /**
   * Renews the payment plan and the related memberships if
   * it paid by installments.
   */
  private function renewWithInstallmentsPaymentPlan() {
    $this->createRecurringContribution();
    $this->renewPaymentPlanMemberships();
    $this->buildLineItemsParams($this->currentRecurContributionID);
    $this->setTotalAndTaxAmount();
    $this->recordPaymentPlanFirstContribution();

    $installmentsHandler = new MembershipInstallmentsHandler(
      $this->currentRecurContributionID
    );
    $installmentsHandler->createRemainingInstalmentContributionsUpfront();
  }

  /**
   * Dispatches postOfflineAutoRenewal hook for each membership line item in the
   * new payment plan.
   */
  private function dispatchMembershipRenewalHook() {
    $recurringLineItems = $this->getRecurringContributionLineItems($this->currentRecurContributionID);

    foreach ($recurringLineItems as $lineItem) {
      if ($lineItem['entity_table'] != 'civicrm_membership') {
        continue;
      }

      $nullObject = CRM_Utils_Hook::$_nullObject;
      CRM_Utils_Hook::singleton()->invoke(
        ['membershipId', 'recurContributionId', 'previousRecurContributionId'],
        $lineItem['entity_id'],
        $this->currentRecurContributionID,
        $this->previousRecurContributionID,
        $nullObject, $nullObject, $nullObject,
        'membershipextras_postOfflineAutoRenewal'
      );
    }
  }

  /**
   * Renews the current membership recurring contribution
   * by creating a new one based on its data.
   * The amount will be the newly calculated
   * total amount.
   *
   * The new recurring contribution will then
   * be set to be the current recurring contribution.
   */
  private function createRecurringContribution() {
    $currentRecurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $this->currentRecurContributionID,
    ])['values'][0];

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
    $this->currentRecurContributionID = $newRecurringContribution['id'];
  }

  /**
   * Updates amount on recurring contribution by calculating from associated line
   * items.
   *
   * @param $recurringContributionID
   */
  private function updateRecurringContributionAmount($recurringContributionID) {
    $totalAmount = $this->calculateRecurringContributionTotalAmount($recurringContributionID);
    civicrm_api3('ContributionRecur', 'create', [
      'id' => $recurringContributionID,
      'amount' => $totalAmount,
    ]);
  }

  /**
   * Calculates amount for current recurring contribution from related line
   * items.
   */
  private function calculateRecurringContributionTotalAmount($recorringContributionID) {
    $totalAmount = 0;

    $result = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recorringContributionID,
      'start_date' => ['IS NOT NULL' => 1],
      'end_date' => ['IS NULL' => 1],
      'is_removed' => 0,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
    ]);

    if ($result['count'] > 0) {
      foreach ($result['values'] as $lineItemData) {
        $totalAmount += $lineItemData['api.LineItem.getsingle']['line_total'];
        $totalAmount += $lineItemData['api.LineItem.getsingle']['tax_amount'];
      }
    }

    return MoneyUtilities::roundToCurrencyPrecision($totalAmount);
  }

  /**
   * Creates copies of all line items set to auto-renew in previous recurring
   * contribution and associates them with the new recurring contribution.
   *
   * @param array $currentContribution
   * @param array $nextContribution
   */
  private function copyRecurringLineItems($currentContribution, $nextContribution) {
    $recurringLineItems = $this->getRecurringContributionLineItems($currentContribution['id']);

    if (count($recurringLineItems)) {
      foreach ($recurringLineItems as $lineItem) {
        unset($lineItem['id']);

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

  private function getPaymentMethodNameFromItsId($paymentMethodId) {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'name',
      'option_group_id' => 'payment_instrument',
      'value' => $paymentMethodId,
    ]);
  }

  /**
   * Renews the payment plan and the related memberships if
   * it paid by once and not using installments.
   *
   * Paid by once (no installments) payment plan
   * get renewed by creating single pending contribution
   * that links to the already existing recurring
   * contribution.
   */
  private function renewNoInstallmentsPaymentPlan() {
    $this->duplicateRecurringLineItems($this->currentRecurContributionID);
    $this->updateRecurringContributionAmount($this->currentRecurContributionID);
    $this->buildLineItemsParams($this->currentRecurContributionID);
    $this->setTotalAndTaxAmount();
    $this->paymentPlanStartDate = $this->calculateNoInstallmentsPaymentPlanStartDate();

    $this->recordPaymentPlanFirstContribution();
    $this->renewPaymentPlanMemberships();
  }

  /**
   * Creates duplicates of line items associated to recurring contribution that
   * are set to auto-renew, don't have an end date and are not removed. It sets
   * end date for the old line items.
   *
   * @param $recurringContributionID
   */
  private function duplicateRecurringLineItems($recurringContributionID) {
    $lineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'auto_renew' => 1,
      'is_removed' => 0,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1]
      ],
    ]);

    foreach ($lineItems['values'] as $line) {
      $lineItemParams = $line['api.LineItem.getsingle'];
      unset($lineItemParams['id']);
      $newLineItem = civicrm_api3('LineItem', 'create', $lineItemParams);

      $newStartDate = $this->calculateNoInstallmentsPaymentPlanStartDate();
      CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
        'contribution_recur_id' => $recurringContributionID,
        'line_item_id' => $newLineItem['id'],
        'start_date' => $newStartDate,
        'auto_renew' => 1,
      ]);

      $endDate = new DateTime($newStartDate);
      $endDate->sub(new DateInterval('P1D'));
      CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
        'id' => $line['id'],
        'end_date' => $endDate->format('Y-m-d'),
      ]);
    }
  }

  /**
   * Calculates the new start date for the payment plan
   * if its paid with no installments.
   * @return string
   */
  private function calculateNoInstallmentsPaymentPlanStartDate() {
    $currentRecurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $this->currentRecurContributionID,
    ])['values'][0];
    $installmentReceiveDateCalculator = new InstallmentReceiveDateCalculator($currentRecurContribution);

    $paymentPlanContributionsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->currentRecurContributionID,
    ]);

    return $installmentReceiveDateCalculator->calculate($paymentPlanContributionsCount + 1);
  }

  /**
   * Records the payment plan first contribution.
   */
  private function recordPaymentPlanFirstContribution() {
    $params =  [
      'currency' => $this->lastContribution['currency'],
      'source' => $this->lastContribution['contribution_source'],
      'contact_id' => $this->lastContribution['contact_id'],
      'fee_amount' => $this->lastContribution['fee_amount'],
      'net_amount' =>  $this->totalAmount - $this->lastContribution['fee_amount'],
      'total_amount' => $this->totalAmount,
      'receive_date' => $this->paymentPlanStartDate,
      'payment_instrument_id' => $this->lastContribution['payment_instrument_id'],
      'financial_type_id' => $this->lastContribution['financial_type_id'],
      'is_test' => $this->lastContribution['is_test'],
      'contribution_status_id' => $this->contributionPendingStatusValue,
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'contribution_recur_id' => $this->currentRecurContributionID,
    ];

    if (!empty($this->totalTaxAmount)) {
      $params['tax_amount'] = $this->totalTaxAmount;
    }

    if (!empty($this->lastContribution['soft_credit'])) {
      $params['soft_credit'] = $this->lastContribution['soft_credit'];
    }

    $contribution = CRM_Contribute_BAO_Contribution::create($params);

    $contributionSoftParams = CRM_Utils_Array::value('soft_credit', $params);
    if (!empty($contributionSoftParams)) {
      $contributionSoftParams['contribution_id'] = $contribution->id;
      $contributionSoftParams['currency'] = $contribution->currency;
      $contributionSoftParams['amount'] = $contribution->total_amount;
      CRM_Contribute_BAO_ContributionSoft::add($contributionSoftParams);
    }

    CRM_MembershipExtras_Service_CustomFieldsCopier::copy(
      $this->lastContribution['id'],
      $contribution->id,
      'Contribution'
    );

    $membershipPayments = civicrm_api3('MembershipPayment', 'get', [
      'return' => 'membership_id',
      'contribution_id' => $this->lastContribution['id'],
    ])['values'];

    foreach ($membershipPayments as $membershipPayment) {
      CRM_Member_BAO_MembershipPayment::create([
        'membership_id' => $membershipPayment['membership_id'],
        'contribution_id' => $contribution->id,
      ]);
    }

    foreach($this->lineItems as &$lineItem) {
      $lineItem['contribution_id'] = $contribution->id;

      if ($lineItem['entity_table'] === 'civicrm_contribution') {
        $lineItem['entity_id'] = $contribution->id;
      }

      $newLineItem = CRM_Price_BAO_LineItem::create($lineItem);

      CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution);
      if (!empty($contribution->tax_amount)) {
        CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution, TRUE);
      }
    }
  }

  /**
   * Renews/Extend the related payment plan memberships to be auto-renewed
   * for one term.
   */
  private function renewPaymentPlanMemberships() {
    $recurringLineItems = $this->getRecurringContributionLineItems($this->currentRecurContributionID);
    $existingMembershipID = null;

    foreach ($recurringLineItems as $lineItem) {
      $priceFieldValue = !empty($lineItem['price_field_value_id']) ? $this->getPriceFieldValue($lineItem['price_field_value_id']) : [];
      if (!$this->isMembershipLineItem($lineItem, $priceFieldValue)) {
        continue;
      }

      $existingMembershipID = $this->getExistingMembershipForLineItem($lineItem, $priceFieldValue);

      if ($existingMembershipID) {
        $this->extendExistingMembership($existingMembershipID);
      } else {
        $existingMembershipID = $this->createMembership($lineItem, $priceFieldValue);
      }

      civicrm_api3('LineItem', 'create', [
        'id' => $lineItem['id'],
        'entity_table' => 'civicrm_membership',
        'entity_id' => $existingMembershipID,
      ]);
    }
  }

  /**
   * Checks if given line item is a memberhip.
   *
   * @param array $lineItem
   * @param array $priceFieldValue
   *
   * @return boolean
   */
  private function isMembershipLineItem($lineItem, $priceFieldValue) {
    if ($lineItem['entity_table'] == 'civicrm_membership') {
      return TRUE;
    }

    if (!empty($priceFieldValue['membership_type_id'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Returns existing membership ID for contact and given membership type.
   *
   * @param array $lineItem
   * @param array $priceFieldValue
   *
   * @return int
   */
  private function getExistingMembershipForLineItem($lineItem, $priceFieldValue) {
    if ($lineItem['entity_table'] == 'civicrm_membership') {
      return $lineItem['entity_id'];
    } else {
      if (!$lineItem['price_field_value_id']) {
        return 0;
      }

      if (!$priceFieldValue['membership_type_id']) {
        return 0;
      }
    }

    $currentRecurContribution = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->currentRecurContributionID,
    ]);

    $memberships = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contact_id' => $currentRecurContribution['contact_id'],
      'membership_type_id' => $priceFieldValue['membership_type_id'],
      'options' => ['sort' => 'id desc'],
    ]);

    if ($memberships['count'] > 0) {
      return $memberships['values'][0]['id'];
    }

    return 0;
  }

  /**
   * Creates a membership from the given line item's data.
   *
   * @param array $lineItem
   * @param array $priceFieldValue
   *
   * @return int
   */
  private function createMembership($lineItem, $priceFieldValue) {
    $currentRecurContribution = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->currentRecurContributionID,
    ]);

    $membership = civicrm_api3('Membership', 'create', [
      'contact_id' => $currentRecurContribution['contact_id'],
      'membership_type_id' => $priceFieldValue['membership_type_id'],
      'join_date' => date('YmdHis'),
      'start_date' => $lineItem['start_date'],
      'end_date' => $lineItem['end_date'],
      'contribution_recur_id' => $this->currentRecurContributionID,
    ])['values'][0];

    return $membership['id'];
  }

  /**
   * Extend membership identified by given ID.
   *
   * @param int $membershipID
   */
  private function extendExistingMembership($membershipID) {
    $membership = new CRM_Member_DAO_Membership();
    $membership->id = $membershipID;
    $membership->end_date = MembershipEndDateCalculator::calculate($membershipID);
    $membership->save();
  }

  /**
   * Obtains price field value with given ID.
   *
   * @param int $priceFieldValueID
   *
   * @return array
   */
  private function getPriceFieldValue($priceFieldValueID) {
    return civicrm_api3('PriceFieldValue', 'getsingle', [
      'id' => $priceFieldValueID,
    ]);
  }

}
