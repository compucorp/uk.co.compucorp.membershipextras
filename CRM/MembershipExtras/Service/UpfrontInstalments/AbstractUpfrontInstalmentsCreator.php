<?php

use CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate as CalculateContributionReceiveDateDispatcher;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as InstalmentTaxAmountCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodTypeCalculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

abstract class CRM_MembershipExtras_Service_UpfrontInstalments_AbstractUpfrontInstalmentsCreator {

  /**
   * The data of the current recurring
   * contribution for the membership.
   *
   * @var array
   */
  public $currentRecurContribution;

  /**
   * The data of the last contribution
   * for the current recurring contribution.
   * If no contribution exist under the current
   * recurring contribution, then the this will
   * contain the data of the last contribution
   * for the previous recurring contribution.
   *
   * @var array
   */
  private $lastContribution;

  /**
   * The option value "value" for the "pending"
   * contribution status.
   *
   * @var int
   */
  private $contributionPendingStatusValue;

  /**
   * @var DateTime
   */
  private $previousInstalmentDate;

  /**
   * @var int
   */
  private $instalmentsCount = 0;

  /**
   * Cached line-item rounding adjustments by contribution number.
   *
   * @var array
   */
  private $lineItemRoundingAdjustmentsCache = [];

  /**
   * Cached membership context by membership ID.
   *
   * @var array
   */
  private $membershipContextCache = [];

  public function __construct($currentRecurContributionId) {
    $this->setCurrentRecurContribution($currentRecurContributionId);
    $this->setLastContribution();
    $this->setPreviousInstalmentDate($this->lastContribution['receive_date']);
    $this->setContributionPendingStatusValue();
  }

