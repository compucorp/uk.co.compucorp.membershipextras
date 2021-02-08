<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate as CalculateContributionReceiveDateDispatcher;
use CRM_MembershipExtras_Utils_InstalmentSchedule as InstalmentScheduleUtils;

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor {

  /**
   * The contribution or line item to-be-created parameters passed from the hook.
   *
   * @var array
   */
  private $params;

  /**
   * The number of instalments to be created.
   *
   * @var int
   */
  private $instalmentsCount;

  /**
   * The frequency of the recurring contribution instalments.
   *
   * @var int
   */
  private $instalmentsFrequency;

  /**
   * The frequency unit of the recurring contribution instalments.
   *
   * @var string
   */
  private $instalmentsFrequencyUnit;

  /**
   * Stores the newly created recurring contributing data
   *
   * @var array
   */
  private $recurringContribution;

  public function __construct(&$params) {
    $this->params = &$params;
    $paymentPlanSchedule = CRM_Utils_Request::retrieve('payment_plan_schedule', 'String');
    if (array_key_exists('membership_id', $this->params)) {
      //Contribution object
      $membershipId = $this->params['membership_id'];
    }
    else {
      //LineItem object
      $membershipId = $this->params['entity_id'];
    }
    $instalmentDetails = InstalmentScheduleUtils::getInstalmentDetails($paymentPlanSchedule, $membershipId);
    $this->instalmentsCount = $instalmentDetails['instalments_count'];
    $this->instalmentsFrequency = $instalmentDetails['instalments_frequency'];
    $this->instalmentsFrequencyUnit = $instalmentDetails['instalments_frequency_unit'];
  }

  /**
   * Returns the created recurring contribution.
   *
   * @return array
   */
  public function getRecurringContribution() {
    return $this->recurringContribution;
  }

  /**
   * Creates the payment plan for the membership
   * if its paid using payment plan option.
   *
   * For now, it creates the recurring contribution
   * and update the first contribution amount
   * depending on the instalments count.
   */
  public function createPaymentPlan() {
    $this->createRecurringContribution();
    $this->alterFirstContributionParameters();
  }

  /**
   * Creates the recurring contribution.
   */
  private function createRecurringContribution() {
    $this->dispatchReceiveDateCalculationHook();

    $amountPerInstalment = $this->calculateSingleInstalmentAmount($this->params['total_amount']);
    $paymentInstrument = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'name',
      'option_group_id' => 'payment_instrument',
      'value' => $this->params['payment_instrument_id'],
    ]);

    $financialType = civicrm_api3('FinancialType', 'getvalue', [
      'return' => 'name',
      'id' => $this->params['financial_type_id'],
    ]);

    $payLaterPaymentProcessorId = CRM_MembershipExtras_SettingsManager::getDefaultProcessorID();
    $cycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($this->params['receive_date'], $this->instalmentsFrequencyUnit);

    $contributionRecurParams = [
      'sequential' => 1,
      'contact_id' => $this->params['contact_id'],
      'amount' => $amountPerInstalment,
      'currency' => $this->params['currency'],
      'frequency_unit' => $this->instalmentsFrequencyUnit,
      'frequency_interval' => $this->instalmentsFrequency,
      'installments' => $this->instalmentsCount,
      'start_date' => $this->params['receive_date'],
      'contribution_status_id' => 'Pending',
      'is_test' => $this->params['is_test'],
      'cycle_day' => $cycleDay,
      'payment_processor_id' => $payLaterPaymentProcessorId,
      'financial_type_id' => $financialType,
      'payment_instrument_id' => $paymentInstrument,
      'campaign_id' => $this->params['campaign_id'],
    ];

    $this->recurringContribution = civicrm_api3('ContributionRecur', 'create', $contributionRecurParams)['values'][0];
  }

  /**
   * Dispatches the membershipextras_calculateContributionReceiveDate.
   *
   * This allows other extensions to change the payment plan's first instalment
   * receive date.
   */
  private function dispatchReceiveDateCalculationHook() {
    $receiveDate = $this->params['receive_date'];

    $dispatcher = new CalculateContributionReceiveDateDispatcher(1, $receiveDate, $this->params);
    $dispatcher->dispatch();

    $this->params['receive_date'] = $receiveDate;
  }

  /**
   * Alters the contribution 'to be created' parameters
   * before saving it.
   *
   * We here adjust the total, net tax amounts of
   * contribution depending on the installments number.
   * We also link the contribution with the newly created
   * recurring contribution.
   */
  private function alterFirstContributionParameters() {
    $this->params['contribution_recur_id'] = $this->recurringContribution['id'];
    $this->params['total_amount'] = $this->recurringContribution['amount'];
    $this->params['net_amount'] = $this->recurringContribution['amount'];

    if ($this->isUsingPriceSet() && !empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($this->params['tax_amount']);
    }
    elseif (!empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateInstalmentTax($this->params['total_amount']);
    }
  }

  /**
   * Checks if priceset was selected on the form to create the membership.
   */
  private function isUsingPriceSet() {
    $priceSetID = CRM_Utils_Request::retrieve('price_set_id', 'Int');

    if (!empty($priceSetID)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Calculates tax amount for given amount.
   *
   * @param float $totalAmount
   *
   * @return float
   */
  private function calculateInstalmentTax($totalAmount) {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = CRM_Utils_Array::value($this->params['financial_type_id'], $taxRates, 0);

    return MoneyUtilities::roundToCurrencyPrecision(
      ($totalAmount * ($rate / 100)) / (1 + ($rate / 100))
    );
  }

  /**
   * Alters the contribution 'to be created' line item parameters
   * before saving it.
   *
   * We here adjust the line total, unit price and tax amount
   * of the line item to be inline with the new contribution amount.
   */
  public function alterLineItemParameters() {
    $this->params['line_total'] = $this->calculateSingleInstalmentAmount($this->params['line_total']);
    $this->params['unit_price'] = $this->calculateSingleInstalmentAmount($this->params['unit_price']);

    if (!empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($this->params['tax_amount']);
    }
  }

  /**
   * Calculates single installment amount.
   *
   * @param float $amount
   *
   * @return float
   */
  private function calculateSingleInstalmentAmount($amount) {
    return MoneyUtilities::roundToCurrencyPrecision($amount / $this->instalmentsCount);
  }

  /**
   * Sets Contribution status to pending and set it to pay later
   */
  public function setContributionToPayLater() {
    $statusId = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ["value"],
      'option_group_id' => "contribution_status",
      'label' => "Pending",
    ])['values'][0]['value'];
    if (!empty($statusId)) {
      $this->params['contribution_status_id'] = $statusId;
      $this->params['is_pay_later'] = TRUE;
    }
  }

}
