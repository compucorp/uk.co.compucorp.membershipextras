<?php

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor {

  /**
   * The contribution or line item to-be-created parameters passed from the hook.
   *
   * @var array
   */
  private $params;

  /**
   * The number of installments to be created.
   *
   * @var int
   */
  private $installmentsCount;

  /**
   * The frequency of the recurring contribution installments.
   *
   * @var int
   */
  private $installmentsFrequency;

  /**
   * The frequency unit of the recurring contribution installments.
   *
   * @var string
   */
  private $installmentsFrequencyUnit;

  /**
   * Stores the newly created recurring contributing data
   *
   * @var array
   */
  private $recurringContribution;

  public function __construct(&$params) {
    $this->params = &$params;

    $this->installmentsCount = CRM_Utils_Request::retrieve('installments', 'Int');
    $this->installmentsFrequency = CRM_Utils_Request::retrieve('installments_frequency', 'Int');
    $this->installmentsFrequencyUnit = CRM_Utils_Request::retrieve('installments_frequency_unit', 'String');
  }

  /**
   * Creates the payment plan for the membership
   * if its paid using payment plan option.
   *
   * For now, it creates the recurring contribution
   * and update the first contribution amount
   * depending on the installments count.
   */
  public function createPaymentPlan() {
    $this->createRecurringContribution();
    $this->alterFirstContributionParameters();
  }

  /**
   * Creates the recurring contribution.
   */
  private function createRecurringContribution() {
    $amountPerInstallment = $this->calculateSingleInstallmentAmount($this->params['total_amount']);

    $PaymentInstrument = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'name',
      'option_group_id' => 'payment_instrument',
      'value' => $this->params['payment_instrument_id'],
    ]);

    $financialType = civicrm_api3('FinancialType', 'getvalue', [
      'return' => 'name',
      'id' => $this->params['financial_type_id'],
    ]);

    $payLaterPaymentProcessorsId = CRM_MembershipExtras_SettingsManager::getDefaultProcessor()['id'];
    $cycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($this->params['receive_date'], $this->installmentsFrequencyUnit);

    $contributionRecurParams = [
      'sequential' => 1,
      'contact_id' => $this->params['contact_id'],
      'amount' => $amountPerInstallment,
      'currency' => $this->params['currency'],
      'frequency_unit' => $this->installmentsFrequencyUnit,
      'frequency_interval' => $this->installmentsFrequency,
      'installments' => $this->installmentsCount,
      'start_date' => $this->params['receive_date'],
      'contribution_status_id' => 'Pending',
      'is_test' => $this->params['is_test'],
      'cycle_day' => $cycleDay,
      'payment_processor_id' => $payLaterPaymentProcessorsId,
      'financial_type_id' =>  $financialType,
      'payment_instrument_id' => $PaymentInstrument,
      'campaign_id' => $this->params['campaign_id'],
    ];

    $this->recurringContribution = civicrm_api3('ContributionRecur', 'create', $contributionRecurParams)['values'][0];
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
    $this->params['contribution_recur_id'] =  $this->recurringContribution['id'];
    $this->params['total_amount'] =  $this->recurringContribution['amount'];
    $this->params['net_amount'] =  $this->recurringContribution['amount'];
    $this->params['tax_amount'] = $this->calculateSingleInstallmentAmount($this->params['tax_amount']);
  }

  /**
   * Alters the contribution 'to be created' line item parameters
   * before saving it.
   *
   * We here adjust the line total, unit price and tax amount
   * of the line item to be inline with the new contribution amount.
   */
  public function alterLineItemParameters() {
    $this->params['line_total'] = $this->calculateSingleInstallmentAmount($this->params['line_total']);
    $this->params['unit_price'] = $this->calculateSingleInstallmentAmount($this->params['unit_price']);

    if (!empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateSingleInstallmentAmount($this->params['tax_amount']);
    }
  }

  /**
   * Calculates single installment amount.
   *
   * @param float $amount
   *
   * @return float
   */
  private function calculateSingleInstallmentAmount($amount) {
    return floor(($amount / $this->installmentsCount) * 100) / 100;
  }

}
