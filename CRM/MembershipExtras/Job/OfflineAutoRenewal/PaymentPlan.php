<?php
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_MembershipEndDateCalculator as MembershipEndDateCalculator;
use CRM_MembershipExtras_SettingsManager as SettingsManager;
use CRM_MembershipExtras_Hook_CustomDispatch_PostOfflineAutoRenewal as PostOfflineAutoRenewalDispatcher;

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
   * Start date for renewed memberships.
   *
   * @var date
   */
  protected $membershipsStartDate;

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
   * @var array
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
  protected $recurContributionStatusesNameMap;

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
   * @var CRM_MembershipExtras_Service_AutoUpgradableMembershipChecker
   */
  protected $autoUpgradableMembershipCheckService;

  /**
   * CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentPlan constructor.
   */
  public function __construct() {
    $this->autoUpgradableMembershipCheckService = new CRM_MembershipExtras_Service_AutoUpgradableMembershipChecker();

    $this->setUseMembershipLatestPrice();
    $this->setContributionPendingStatusValue();
    $this->setRecurContributionStatusesNameMap();
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
    $this->contributionPendingStatusValue = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  /**
   * Sets recur contribution Statuses Name to Value mapping
   */
  private function setRecurContributionStatusesNameMap() {
    $recurContributionStatuses = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ['name', 'value'],
      'option_group_id' => 'contribution_recur_status',
      'options' => ['limit' => 0],
    ])['values'];

    $recurContributionStatusesNameMap = [];
    foreach ($recurContributionStatuses as $status) {
      $recurContributionStatusesNameMap[$status['name']] = $status['value'];
    }

    $this->recurContributionStatusesNameMap = $recurContributionStatusesNameMap;
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
   *
   * @throws \CRM_Core_Exception
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
      }
      catch (Exception $e) {
        $transaction->rollback();
        $exceptions[] = "An error occurred renewing a payment plan with id ({$recurContribution['contribution_recur_id']}): " . $e->getMessage();
      }

      $transaction->commit();
    }

    if (count($exceptions)) {
      throw new CRM_Core_Exception(implode(";\n", $exceptions));
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
   * Dispatches postOfflineAutoRenewal hook for the recurring contribution.
   */
  private function dispatchMembershipRenewalHook() {
    $dispatcher = new PostOfflineAutoRenewalDispatcher(NULL, $this->newRecurringContributionID, $this->currentRecurContributionID);
    $dispatcher->dispatch();
  }

  /**
   * Obtains the list of recurring line items to be renewed for the plan.
   *
   * Returns an array with all the line items of the payment plan that are not
   * removed and are set to auto-renew.
   *
   * @param int $recurringContributionID
   *
   * @return array
   */
  abstract protected function getRecurringContributionLineItemsToBeRenewed($recurringContributionID);

  /**
   * Obtains list of all active line items of the given recurring contribution.
   *
   * Returns an array with all the line items that ar not removed from the
   * payment plan, irrespective if they are renewable or not.
   *
   * @param int $recurringContributionID
   *
   * @return array
   */
  abstract protected function getAllRecurringContributionActiveLineItems($recurringContributionID);

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
      'return' => ['id'],
      'contribution_recur_id' => $this->currentRecurContributionID,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0];

    $softContribution = civicrm_api3('ContributionSoft', 'get', [
      'sequential' => 1,
      'return' => ['contact_id', 'soft_credit_type_id'],
      'contribution_id' => $contribution['id'],
      'options' => ['limit' => 1],
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
      return $lineItem['unit_price'];
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
  protected function isMembershipLineItem($lineItem, $priceFieldValue = NULL) {
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
    }
    else {
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
  protected function calculateSingleInstallmentAmount($amount) {
    $resultAmount = $amount;

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
   *
   * @param int $sourceRecurringContribution
   *   ID of the recurring contribution to be used to copy line items.
   *
   * @throws \CiviCRM_API3_Exception
   */
  protected function renewPaymentPlanMemberships($sourceRecurringContribution) {
    $recurringLineItems = $this->getRecurringContributionLineItemsToBeRenewed($sourceRecurringContribution);

    foreach ($recurringLineItems as $lineItem) {
      $priceFieldValue = !empty($lineItem['price_field_value_id']) ? $this->getPriceFieldValue($lineItem['price_field_value_id']) : [];
      if (!$this->isMembershipLineItem($lineItem, $priceFieldValue)) {
        continue;
      }

      $existingMembershipID = $this->getExistingMembershipForLineItem($lineItem, $priceFieldValue);

      if ($existingMembershipID) {
        $currentMembershipTypeId = civicrm_api3('Membership', 'getvalue', [
          'return' => 'membership_type_id',
          'id' => $existingMembershipID,
        ]);
      }

      if ($existingMembershipID && ($currentMembershipTypeId == $priceFieldValue['membership_type_id'])) {
        $this->extendExistingMembership($existingMembershipID);
      }
      else {
        $existingMembershipID = $this->createMembership($lineItem, $priceFieldValue);
      }

      // Civicrm does not allow updating entity_table and
      // entity_id fields using API, so we use DAO class instead.
      $updateLineItemParams = [
        'id' => $lineItem['id'],
        'entity_table' => 'civicrm_membership',
        'entity_id' => $existingMembershipID,
      ];
      $lineItemDAO = new CRM_Price_DAO_LineItem();
      $lineItemDAO->copyValues($updateLineItemParams);
      $lineItemDAO->save();
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
    }

    if (!$lineItem['price_field_value_id']) {
      return 0;
    }

    if (!$priceFieldValue['membership_type_id']) {
      return 0;
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
    $membershipCreateResult = civicrm_api3('Membership', 'create', [
      'contact_id' => $this->currentRecurringContribution['contact_id'],
      'membership_type_id' => $priceFieldValue['membership_type_id'],
      'join_date' => $this->membershipsStartDate,
      'start_date' => $this->membershipsStartDate,
      'end_date' => $lineItem['end_date'],
      'contribution_recur_id' => $this->newRecurringContributionID,
      'skipLineItem' => TRUE,
    ]);

    return $membershipCreateResult['id'];
  }

  /**
   * Extend membership identified by given ID.
   *
   * @param int $membershipID
   *   ID of the membership to be extended.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function extendExistingMembership($membershipID) {
    $endDate = MembershipEndDateCalculator::calculate($membershipID);
    $isUpdateStartDateRenewal = self::isUpdateStartDateRenewal();
    $relatedMemberships = $this->loadRelatedMembershipIDs($membershipID);
    $membershipsToExtend = [$membershipID];
    $membershipsToExtend = array_merge($membershipsToExtend, $relatedMemberships);

    foreach ($membershipsToExtend as $relatedMembershipID) {
      $membership = new CRM_Member_DAO_Membership();
      $membership->id = $relatedMembershipID;
      $membership->end_date = $endDate;

      if ($isUpdateStartDateRenewal) {
        $membership->start_date = $this->membershipsStartDate;
      }

      $membership->save();
    }
  }

  /**
   * Obtains list of memberships related to given membership ID.
   *
   * @param int $membershipID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function loadRelatedMembershipIDs($membershipID) {
    $result = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'owner_membership_id' => $membershipID,
    ]);

    if ($result['count'] < 1) {
      return [];
    }

    $memberships = [];
    foreach ($result['values'] as $row) {
      $memberships[] = $row['id'];
    }

    return $memberships;
  }

  /**
   * Builds the list of line items to be created based on active line items set
   * for the recurring contribution.
   */
  protected function buildLineItemsParams() {
    $recurringContributionLineItems = $this->getNewPaymentPlanActiveLineItems();

    $lineItemsList = [];
    foreach ($recurringContributionLineItems as $lineItem) {
      $unitPrice = $this->calculateLineItemUnitPrice($lineItem);
      $lineTotal = MoneyUtilities::roundToCurrencyPrecision($unitPrice * $lineItem['qty']);
      $taxAmount = $this->calculateLineItemTaxAmount($lineTotal, $lineItem['financial_type_id']);

      switch ($lineItem['entity_table']) {
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
    foreach ($this->lineItems as $lineItem) {
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
    $params = [
      'currency' => $this->currentRecurringContribution['currency'],
      'source' => 'Offline Autorenewal: ' . date('Y-m-d H:i:s'),
      'contact_id' => $this->currentRecurringContribution['contact_id'],
      'net_amount' => $this->totalAmount,
      'total_amount' => $this->totalAmount,
      'receive_date' => $this->paymentPlanStartDate,
      'payment_instrument_id' => $this->currentRecurringContribution['payment_instrument_id'],
      'financial_type_id' => $this->currentRecurringContribution['financial_type_id'],
      'is_test' => $this->currentRecurringContribution['is_test'],
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
      $params['soft_credit'][1] = $this->lastContribution['soft_credit'];
    }

    $contribution = CRM_Contribute_BAO_Contribution::create($params);

    CRM_MembershipExtras_Service_CustomFieldsCopier::copy(
      $this->lastContribution['id'],
      $contribution->id,
      'Contribution'
    );

    foreach ($this->lineItems as &$lineItem) {
      $lineItem['contribution_id'] = $contribution->id;

      if ($lineItem['entity_table'] === 'civicrm_contribution') {
        $lineItem['entity_id'] = $contribution->id;
      }

      if ($lineItem['entity_table'] === 'civicrm_contribution_recur') {
        $lineItem['entity_id'] = $contribution->id;
        $lineItem['entity_table'] = 'civicrm_contribution';
      }

      if ($this->isDuplicateLineItem($lineItem)) {
        continue;
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

  /**
   * Checks if given line item already exists.
   *
   * Checks if there is already a similar line item related to the contribution,
   * by checking if there is already a line item with same entity_table,
   * entity_id, contribution_id, price_field_value_id, and price_field_id.
   *
   * @param array $lineItem
   *   Data for the line item to be used to check if it already exists.
   *
   * @return bool
   *   TRUE if it finds a line item with the same combination of fields, FALSE
   *   otherwise.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function isDuplicateLineItem($lineItem) {
    $priceFieldID = CRM_Utils_Array::value('price_field_id', $lineItem);
    $priceFieldValueID = CRM_Utils_Array::value('price_field_value_id', $lineItem);
    if (!$priceFieldID || !$priceFieldValueID) {
      return FALSE;
    }

    $result = civicrm_api3('LineItem', 'get', [
      'entity_table' => CRM_Utils_Array::value('entity_table', $lineItem),
      'entity_id' => CRM_Utils_Array::value('entity_id', $lineItem),
      'contribution_id' => CRM_Utils_Array::value('contribution_id', $lineItem),
      'price_field_id' => $priceFieldID,
      'price_field_value_id' => $priceFieldValueID,
    ]);

    if ($result['count'] > 0) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Calculates the start date renewed memberships should have.
   *
   * @return string
   *   Date the renewed memberships should have as start date.
   *
   * @throws \Exception
   */
  protected function calculateRenewedMembershipsStartDate() {
    $latestEndDates = [
      'auto_renewable' => NULL,
      'not_auto_renewable' => NULL,
    ];
    $currentPeriodLines = $this->getAllRecurringContributionActiveLineItems($this->currentRecurContributionID);

    foreach ($currentPeriodLines as $lineItem) {
      if ($lineItem['entity_table'] != 'civicrm_membership') {
        continue;
      }

      if (empty($lineItem['memberhsip_end_date'])) {
        continue;
      }

      $membershipEndDate = new DateTime($lineItem['memberhsip_end_date']);
      $isLineAutoRenewable = $lineItem['auto_renew'] == '1' ? 'auto_renewable' : 'not_auto_renewable';

      if (!isset($latestEndDates[$isLineAutoRenewable])) {
        $latestEndDates[$isLineAutoRenewable] = $membershipEndDate;
      }
      elseif ($latestEndDates[$isLineAutoRenewable] < $membershipEndDate) {
        $latestEndDates[$isLineAutoRenewable] = $membershipEndDate;
      }
    }

    // If there are no auto-renewable lines, we use the latest end date of
    // non-renewable lines. This happens when a membership is set not to
    // auto-renew for a period and a new membership is added to the next period.
    $latestDate = $latestEndDates['auto_renewable'] ?: $latestEndDates['not_auto_renewable'];

    if ($latestDate) {
      $latestDate->add(new DateInterval('P1D'));

      return $latestDate->format('Y-m-d');
    }

    return NULL;
  }

  /**
   * Check if update start date renewal is selected.
   *
   * @return bool
   */
  public static function isUpdateStartDateRenewal() {
    $updateStartDateRenewalSetting = SettingsManager::getUpdateStartDateRenewal();
    if ($updateStartDateRenewalSetting == 1) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Creates a subscription line item for the membership
   * we are going to current membership to in case
   * it is upgradable.
   *
   * @param int $newMembershipTypeId
   * @param int $recurContributionID
   * @param string $lineItemStartDate
   */
  protected function createUpgradableSubscriptionMembershipLine($newMembershipTypeId, $recurContributionID, $lineItemStartDate) {
    $newPriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $newMembershipTypeId,
      'options' => ['sort' => 'id ASC', 'limit' => 1],
    ])['values'][0];

    $newLineItemParams = [
      'entity_id' => $recurContributionID,
      'entity_table' => 'civicrm_contribution_recur',
      'qty' => 1,
      'price_field_id' => $newPriceFieldValue['price_field_id'],
      'price_field_value_id' => $newPriceFieldValue['id'],
      'label' => $newPriceFieldValue['label'],
      'financial_type_id' => $newPriceFieldValue['financial_type_id'],
    ];

    $newLineItemParams['unit_price'] = $this->calculateUpgradedMembershipPrice($newMembershipTypeId);
    $newLineItemParams['line_total'] = MoneyUtilities::roundToCurrencyPrecision($newLineItemParams['unit_price'] * $newLineItemParams['qty']);
    $newLineItemParams['tax_amount'] = $this->calculateLineItemTaxAmount($newLineItemParams['line_total'], $newPriceFieldValue['financial_type_id']);

    $newLineItem = civicrm_api3('LineItem', 'create', $newLineItemParams);

    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
      'contribution_recur_id' => $recurContributionID,
      'line_item_id' => $newLineItem['id'],
      'start_date' => $lineItemStartDate,
      'auto_renew' => 1,
    ]);
  }

  private function calculateUpgradedMembershipPrice($membershipTypeId) {
    $membershipMinimumFee = civicrm_api3('MembershipType', 'getvalue', [
      'return' => 'minimum_fee',
      'id' => $membershipTypeId,
    ]);

    return $this->calculateSingleInstallmentAmount($membershipMinimumFee);
  }

  /**
   * Duplicates given subscription line with the given start date.
   *
   * @param array $lineItemParams
   * @param string $startDate
   * @param int $newRecurContributionId
   */
  protected function duplicateSubscriptionLine($lineItemParams, $startDate, $newRecurContributionId) {
    $lineItemParams['unit_price'] = $this->calculateLineItemUnitPrice($lineItemParams) ?: 0;
    $lineItemParams['line_total'] = MoneyUtilities::roundToCurrencyPrecision($lineItemParams['unit_price'] * $lineItemParams['qty']) ?: 0;
    $lineItemParams['tax_amount'] = $this->calculateLineItemTaxAmount($lineItemParams['line_total'], $lineItemParams['financial_type_id']) ?: 0;
    unset($lineItemParams['id']);

    $newLineItem = civicrm_api3('LineItem', 'create', $lineItemParams);

    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create([
      'contribution_recur_id' => $newRecurContributionId,
      'line_item_id' => $newLineItem['id'],
      'start_date' => $startDate,
      'auto_renew' => 1,
    ]);
  }

}
