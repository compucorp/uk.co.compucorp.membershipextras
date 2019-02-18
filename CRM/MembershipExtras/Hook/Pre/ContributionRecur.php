<?php

use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

/**
 * Implements pre hook on ContributionRecur entity.
 */
class CRM_MembershipExtras_Hook_Pre_ContributionRecur {

  /**
   * Operation being performe.
   *
   * @var string
   */
  private $operation;

  /**
   * Current data for the recurring contribution, if it's being updated.
   *
   * @var array
   */
  private $recurringContribution;

  /**
   * List of parameters that are being used to create/update the recurring
   * contribution.
   *
   * @var array
   */
  private $params;

  /**
   * CRM_MembershipExtras_Hook_Pre_ContributionRecur constructor.
   *
   * @param string $op
   * @param int $id
   * @param array $params
   */
  public function __construct($op, $id, &$params) {
    $this->operation = $op;
    $this->recurringContribution = $this->getRecurringContribution($id);
    $this->params = &$params;
  }

  /**
   * Loads the data for the given recurring contribution ID and returns it.
   *
   * @param int $id
   *
   * @return array
   */
  private function getRecurringContribution($id) {
    if (empty($id)) {
      return [];
    }

    return civicrm_api3('ContributionRecur', 'getsingle', [
      'sequential' => 1,
      'id' => $id,
    ]);
  }

  /**
   * Pre-processes the parameters being used to create or update the recurring
   * contribution.
   */
  public function preProcess() {
    $isManualPaymentPlan = ManualPaymentProcessors::isManualPaymentProcessor(
      CRM_Utils_Array::value('payment_processor_id', $this->recurringContribution, null)
    );
    if ($this->operation == 'edit' && $isManualPaymentPlan) {
      $this->rectifyPaymentPlanStatus();
    }
  }

  /**
   * Calculates recurring contribution status.
   */
  private function rectifyPaymentPlanStatus() {
    $status = $this->calculateRecurringContributionStatus();

    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $statusID = array_search($status, $contributionStatus);
    $this->params['contribution_status_id'] = $statusID;

    if ($status === 'Completed' && $this->recurringContribution['installments'] > 1) {
      $this->params['end_date'] = $this->generateNewPaymentPlanEndDate();
    }
  }

  /**
   * Generates end date for recurring contribution from last paid contribution.
   *
   * @return string
   */
  private function generateNewPaymentPlanEndDate() {
    $lastPaymentPlanContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $this->recurringContribution['id'],
      'options' => ['sort' => 'id DESC', 'limit' => 1],
    ]);

    $endDate = NULL;
    if (!empty($lastPaymentPlanContribution['values'][0]['receive_date'])) {
      $endDate = $lastPaymentPlanContribution['values'][0]['receive_date'];
    }

    return $endDate;
  }

  /**
   * Calculates the status of the current recurring contribution.
   *
   * @return string
   */
  private function calculateRecurringContributionStatus() {
    $paidInstallmentsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->recurringContribution['id'],
      'contribution_status_id' => 'Completed',
    ]);

    $partiallyPaidInstallmentsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->recurringContribution['id'],
      'contribution_status_id' => 'Partially paid',
    ]);

    $installments = CRM_Utils_Array::value('installments', $this->recurringContribution, 0);
    $allPaid = $paidInstallmentsCount >= $installments;
    $moreThanOneInstallment = $installments > 1;

    switch (true) {
      case $moreThanOneInstallment && $allPaid:
        $status = 'Completed';
        break;

      case $paidInstallmentsCount >= 1 || $partiallyPaidInstallmentsCount >=  1:
        $status = 'In Progress';
        break;

      default:
        $status = 'Pending';
    }

    return $status;
  }

}