  /**
   * Sets $currentRecurContribution
   *
   * @param int $currentRecurContributionId
   */
  private function setCurrentRecurContribution($currentRecurContributionId) {
    $this->currentRecurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $currentRecurContributionId,
    ])['values'][0];
  }

  /**
   * Sets $lastContribution
   */
  private function setLastContribution() {
    $contribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => [
        'currency', 'contribution_source', 'net_amount', 'contact_id',
        'fee_amount', 'total_amount', 'payment_instrument_id', 'is_test',
        'tax_amount', 'contribution_recur_id', 'financial_type_id', 'receive_date',
      ],
      'is_test' => $this->currentRecurContribution['is_test'] ?? 0,
      'contribution_recur_id' => $this->currentRecurContribution['id'],
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
   * Sets $currentRecurContribution
   */
  private function setContributionPendingStatusValue() {
    $this->contributionPendingStatusValue = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  /**
   * Creates the Remaining instalments contributions for
   * the membership new recurring contribution.
   */
  public function createRemainingInstalments() {
    if ($this->instalmentsCount == 0) {
      $this->instalmentsCount = (int) $this->currentRecurContribution['installments'];
    }
    for ($contributionNumber = 2; $contributionNumber <= $this->instalmentsCount; $contributionNumber++) {
      $this->createContribution($contributionNumber);
    }
  }

  /**
   * Creates the instalment contribution.
   *
   * @param int $contributionNumber
   *   The instalment number (index), if for example
   *   the recurring contribution has 3 instalments, then
   *   the first contribution number will be 1, the 2nd will be 2
   *   .. etc.
   */
  private function createContribution($contributionNumber = 1) {
    $contribution = $this->recordMembershipContribution($contributionNumber);
    $this->setPreviousInstalmentDate($contribution->receive_date);
    $this->createLineItems($contribution, $contributionNumber);
  }

  /**
   * Sets Previous Instalment Date.
   *
   * @throws Exception
   */
  private function setPreviousInstalmentDate(string $dateString) {
    $this->previousInstalmentDate = new DateTime($dateString);
  }

  /**
   * Records the membership contribution and its
   * related entities using the specified parameters
   *
   * @param int $contributionNumber
   *
   * @return CRM_Contribute_BAO_Contribution
   */
  private function recordMembershipContribution($contributionNumber) {
    $params = $this->buildContributionParams($contributionNumber);
    $this->dispatchReceiveDateCalculationHook($contributionNumber, $params);

    $contribution = CRM_Contribute_BAO_Contribution::create($params);

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

    CRM_MembershipExtras_Service_CustomFieldsCopier::copy(
      $this->lastContribution['id'],
      $contribution->id,
      'Contribution'
    );

    return $contribution;
  }

  /**
   * Builds the instalment contribution to be created parameters.
   *
   * @param int $contributionNumber
   *
   * @return array
   */
  private function buildContributionParams($contributionNumber) {
    $roundingAdjustment = $this->getRoundingAdjustmentForContribution($contributionNumber);
    $reconciledTotalAmount = MoneyUtilities::roundToPrecision($this->lastContribution['total_amount'] + $roundingAdjustment['total_amount'], 2);
    $reconciledFeeAmount = (float) $this->lastContribution['fee_amount'];

    $params = [
      'currency' => $this->lastContribution['currency'],
      'source' => $this->lastContribution['contribution_source'],
      'contact_id' => $this->lastContribution['contact_id'],
      'fee_amount' => $reconciledFeeAmount,
      'net_amount' => MoneyUtilities::roundToPrecision($reconciledTotalAmount - $reconciledFeeAmount, 2),
      'total_amount' => $reconciledTotalAmount,
      'receive_date' => $this->calculateReceiveDate($contributionNumber),
      'payment_instrument_id' => $this->lastContribution['payment_instrument_id'],
      'financial_type_id' => $this->lastContribution['financial_type_id'],
      'is_test' => $this->lastContribution['is_test'],
      'contribution_status_id' => $this->contributionPendingStatusValue,
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'contribution_recur_id' => $this->currentRecurContribution['id'],
    ];

    if (!empty($this->lastContribution['tax_amount']) || $roundingAdjustment['tax_amount'] !== 0.0) {
      $params['tax_amount'] = MoneyUtilities::roundToPrecision((float) ($this->lastContribution['tax_amount'] ?? 0) + $roundingAdjustment['tax_amount'], 2);
    }

    if (!empty($this->lastContribution['soft_credit'])) {
      $params['soft_credit'][1] = $this->lastContribution['soft_credit'];
    }

    return $params;
  }

  abstract protected function calculateReceiveDate($contributionNumber);

  public function setInstalmentsCount(int $instalmentsCount) {
    $this->instalmentsCount = $instalmentsCount;
  }

  /**
   * Dispatches hook so other extensions may change each contribution's receive
   * date.
   *
   * @param int $contributionNumber
   * @param array $params
   */
  private function dispatchReceiveDateCalculationHook($contributionNumber, &$params) {
    $receiveDate = $params['receive_date'];
    $paymentPlanSchedule = CRM_MembershipExtras_Helper_InstalmentSchedule::getPaymentPlanSchedule(
      $this->currentRecurContribution['frequency_unit'],
      $this->currentRecurContribution['frequency_interval']
    );

    $contributionReceiveDateParams = [
      'membership_id' => $this->getMembership()['membership_id.id'],
      'contribution_recur_id' => $this->currentRecurContribution['id'],
      'previous_instalment_date' => $this->previousInstalmentDate->format('Y-m-d'),
      'payment_schedule' => $paymentPlanSchedule,
      'payment_instrument_id' => $params['payment_instrument_id'],
      'membership_start_date' => $this->getMembership()['membership_id.start_date'],
      'frequency_interval' => $this->currentRecurContribution['frequency_interval'],
      'frequency_unit' => $this->currentRecurContribution['frequency_unit'],
    ];

    $dispatcher = new CalculateContributionReceiveDateDispatcher($contributionNumber, $receiveDate, $contributionReceiveDateParams);
    $dispatcher->dispatch();

    $params['receive_date'] = $receiveDate;

  }

  private function getMembership() {
    return civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'return' => [
        'membership_id.start_date',
        'membership_id.id',
      ],
      'contribution_id' => $this->lastContribution['id'],
    ])['values'][0];
  }

  /**
   * Creates the contribution line items.
   *
   * @param CRM_Contribute_BAO_Contribution $contribution
   *   The contribution that we need to build the line items for.
   * @param int $contributionNumber
   *   The contribution number is used to identify the last contribution to adjust amount.
   */
  private function createLineItems(CRM_Contribute_BAO_Contribution $contribution, int $contributionNumber) {
    $roundingAdjustments = $this->getLineItemRoundingAdjustments($contributionNumber);
    $lineItems = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $this->lastContribution['id'],
    ])['values'];

    foreach ($lineItems as $lineItem) {
      $entityID = $lineItem['entity_id'];
      if ($lineItem['entity_table'] === 'civicrm_contribution') {
        $entityID = $contribution->id;
      }

      $lineRoundingAdjustment = $roundingAdjustments[$lineItem['id']] ?? ['line_total' => 0.0, 'tax_amount' => 0.0];

      $quantity = (float) $lineItem['qty'];
      $unitPriceAdjustment = $quantity > 0 ? MoneyUtilities::roundToPrecision($lineRoundingAdjustment['line_total'] / $quantity, 2) : 0.0;

      $lineItemParms = [
        'entity_table' => $lineItem['entity_table'],
        'entity_id' => $entityID,
        'contribution_id' => $contribution->id,
        'price_field_id' => CRM_Utils_Array::value('price_field_id', $lineItem),
        'label' => $lineItem['label'],
        'qty' => $lineItem['qty'],
        'unit_price' => MoneyUtilities::roundToPrecision($lineItem['unit_price'] + $unitPriceAdjustment, 2),
        'line_total' => MoneyUtilities::roundToPrecision($lineItem['line_total'] + $lineRoundingAdjustment['line_total'], 2),
        'price_field_value_id' => CRM_Utils_Array::value('price_field_value_id', $lineItem),
        'financial_type_id' => $lineItem['financial_type_id'],
        'non_deductible_amount' => $lineItem['non_deductible_amount'],
      ];
      $expectedTaxAmount = NULL;
      if (!empty($lineItem['tax_amount']) || $lineRoundingAdjustment['tax_amount'] !== 0.0) {
        $expectedTaxAmount = MoneyUtilities::roundToPrecision((float) ($lineItem['tax_amount'] ?? 0) + $lineRoundingAdjustment['tax_amount'], 2);
        $lineItemParms['tax_amount'] = $expectedTaxAmount;
      }
      $newLineItem = CRM_Price_BAO_LineItem::create($lineItemParms);

      CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution);

      if (!empty($newLineItem->tax_amount)) {
        CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution, TRUE);
      }

      $this->syncLineItemTaxAmount($newLineItem, $expectedTaxAmount);
    }
  }

  /**
   * Ensures line item's tax amount value remains aligned with the expected
   * reconciled amount after creation.
   */
  private function syncLineItemTaxAmount(CRM_Price_BAO_LineItem $lineItem, ?float $expectedTaxAmount): void {
    if ($expectedTaxAmount === NULL) {
      return;
    }

    $createdTaxAmount = MoneyUtilities::roundToPrecision((float) ($lineItem->tax_amount ?? 0), 2);
    if ($createdTaxAmount === $expectedTaxAmount) {
      return;
    }

    civicrm_api3('LineItem', 'create', [
      'id' => $lineItem->id,
      'tax_amount' => $expectedTaxAmount,
    ]);
  }

  /**
   * Gets rounding adjustments for line items on the final contribution.
   */
  private function getLineItemRoundingAdjustments(int $contributionNumber): array {
    if (isset($this->lineItemRoundingAdjustmentsCache[$contributionNumber])) {
      return $this->lineItemRoundingAdjustmentsCache[$contributionNumber];
    }

    if ($contributionNumber !== $this->instalmentsCount) {
      $this->lineItemRoundingAdjustmentsCache[$contributionNumber] = [];
      return [];
    }

    $lineItems = $this->getContributionLineItems();
    $this->preloadMembershipContexts($lineItems);
    $priceFieldValues = $this->getPriceFieldValuesById($lineItems);
    $taxCalculator = new InstalmentTaxAmountCalculator();

    $lineItemAdjustments = [];
    foreach ($lineItems as $lineItem) {
      $lineItemAdjustment = $this->buildLineItemRoundingAdjustment($lineItem, $priceFieldValues, $taxCalculator);
      if ($lineItemAdjustment === NULL) {
        continue;
      }

      $lineItemAdjustments[$lineItem['id']] = $lineItemAdjustment;
    }

    $this->lineItemRoundingAdjustmentsCache[$contributionNumber] = $lineItemAdjustments;

    return $lineItemAdjustments;
  }

  /**
   * Preloads membership contexts for all membership line items.
   */
  private function preloadMembershipContexts(array $lineItems): void {
    $membershipIDs = [];
    foreach ($lineItems as $lineItem) {
      if (($lineItem['entity_table'] ?? NULL) !== 'civicrm_membership' || empty($lineItem['entity_id'])) {
        continue;
      }

      $membershipID = (int) $lineItem['entity_id'];
      if (!isset($this->membershipContextCache[$membershipID])) {
        $membershipIDs[] = $membershipID;
      }
    }

    if (empty($membershipIDs)) {
      return;
    }

    $memberships = \Civi\Api4\Membership::get(FALSE)
      ->addSelect(
        'id',
        'start_date',
        'end_date',
        'join_date',
        'membership_type_id.id',
        'membership_type_id.period_type',
        'membership_type_id.duration_unit',
        'membership_type_id.duration_interval',
        'membership_type_id.fixed_period_start_day',
        'membership_type_id.fixed_period_rollover_day'
      )
      ->addWhere('id', 'IN', array_values(array_unique($membershipIDs)))
      ->setLimit(0)
      ->execute();

    foreach ($memberships as $membership) {
      $this->membershipContextCache[(int) $membership['id']] = $membership;
    }
  }

  /**
   * Gets cached membership context for the given membership ID.
   */
  private function getMembershipContext(int $membershipID): ?array {
    if (isset($this->membershipContextCache[$membershipID])) {
      return $this->membershipContextCache[$membershipID];
    }

    $this->preloadMembershipContexts([['entity_table' => 'civicrm_membership', 'entity_id' => $membershipID]]);

    return $this->membershipContextCache[$membershipID] ?? NULL;
  }

  /**
   * Gets source contribution line items.
   */
  private function getContributionLineItems(): array {
    return civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $this->lastContribution['id'],
      'options' => ['limit' => 0],
    ])['values'];
  }

  /**
   * Gets price field values indexed by ID for the given line items.
   */
  private function getPriceFieldValuesById(array $lineItems): array {
    $priceFieldValueIDs = [];
    foreach ($lineItems as $lineItem) {
      if (!empty($lineItem['price_field_value_id'])) {
        $priceFieldValueIDs[] = $lineItem['price_field_value_id'];
      }
    }

    if (empty($priceFieldValueIDs)) {
      return [];
    }

    $priceFieldValuesById = [];
    $priceFieldValues = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'id' => ['IN' => array_values(array_unique($priceFieldValueIDs))],
      'options' => ['limit' => 0],
    ])['values'];

    foreach ($priceFieldValues as $priceFieldValue) {
      $priceFieldValuesById[$priceFieldValue['id']] = $priceFieldValue;
    }

    return $priceFieldValuesById;
  }

  /**
   * Builds rounding adjustment for a single line item.
   */
  private function buildLineItemRoundingAdjustment(array $lineItem, array $priceFieldValues, InstalmentTaxAmountCalculator $taxCalculator): ?array {
    $priceFieldValueID = CRM_Utils_Array::value('price_field_value_id', $lineItem);
    if (empty($priceFieldValueID) || empty($priceFieldValues[$priceFieldValueID])) {
      return NULL;
    }

    $priceFieldValue = $priceFieldValues[$priceFieldValueID];
    $originalAmounts = $this->getOriginalLineAndTaxAmounts($lineItem, $priceFieldValue, $taxCalculator);

    $lastLineTotal = MoneyUtilities::roundToPrecision($originalAmounts['line_total'] - ((float) $lineItem['line_total'] * ($this->instalmentsCount - 1)), 2);
    $lastTaxAmount = MoneyUtilities::roundToPrecision($originalAmounts['tax_amount'] - ((float) ($lineItem['tax_amount'] ?? 0) * ($this->instalmentsCount - 1)), 2);

    if ($lastLineTotal < 0 || $lastTaxAmount < 0) {
      $quantity = (float) $lineItem['qty'];
      $fullLineTotal = (float) $priceFieldValue['amount'] * $quantity;
      $fullTaxAmount = (float) $taxCalculator->calculateByPriceFieldValue($priceFieldValue) * $quantity;
      $lastLineTotal = MoneyUtilities::roundToPrecision($fullLineTotal - ((float) $lineItem['line_total'] * ($this->instalmentsCount - 1)), 2);
      $lastTaxAmount = MoneyUtilities::roundToPrecision($fullTaxAmount - ((float) ($lineItem['tax_amount'] ?? 0) * ($this->instalmentsCount - 1)), 2);

      // If recalculation still yields an invalid negative final line/tax amount,
      // skip any adjustment for this line to avoid creating negative contributions.
      if ($lastLineTotal < 0 || $lastTaxAmount < 0) {
        return NULL;
      }
    }

    return [
      'line_total' => MoneyUtilities::roundToPrecision($lastLineTotal - (float) $lineItem['line_total'], 2),
      'tax_amount' => MoneyUtilities::roundToPrecision($lastTaxAmount - (float) ($lineItem['tax_amount'] ?? 0), 2),
    ];
  }

  /**
   * Calculates the original line total and tax amount for a line item.
   */
  private function getOriginalLineAndTaxAmounts(array $lineItem, array $priceFieldValue, InstalmentTaxAmountCalculator $taxCalculator): array {
    $quantity = (float) $lineItem['qty'];
    $originalLineTotal = (float) $priceFieldValue['amount'] * $quantity;
    $originalTaxAmount = (float) $taxCalculator->calculateByPriceFieldValue($priceFieldValue) * $quantity;

    if ($this->isFixedMembershipLineItem($lineItem)) {
      $projectedLineTotal = MoneyUtilities::roundToPrecision((float) $lineItem['line_total'] * $this->instalmentsCount, 2);
      $projectedTaxAmount = MoneyUtilities::roundToPrecision((float) ($lineItem['tax_amount'] ?? 0) * $this->instalmentsCount, 2);
      $isProjectedProrated = $this->isSignificantlyLowerThanBaseline($projectedLineTotal, $originalLineTotal);

      $lineItemHasTaxAmount = array_key_exists('tax_amount', $lineItem) && $lineItem['tax_amount'] !== NULL && $lineItem['tax_amount'] !== '';
      if ($lineItemHasTaxAmount && $originalTaxAmount > 0) {
        $isProjectedProrated = $isProjectedProrated
          || $this->isSignificantlyLowerThanBaseline($projectedTaxAmount, $originalTaxAmount);
      }

      if ($isProjectedProrated) {
        $proratedAmounts = $this->getFixedMembershipProratedLineAmounts($lineItem, $priceFieldValue);
        if ($proratedAmounts) {
          $originalLineTotal = $proratedAmounts['line_total'];
          $originalTaxAmount = $proratedAmounts['tax_amount'];
        }
      }
    }

    return [
      'line_total' => $originalLineTotal,
      'tax_amount' => $originalTaxAmount,
    ];
  }

  /**
   * Detects if projected amount is significantly lower than baseline amount.
   *
   * This avoids treating normal per-instalment rounding differences as prorata.
   */
  private function isSignificantlyLowerThanBaseline(float $projectedAmount, float $baselineAmount): bool {
    $baselineAmount = MoneyUtilities::roundToPrecision($baselineAmount, 2);
    $projectedAmount = MoneyUtilities::roundToPrecision($projectedAmount, 2);
    if ($baselineAmount <= 0) {
      return FALSE;
    }

    $difference = $baselineAmount - $projectedAmount;
    $tolerance = max(1.00, $baselineAmount * 0.01);

    return $difference > $tolerance;
  }

  /**
   * Detects whether given line item belongs to a fixed membership type.
   */
  private function isFixedMembershipLineItem(array $lineItem): bool {
    if (($lineItem['entity_table'] ?? NULL) !== 'civicrm_membership' || empty($lineItem['entity_id'])) {
      return FALSE;
    }

    $membership = $this->getMembershipContext((int) $lineItem['entity_id']);

    if (empty($membership)) {
      return FALSE;
    }

    return ($membership['membership_type_id.period_type'] ?? NULL) === 'fixed';
  }

  /**
   * Calculates prorated line and tax amounts for fixed membership line items.
   */
  private function getFixedMembershipProratedLineAmounts(array $lineItem, array $priceFieldValue): ?array {
    if (($lineItem['entity_table'] ?? NULL) !== 'civicrm_membership' || empty($lineItem['entity_id'])) {
      return NULL;
    }

    try {
      $membership = $this->getMembershipContext((int) $lineItem['entity_id']);

      if (empty($membership)) {
        return NULL;
      }

      $membershipType = new CRM_Member_BAO_MembershipType();
      $membershipType->id = $membership['membership_type_id.id'];
      $membershipType->period_type = $membership['membership_type_id.period_type'];
      $membershipType->duration_unit = $membership['membership_type_id.duration_unit'];
      $membershipType->duration_interval = $membership['membership_type_id.duration_interval'];
      $membershipType->fixed_period_start_day = $membership['membership_type_id.fixed_period_start_day'];
      $membershipType->fixed_period_rollover_day = $membership['membership_type_id.fixed_period_rollover_day'];

      $membershipType->minimum_fee = (float) $priceFieldValue['amount'];
      $membershipType->financial_type_id = $priceFieldValue['financial_type_id'];

      $calculator = new FixedPeriodTypeCalculator([$membershipType]);
      $calculator->setStartDate(new DateTime($membership['start_date']));
      $calculator->setEndDate(new DateTime($membership['end_date']));
      if (!empty($membership['join_date'])) {
        $calculator->setJoinDate(new DateTime($membership['join_date']));
      }
      $calculator->calculate();

      $quantity = (float) $lineItem['qty'];
      $proratedLineTotal = MoneyUtilities::roundToPrecision((float) $calculator->getAmount() * $quantity, 2);
      $taxRate = (float) (new InstalmentTaxAmountCalculator())->getTaxRateByFinancialTypeId((int) $membershipType->financial_type_id);
      $proratedTaxAmount = MoneyUtilities::roundToPrecision(($taxRate / 100) * $proratedLineTotal, 2);

      return [
        'line_total' => $proratedLineTotal,
        'tax_amount' => $proratedTaxAmount,
      ];
    }
    catch (Throwable $exception) {
      \Civi::log()->error('Failed to calculate prorated line amounts: ' . $exception->getMessage());
      return NULL;
    }
  }

  /**
   * Gets contribution amount rounding adjustment for the final contribution.
   */
  private function getRoundingAdjustmentForContribution(int $contributionNumber): array {
    $lineItemAdjustments = $this->getLineItemRoundingAdjustments($contributionNumber);
    if (empty($lineItemAdjustments)) {
      return [
        'net_amount' => 0.0,
        'tax_amount' => 0.0,
        'total_amount' => 0.0,
      ];
    }

    $netAdjustment = 0.0;
    $taxAdjustment = 0.0;
    foreach ($lineItemAdjustments as $lineItemAdjustment) {
      $netAdjustment += $lineItemAdjustment['line_total'];
      $taxAdjustment += $lineItemAdjustment['tax_amount'];
    }

    return [
      'net_amount' => MoneyUtilities::roundToPrecision($netAdjustment, 2),
      'tax_amount' => MoneyUtilities::roundToPrecision($taxAdjustment, 2),
      'total_amount' => MoneyUtilities::roundToPrecision($netAdjustment + $taxAdjustment, 2),
    ];
  }

}
