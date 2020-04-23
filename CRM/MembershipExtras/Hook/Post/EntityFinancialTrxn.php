<?php

use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;
use CRM_MembershipExtras_Service_PaymentPlanStatusCalculator as PaymentPlanStatusCalculator;

class CRM_MembershipExtras_Hook_Post_EntityFinancialTrxn {

  /**
   * The created entity Financial Transaction object
   * passed from the hook.
   *
   * @var CRM_Financial_DAO_EntityFinancialTrxn
   */
  private $entityFinancialTrx;

  /**
   * The transaction recurring contribution if exist.
   *
   * @var null
   */
  private $recurContribution = NULL;

  /**
   * @param CRM_Financial_DAO_EntityFinancialTrxn $entityFinancialTrx
   */
  public function __construct(&$entityFinancialTrx) {
    $this->entityFinancialTrx = $entityFinancialTrx;
  }

  /**
   * Updates the payment plan recurring contribution
   * status based on the number of completed/partially paid
   * installments.
   */
  public function updatePaymentPlanStatus() {
    if ($this->entityFinancialTrx->entity_table != 'civicrm_financial_item') {
      return;
    }

    $this->setRecurContribution();
    $isManualPaymentPlanTransaction = ManualPaymentProcessors::isManualPaymentProcessor($this->recurContribution['payment_processor_id']);
    if (empty($this->recurContribution) || !$isManualPaymentPlanTransaction) {
      return;
    }

    $newStatus = $this->generatePaymentPlanNewStatus();
    if ($newStatus == NULL) {
      return;
    }

    $updateParams = [
      'id' => $this->recurContribution['id'],
      'contribution_status_id' => $newStatus,
    ];

    if ($newStatus == 'Completed') {
      $updateParams['end_date'] = $this->generateNewPaymentPlanEndDate();
    }

    civicrm_api3('ContributionRecur', 'create', $updateParams);
  }

  /**
   * Sets the recurring contribution data for
   * the financial transaction if exist.
   */
  private function setRecurContribution() {
    $entityFinancialTrxn = civicrm_api3('EntityFinancialTrxn', 'get', [
      'sequential' => 1,
      'entity_table' => 'civicrm_contribution',
      'financial_trxn_id' => $this->entityFinancialTrx->financial_trxn_id,
    ]);
    if (empty($entityFinancialTrxn['values'][0])) {
      return;
    }
    $contributionId = $entityFinancialTrxn['values'][0]['entity_id'];

    $contribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['contribution_recur_id'],
      'id' => $contributionId,
    ]);
    if (empty($contribution['values'][0]['contribution_recur_id'])) {
      return;
    }
    $recurContributionID = $contribution['values'][0]['contribution_recur_id'];

    $recurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurContributionID,
    ]);
    if (empty($recurContribution['values'][0])) {
      return;
    }

    $this->recurContribution =  $recurContribution['values'][0];
  }

  /**
   * Generates the payment plan new status
   *
   * @return string|NULL
   */
  private function generatePaymentPlanNewStatus() {
    $paymentPlanStatusCalculator = new PaymentPlanStatusCalculator($this->recurContribution['id']);
    return $paymentPlanStatusCalculator->calculate();
  }

  private function generateNewPaymentPlanEndDate() {
    $lastPaymentPlanContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $this->recurContribution['id'],
      'options' => ['sort' => 'id DESC', 'limit' => 1],
    ]);

    $endDate = NULL;
    if (!empty($lastPaymentPlanContribution['values'][0]['receive_date'])) {
      $endDate = $lastPaymentPlanContribution['values'][0]['receive_date'];
    }

    return $endDate;
  }

}
