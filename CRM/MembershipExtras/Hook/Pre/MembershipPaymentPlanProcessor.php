<?php

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor {

  /**
   * The contribution to be created parameters passed from the hook.
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
   * Processes the membership in case it is paid
   * using payment plan option.
   *
   * For now, it creates the recurring contribution
   * and update the first contribution & line itme amounts
   * depending on the installments count.
   */
  public function createPaymentPlan() {
    $this->createRecurringContribution();
    $this->updateFirstContributionData();
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

    $paymentProcessorId = 'null';
    if (!empty($this->params['payment_processor_id'])) {
      $paymentProcessorId = $this->params['payment_processor_id'];
    }

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
      'cycle_day' => $this->calculateCycleDay(),
      'payment_processor_id' => $paymentProcessorId,
      'financial_type_id' =>  $financialType,
      'payment_instrument_id' => $PaymentInstrument,
      'campaign_id' => $this->params['campaign_id'],
    ];

    $this->recurringContribution = civicrm_api3('ContributionRecur', 'create', $contributionRecurParams)['values'][0];
  }

  /**
   * Calculates the recurring contribution
   * cycle day.
   *
   * The cycle day value may vary depending on the
   * installments frequency interval and recurring
   * contribution start date.
   *
   * @return int
   */
  private function calculateCycleDay() {;
    $recurContStartDate = new DateTime($this->params['receive_date']);

    switch ($this->installmentsFrequencyUnit) {
      case 'week':
        $cycleDay =  $recurContStartDate->format('N');
        break;
      case 'month':
        $cycleDay =  $recurContStartDate->format('j');
        break;
      case 'year':
        $cycleDay =  $recurContStartDate->format('z');
        break;
      default:
        $cycleDay = 1;
    }

    return $cycleDay;
  }

  /**
   * Updates the contribution 'to be created' data.
   *
   * We here adjust the total, net tax amounts of
   * contribution depending on the installments number.
   * We also link the contribution with the newly created
   * recurring contribution.
   */
  private function updateFirstContributionData() {
    $this->params['contribution_recur_id'] =  $this->recurringContribution['id'];
    $this->params['total_amount'] =  $this->recurringContribution['amount'];
    $this->params['net_amount'] =  $this->recurringContribution['amount'];
    $this->params['tax_amount'] = $this->calculateSingleInstallmentAmount($this->params['tax_amount']);
  }

  /**
   * Updates the contribution 'to be created' line item data.
   *
   * We here adjust the line total, unit price and tax amount
   * of the line item to be inline with the new contribution amount.
   */
  public function updateLineItemData() {
    $this->params['line_total'] = $this->calculateSingleInstallmentAmount($this->params['line_total']);
    $this->params['unit_price'] = $this->calculateSingleInstallmentAmount($this->params['unit_price']);
    $this->params['tax_amount'] = $this->calculateSingleInstallmentAmount($this->params['tax_amount']);
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
