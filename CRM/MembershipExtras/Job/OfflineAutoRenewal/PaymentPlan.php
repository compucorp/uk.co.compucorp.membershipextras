<?php
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_MembershipEndDateCalculator as MembershipEndDateCalculator;

/**
 * Renews a payment plan.
 */
abstract class CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentPlan {
  /**
   * Array with the recurring contribution's data.
   *
   * @var array
   */
  protected $currentRecurringContribution;

  /**
   * ID for the current recurring contribution that is getting renewed.
   *
   * @var int
   */
  protected $currentRecurContributionID;

  /**
   * Array with the recurring contribution's data.
   *
   * @var array
   */
  protected $newRecurringContribution;

  /**
   * Holds the information of the new recurring contribution ID, once its created.
   *
   * @var int
   */
  protected $newRecurringContributionID;

  /**
   * Holds data for the last contribution of the current payment plan.
   *
   * @var array
   */
  protected $lastContribution;

  /**
   * Start date for the new period.
   *
   * @var string
   */
  protected $paymentPlanStartDate;

  /**
   * True if we should use the membership latest price
   * for renewal or false otherwise.
   *
   * @var bool
   */
  protected $useMembershipLatestPrice = FALSE;

  /**
   * The list of line items to be created.
   *
   * @var
   */
  protected $lineItems;

  /**
   * The calculated total amount that to be used to create the recurring
   * contribution as well as the installment contributions.
   *
   * @var int
   */
  protected $totalAmount;

  /**
   * The calculated tax amount that to be used to create the recurring
   * contribution as well as the installment contributions.
   *
   * @var int
   */
  protected $totalTaxAmount = 0;

  /**
   * The option value "value" for the "pending" contribution status.
   *
   * @var int
   */
  protected $contributionPendingStatusValue;

  /**
   * Maps contribution status names to their corresponding ID's.
   *
   * @var array
   */
  protected $contributionStatusesNameMap;

  /**
   * Number of days in advance a membership shuld be renewed.
   *
   * @var int
   */
  protected $daysToRenewInAdvance;

  /**
   * ID's for payment processors that are considered to be manual.
   *
   * @var array
   */
  protected $manualPaymentProcessorIDs;

  /**
   * CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentPlan constructor.
   */
  public function __construct() {
    $this->setUseMembershipLatestPrice();
    $this->setContributionPendingStatusValue();
    $this->setContributionStatusesNameMap();
    $this->setManualPaymentProcessorIDs();
    $this->setDaysToRenewInAdvance();
  }

