<?php

class CRM_MembershipExtras_Hook_Pre_EntityFinancialTrxn {

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
    if (empty($this->recurContribution) || !$this->isPaymentPlanTransaction()) {
      return;
    }

    $newStatus = $this->generatePaymentPlanNewStatus();
    if ($newStatus !== NULL) {
      civicrm_api3('ContributionRecur', 'create', [
        'id' => $this->recurContribution['id'],
        'contribution_status_id' => $newStatus,
      ]);
    }
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
   * Determines if the transaction is a payment
   * plan transaction.
   *
   * @return bool
   */
  private function isPaymentPlanTransaction() {
    $payLaterProcessorID = 0;
    $manualPaymentProcessorsIDs = array_merge([$payLaterProcessorID], CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs());

    if ($this->recurContribution['installments'] > 1 && in_array($this->recurContribution['payment_processor_id'], $manualPaymentProcessorsIDs)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Generates the payment plan new status
   *
   * @return string|NULL
   */
  private function generatePaymentPlanNewStatus() {
    $paidInstallmentsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->recurContribution['id'],
      'contribution_status_id' => 'Completed',
    ]);

    $partiallyPaidInstallmentsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->recurContribution['id'],
      'contribution_status_id' => 'Partially paid',
    ]);

    $newStatus = NULL;
    if ($paidInstallmentsCount == 1 || $partiallyPaidInstallmentsCount == 1) {
      $newStatus = 'In Progress';
    }

    if ($paidInstallmentsCount >= $this->recurContribution['installments']) {
      $newStatus = 'Completed';
    }

    return $newStatus;
  }

}
