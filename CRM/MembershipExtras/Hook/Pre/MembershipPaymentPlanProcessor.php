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
  public function process() {
    if (!$this->isPaymentPlanPayment()) {
      return;
    }

    $this->createRecurringContribution();
    $this->updateContributionData();
    $this->updateLineItemData();
  }

  /**
   * Detects if the membership is paid for
   * using payment plan option.
   *
   * @return bool
   */
  private function isPaymentPlanPayment() {
    $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($isSavingContribution && $contributionIsPaymentPlan && $this->installmentsCount > 1) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Creates the recurring contribution.
   */
  private function createRecurringContribution() {
    $amountPerInstallment = $this->calculateSingleInstallmentAmount();

    $PaymentInstrument = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'name',
      'option_group_id' => 'payment_instrument',
      'value' => $this->params['payment_instrument_id'],
    ]);

    $financialType = civicrm_api3('FinancialType', 'getvalue', [
      'return' => 'name',
      'id' => $this->params['financial_type_id'],
    ]);

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
      'payment_processor_id' => $this->params['payment_processor_id'],
      'financial_type_id' =>  $financialType,
      'payment_instrument_id' => $PaymentInstrument,
      'campaign_id' => $this->params['campaign_id'],
    ];

    $this->recurringContribution = civicrm_api3('ContributionRecur', 'create', $contributionRecurParams)['values'][0];
  }

  /**
   * Calculates single installment amount.
   *
   * @return float
   */
  private function calculateSingleInstallmentAmount() {
    return floor(($this->params['total_amount'] / $this->installmentsCount) * 100) / 100;
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
  private function updateContributionData() {
    $this->params['contribution_recur_id'] =  $this->recurringContribution['id'];
    $this->params['total_amount'] =  $this->recurringContribution['amount'];
    $this->params['net_amount'] =  $this->recurringContribution['amount'];
    $this->params['tax_amount'] = $this->calculateSingleInstallmentTaxAmount();
  }

  /**
   * Calculates the contribution 'to be created' tax amount.
   *
   * @return float
   */
  private function calculateSingleInstallmentTaxAmount() {
    return floor(($this->params['tax_amount'] / $this->installmentsCount) * 100) / 100;
  }

  /**
   * Updates the contribution 'to be created' line item data.
   *
   * We here adjust the line total, unit price and tax amount
   * of the line item to be inline with the new contribution amount.
   */
  private function updateLineItemData() {
    $membershipTypeID =  key($this->params['line_item']);
    $priceValueID = key(current($this->params['line_item']));

    $lineItemAmount = $this->recurringContribution['amount'] - $this->params['tax_amount'];
    $this->params['line_item'][$membershipTypeID][$priceValueID]['line_total'] = $lineItemAmount;
    $this->params['line_item'][$membershipTypeID][$priceValueID]['unit_price'] = $lineItemAmount;
    $this->params['line_item'][$membershipTypeID][$priceValueID]['tax_amount'] = $this->params['tax_amount'];
  }

}