  /**
   * Sets given recurring contribution ID as the current one and loads its data
   * into a clss attribute.
   *
   * @param $recurringContributionID
   */
  private function setCurrentRecurringContribution($recurringContributionID) {
    $this->currentRecurContributionID = $recurringContributionID;
    $this->currentRecurringContribution = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->currentRecurContributionID,
    ]);
  }

  /**
   * Sets the value for the flag to determine if latest membership price should
   * be used or not on renewal.
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
   * Loads value for Pending contribution status into a class attribute.
   */
  private function setContributionPendingStatusValue() {
    $this->contributionPendingStatusValue =  civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  /**
   * Gets contribution Statuses Name to value Mapping
   *
   * @return array $contributionStatusesNameMap
   */
  private function setContributionStatusesNameMap() {
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

    $this->contributionStatusesNameMap = $contributionStatusesNameMap;
  }

  /**
   * Loads setting and assigns it to a class attribute.
   */
  private function setDaysToRenewInAdvance() {
    $this->daysToRenewInAdvance = CRM_MembershipExtras_SettingsManager::getDaysToRenewInAdvance();
  }

  /**
   * Loads list of manual payment processors into an array as a class attribute.
   */
    private function setManualPaymentProcessorIDs() {
    $payLaterProcessorID = 0;
    $this->manualPaymentProcessorIDs = array_merge([$payLaterProcessorID], CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs());
  }

  /**
   * Renews the given payment plan.
   */
  public function run() {
    $exceptions = [];
    $paymentPlans = $this->getRecurringContributions();

    foreach ($paymentPlans as $recurContribution) {
      $transaction = new CRM_Core_Transaction();
      try {
        $this->setCurrentRecurringContribution($recurContribution['contribution_recur_id']);
        $this->setLastContribution();
        $this->renew();
        $this->dispatchMembershipRenewalHook();
      } catch (Exception $e) {
        $transaction->rollback();
        $exceptions[] = "An error occurred renewing a payment plan with id ({$recurContribution['contribution_recur_id']}): " . $e->getMessage();

        continue;
      }

      $transaction->commit();
    }

    if (count($exceptions)) {
      throw new Exception(implode(";\n", $exceptions));
    }
  }
  
  /**
   * Retunrs an array of recurring contributions that need to be renewed.
   *
   * @return array
   */
  abstract protected function getRecurringContributions();

  /**
   * Renews the current payment plan.
   */
  abstract public function renew();

  /**
   * Dispatches postOfflineAutoRenewal hook for each membership line item in the
   * new payment plan.
   */
  private function dispatchMembershipRenewalHook() {
    $recurringLineItems = $this->getNewPaymentPlanActiveLineItems();

    foreach ($recurringLineItems as $lineItem) {
      if ($lineItem['entity_table'] != 'civicrm_membership') {
        continue;
      }

      $nullObject = CRM_Utils_Hook::$_nullObject;
      CRM_Utils_Hook::singleton()->invoke(
        ['membershipId', 'recurContributionId', 'previousRecurContributionId'],
        $lineItem['entity_id'],
        $this->newRecurringContributionID,
        $this->currentRecurContributionID,
        $nullObject, $nullObject, $nullObject,
        'membershipextras_postOfflineAutoRenewal'
      );
    }
  }

  /**
   * Obtains the list of recurring line items to be renewed for the payment plan
   * being renewed.
   *
   * @param int $recurringContributionID
   *
   * @return array
   */
  abstract protected function getRecurringContributionLineItemsToBeRenewed($recurringContributionID);

  /**
   * Obtains list of recurring line items that are active for the new recurring
   * contribution.
   *
   * @return array
   */
  abstract protected function getNewPaymentPlanActiveLineItems();

  /**
   * Sets $lastContribution
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
   * Obtains ID for custom field name in given group.
   *
   * @param $fieldGroup
   * @param $fieldName
   *
   * @return int
   * @throws \Exception
   */
  protected function getCustomFieldID($fieldGroup, $fieldName) {
    $result = civicrm_api3('CustomField', 'get', [
      'sequential' => 1,
      'custom_group_id' => $fieldGroup,
      'name' => $fieldName,
    ]);

    if ($result['count'] > 0) {
      return $result['values'][0]['id'];
    }

    throw new Exception("Cannot find customfield $fieldName in $fieldGroup group.");
  }

  /**
   * Calculates the unit price for the line item, checking if it is a membership
   * that requires its price to be updated to latest.
   *
   * @param array $lineItem
   *
   * @return float
   */
  protected function calculateLineItemUnitPrice($lineItem) {
    $priceFieldValue = !empty($lineItem['price_field_value_id']) ? $this->getPriceFieldValue($lineItem['price_field_value_id']) : [];
    if (!$this->isMembershipLineItem($lineItem, $priceFieldValue)) {
      return  $lineItem['unit_price'];
    }

    $membershipMinimumFee = $this->getMembershipMinimumFeeFromLineItem($lineItem, $priceFieldValue);
    if ($this->isUseLatestPriceForMembership($lineItem)) {
      $unitPrice = $this->calculateSingleInstallmentAmount($membershipMinimumFee);
    }
    else {
      $unitPrice = $lineItem['unit_price'];
    }

    return $unitPrice;
  }

  /**
   * Obtains price field value with given ID.
   *
   * @param int $priceFieldValueID
   *
   * @return array
   */
  protected function getPriceFieldValue($priceFieldValueID) {
    return civicrm_api3('PriceFieldValue', 'getsingle', [
      'id' => $priceFieldValueID,
    ]);
  }

  /**
   * Checks if given line item is a memberhip.
   *
   * @param array $lineItem
   * @param array $priceFieldValue
   *
   * @return boolean
   */
  protected function isMembershipLineItem($lineItem, $priceFieldValue = null) {
    if ($lineItem['entity_table'] == 'civicrm_membership') {
      return TRUE;
    }

    if (isset($priceFieldValue) && !empty($priceFieldValue['membership_type_id'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Obtains the minimum fee for a membership from the given line item, takiing
   * into account the membership might not exist yet if it corresponds for a
   * line item added for next period.
   *
   * @param array $lineItem
   * @param array $priceFieldValue
   *
   * @return mixed
   */
  private function getMembershipMinimumFeeFromLineItem($lineItem, $priceFieldValue) {
    if ($lineItem['entity_table'] == 'civicrm_membership') {
      $membershipTypeID = civicrm_api3('Membership', 'getsingle', [
        'id' => $lineItem['entity_id'],
      ])['membership_type_id'];
    } else {
      $membershipTypeID = $priceFieldValue['membership_type_id'];
    }

    $membershipType = civicrm_api3('MembershipType', 'getsingle', [
      'id' => $membershipTypeID,
    ]);

    return $membershipType['minimum_fee'];
  }

  /**
   * Checks if the given line item, that should correspond to an existing
   * membership, requires its price to be updated oon renewal or not.
   *
   * @param array $lineItem
   *
   * @return bool
   */
  private function isUseLatestPriceForMembership($lineItem) {
    $isOptoutUsingLastPrice = FALSE;
    $optoutUsingLastPriceFieldID = civicrm_api3('CustomField', 'getvalue', [
      'return' => 'id',
      'custom_group_id' => 'offline_autorenew_option',
      'name' => 'optout_last_price_offline_autorenew',
    ]);
    if ($lineItem['entity_table'] == 'civicrm_membership') {
      $lineItemMembership = civicrm_api3('Membership', 'get', [
        'sequential' => 1,
        'return' => ["custom_$optoutUsingLastPriceFieldID"],
        'id' => $lineItem['entity_id'],
      ]);

      if (!empty($lineItemMembership['values'][0]["custom_$optoutUsingLastPriceFieldID"])) {
        $isOptoutUsingLastPrice = TRUE;
      }
    }

    return $this->useMembershipLatestPrice && !$isOptoutUsingLastPrice;
  }

  /**
   * Calulcates the value for a single installment of the given amount.
   *
   * @param $amount
   *
   * @return mixed
   */
  private function calculateSingleInstallmentAmount($amount) {
    $resultAmount =  $amount;

    if ($this->currentRecurringContribution['installments'] > 1) {
      $resultAmount = MoneyUtilities::roundToCurrencyPrecision(($amount / $this->currentRecurringContribution['installments']));
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
  protected function calculateLineItemTaxAmount($lineTotal, $financialTypeId) {
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
   * Updates amount on recurring contribution by calculating from associated line
   * items.
   *
   * @param $recurringContributionID
   */
  protected function updateRecurringContributionAmount($recurringContributionID) {
    $totalAmount = $this->calculateRecurringContributionTotalAmount($recurringContributionID);
    civicrm_api3('ContributionRecur', 'create', [
      'id' => $recurringContributionID,
      'amount' => $totalAmount,
    ]);
  }

  /**
   * Calculates total of recurring contribution according to recurring line
   * items.
   *
   * @param $recurringContributionID
   *
   * @return float
   */
  abstract protected function calculateRecurringContributionTotalAmount($recurringContributionID);

  /**
   * Renews/Extend the related payment plan memberships to be auto-renewed
   * for one term.
   */
  protected function renewPaymentPlanMemberships() {
    $recurringLineItems = $this->getRecurringContributionLineItemsToBeRenewed($this->currentRecurContributionID);
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

    $memberships = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contact_id' => $this->currentRecurringContribution['contact_id'],
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
    $membership = civicrm_api3('Membership', 'create', [
      'contact_id' => $this->currentRecurringContribution['contact_id'],
      'membership_type_id' => $priceFieldValue['membership_type_id'],
      'join_date' => date('YmdHis'),
      'start_date' => $lineItem['start_date'],
      'end_date' => $lineItem['end_date'],
      'contribution_recur_id' => $this->newRecurringContributionID,
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
   * Builds the list of line items to be created based on active line items set
   * for the recurring contribution.
   */
  protected function buildLineItemsParams() {
    $recurringContributionLineItems = $this->getNewPaymentPlanActiveLineItems();

    $lineItemsList = [];
    foreach($recurringContributionLineItems as $lineItem) {
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
        'price_field_id' => isset($lineItem['price_field_id']) ? $lineItem['price_field_id'] : NULL,
        'label' => $lineItem['label'],
        'qty' => $lineItem['qty'],
        'unit_price' => $unitPrice,
        'line_total' => $lineTotal,
        'price_field_value_id' => isset($lineItem['price_field_value_id']) ? $lineItem['price_field_value_id'] : NULL,
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
   * Sets $totalAmount and $totalTaxAmount
   * based on the line items to be created
   * data.
   */
  protected function setTotalAndTaxAmount() {
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
   * Records the payment plan first contribution.
   */
  protected function recordPaymentPlanFirstContribution() {
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
      'skipCleanMoney' => TRUE,
      'contribution_recur_id' => $this->newRecurringContributionID,
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

    foreach($this->lineItems as &$lineItem) {
      $lineItem['contribution_id'] = $contribution->id;

      if ($lineItem['entity_table'] === 'civicrm_contribution') {
        $lineItem['entity_id'] = $contribution->id;
      }

      $newLineItem = CRM_Price_BAO_LineItem::create($lineItem);

      CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution);
      if (!empty($contribution->tax_amount) && !empty($newLineItem->tax_amount)) {
        CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution, TRUE);
      }

      if ($this->isMembershipLineItem($lineItem)) {
        CRM_Member_BAO_MembershipPayment::create([
          'membership_id' => $lineItem['entity_id'],
          'contribution_id' => $contribution->id,
        ]);
      }
    }
  }

}
