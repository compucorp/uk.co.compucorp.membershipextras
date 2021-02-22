<?php

use CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_AbstractProcessor as AbstractProcessor;
use CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate as CalculateContributionReceiveDateDispatcher;

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_Contribution extends AbstractProcessor {

  /**
   * Stores the newly created recurring contributing data
   *
   * @var array
   */
  private $recurringContribution;

  public function __construct(&$params) {
    $this->params = &$params;
    $this->assignInstalmentDetails();
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
    $this->alterRecievedDate();
    $this->createRecurringContribution();
    $this->alterFirstContributionParameters();
  }

  /**
   * Set Contribution receive date as Membership start date.
   */
  private function alterRecievedDate() {
    $membership = civicrm_api3('Membership', 'getSingle', [
      'sequential' => 1,
      'id' => $this->membershipId,
    ]);
    $this->params['receive_date'] = $membership['start_date'];
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
      $membership = civicrm_api3('Membership', 'get', [
        'sequential' => 1,
        'id' => $this->membershipId,
      ])['values'][0];
      $membershipTypes = $this->getLineItemsFixedPeriodMembershipType();
      if (!empty($membershipTypes)) {
        $instalmentAmount = $this->getProRatedInstalmentAmount($membershipTypes, $membership['start_date']);
        $this->params['tax_amount'] = $instalmentAmount->getCalculator()->getTaxAmount();
      }
      else {
        $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($this->params['tax_amount']);
      }
    }
    elseif (!empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateInstalmentTax($this->params['total_amount'], $this->params['financial_type_id']);
    }
  }

  /**
   * Gets only fixed period membeship type that belong to each line item
   */
  private function getLineItemsFixedPeriodMembershipType() {
    $membershipTypes = [];
    foreach (CRM_Utils_Array::value('line_item', $this->params, []) as $types) {
      foreach ($types as &$line) {
        $membershipType = CRM_Member_BAO_MembershipType::findById($line['membership_type_id']);
        if ($membershipType->period_type == 'fixed') {
          $membershipType->minimum_fee = $line['line_total'];
          array_push($membershipTypes, $membershipType);
        }
      }
    }

    return $membershipTypes;
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
