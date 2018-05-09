<?php

use CRM_MembershipExtras_Service_MembershipInstallmentsHandler as MembershipInstallmentsHandler;
use CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator as InstallmentReceiveDateCalculator;
use CRM_MembershipExtras_PaymentProcessor_OfflineRecurringContribution as OfflineRecurringContribution;

class CRM_MembershipExtras_Job_OfflineAutoRenewal {

  /**
   * Mapping between financial types IDs and Machine Names.
   *
   * @var array
   */
  private $financialTypesIDMap = [];

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
     $this->currentRecurContributionID = $recurContribution['contribution_recur_id'];

     if (empty($recurContribution['installments'])) {
       $this->currentInstallmentsNumber = 1;
     }
     else {
       $this->currentInstallmentsNumber = $recurContribution['installments'];
     }

     $this->setLastContribution();

     $this->buildLineItemsParams();
     $this->setTotalAndTaxAmount();

     if ($this->currentInstallmentsNumber > 1) {
       $this->renewWithInstallmentsPaymentPlan();
     }
     else {
       $this->renewNoInstallmentsPaymentPlan();
     }
   }

   return TRUE;
  }

  /**
   * Gets the list of offline auto-renewal Recurring Contributions
   * to be renewed, the following conditions should Apply:
   *
   * 1- The Recurring Contribution has at least one linked membership.
   * 2- the payment processor used is pay later (aka : no payment processor used)
   *   or an equivalent payment processor.
   * 3- The recurring contribution is not cancelled or refunded.
   * 4- Any of the linked memberships end date is less or equal than today.
   *
   * @return array
   *   Each row Contains :
   *   - The  recurring contribution (contribution_recur_id)
   *   - The number of the recurring contribution installments (installments)
   */
  private function getOfflineAutoRenewalRecurContributions() {
    $getContributionStatusesNameMap = $this->getContributionStatusesNameMap();
    $cancelledStatusID = $getContributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $getContributionStatusesNameMap['Refunded'];

    $payLaterProcessorID = 0;
    $manualPaymentProcessors = array_merge([$payLaterProcessorID], CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs());
    $manualPaymentProcessorsIDs = implode(',', $manualPaymentProcessors);

    $query = 'SELECT ccr.id as contribution_recur_id, ccr.installments  
                FROM civicrm_contribution_recur ccr 
           LEFT JOIN civicrm_membership cm ON ccr.id = cm.contribution_recur_id 
               WHERE ccr.auto_renew = 1 
                 AND (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN (' . $manualPaymentProcessorsIDs . '))
                 AND (ccr.contribution_status_id != ' . $cancelledStatusID . ' OR  ccr.contribution_status_id != ' . $refundedStatusID . ')
                 AND cm.end_date <= CURDATE() 
            GROUP BY ccr.id';
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
   * Builds the list of line items to be created
   * based on the last contribution.
   */
  private function buildLineItemsParams() {
    $lastContributionLineItems = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $this->lastContribution['id'],
    ])['values'];

    $lineItemsList = [];
    foreach($lastContributionLineItems as $lineItem) {
      $unitPrice = $this->calculateLineItemUnitPrice($lineItem);
      $lineTotal = floor(($unitPrice * $lineItem['qty']) * 100) / 100;
      $taxAmount = $this->calculateLineItemTaxAmount($lineTotal, $lineItem['financial_type_id']);

      $entityID = $lineItem['entity_id'];
      if ($lineItem['entity_table'] === 'civicrm_contribution') {
        $entityID = 'null';
      }

      $lineItemsList[] = [
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
        'tax_amount' => $taxAmount,
      ];
    }

    $this->lineItems = $lineItemsList;
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
      $resultAmount = floor(($amount / $this->currentInstallmentsNumber) * 100) / 100;
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
      $taxAmount = round($taxAmount['tax_amount'], 2);
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
      $totalAmount += $lineItem['line_total'] + $lineItem['tax_amount'];
      $taxAmount += $lineItem['tax_amount'];
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

    $this->recordPaymentPlanFirstContribution();

    $installmentsHandler = new MembershipInstallmentsHandler(
      $this->currentRecurContributionID
    );
    $installmentsHandler->createRemainingInstalmentContributionsUpfront();

    $this->renewPaymentPlanMemberships();
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

    $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', [
      'sequential' => 1,
      'contact_id' => $currentRecurContribution['contact_id'],
      'amount' => $this->totalAmount,
      'currency' => $currentRecurContribution['currency'],
      'frequency_unit' => $currentRecurContribution['frequency_unit'],
      'frequency_interval' => $currentRecurContribution['frequency_interval'],
      'installments' => $currentRecurContribution['installments'],
      'contribution_status_id' => 'Pending',
      'is_test' => $currentRecurContribution['is_test'],
      'auto_renew' => 1,
      'cycle_day' => $currentRecurContribution['cycle_day'],
      'payment_processor_id' => $paymentProcessorID,
      'financial_type_id' => $this->financialTypesIDMap[$currentRecurContribution['financial_type_id']],
      'payment_instrument_id' => 'EFT',
      'start_date' => $this->paymentPlanStartDate,
    ])['values'][0];

    // The new recurring contribution is now the current one.
    $this->currentRecurContributionID = $newRecurringContribution['id'];
  }

  /**
   * Renews the payment plan and the related memberships if
   * it paid by once and not using installments.
   *
   * Paid by once (no installments) payment plan
   * get renewed by creating single pending contribution
   * that links to the already existing recurring
   * contribution.
   *
   */
  private function renewNoInstallmentsPaymentPlan() {
    $this->paymentPlanStartDate = $this->calculateNoInstallmentsPaymentPlanStartDate();

    $this->recordPaymentPlanFirstContribution();
    $this->renewPaymentPlanMemberships();
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
      'tax_amount' => $this->totalTaxAmount,
      'skipLineItem' => 1,
      'contribution_recur_id' => $this->currentRecurContributionID,
    ];

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
    $membershipPayments = civicrm_api3('MembershipPayment', 'get', [
      'return' => 'membership_id',
      'contribution_id' => $this->lastContribution['id'],
    ])['values'];

    foreach ($membershipPayments as $membershipPayment) {
      $newEndDate = $this->calculateMembershipNewEndDate($membershipPayment['membership_id']);

      $membership = new CRM_Member_DAO_Membership();
      $membership->id = $membershipPayment['membership_id'];
      $membership->end_date = $newEndDate;
      $membership->save();
    }
  }

  /**
   * Calculates the membership new end date
   * for renewal.
   *
   * @param int $membershipId
   *
   * @return string
   */
  private function calculateMembershipNewEndDate($membershipId) {
    $membershipDetails = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ['end_date', 'membership_type_id.duration_unit', 'membership_type_id.duration_interval'],
      'id' => $membershipId,
    ])['values'][0];

    $currentEndDate = new DateTime($membershipDetails['end_date']);

    switch ($membershipDetails['membership_type_id.duration_unit']) {
      case 'month':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] . 'M';
        break;
      case 'day':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] .'D';
        break;
      case 'year':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] .'Y';
        break;
    }

    $currentEndDate->add(new DateInterval($interval));
    $newEndDate = $currentEndDate->format('Ymd');
    return $newEndDate;
  }

}
