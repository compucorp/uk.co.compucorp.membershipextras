<?php

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
   * @param sting $op
   * @param int $id
   * @param array $params
   */
  public function __construct($op, $id, &$params) {
    $this->operation = $op;
    $this->recurringContribution = $this->getRecurringContribution($id);
    $this->params = &$params;
  }

  /**
   * Preprocesses the parameters ebing used to create or update the recurring
   * contribution.
   */
  public function preProcess() {
    if ($this->operation == 'edit' && $this->isPaymentPlanStatusChange()) {
      $this->rectifyPaymentPlanStatus();
    }
  }

  /**
   * Checks if the recurring contribution is a payment plan and if its status is
   * being changed.
   *
   * @return bool
   */
  private function isPaymentPlanStatusChange() {
    if (!$this->isManualPaymentPlan()) {
      return FALSE;
    }

    $oldStatus = CRM_Utils_Array::value('contribution_status_id', $this->recurringContribution);
    $newStatus = CRM_Utils_Array::value('contribution_status_id', $this->params);

    if ($newStatus && $newStatus != $oldStatus) {
      return TRUE;
    }

    return FALSE;
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
      $this->params['end_date'] = date('Y-m-d H:i:s');
    }
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

    $allPaid = $paidInstallmentsCount >= $this->recurringContribution['installments'];
    $moreThanOneInstallment = $this->recurringContribution['installments'] > 1;

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

  /**
   * Checks if current recurring contribution corresponds to a manual payment
   * plan.
   */
  private function isManualPaymentPlan() {
    $payLaterProcessorID = 0;
    $manualPaymentProcessorsIDs = array_merge([$payLaterProcessorID], CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs());
    $isManualPaymentProcessor = in_array($this->recurringContribution['payment_processor_id'], $manualPaymentProcessorsIDs);

    if ($isManualPaymentProcessor) {
      return TRUE;
    }

    return FALSE;
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

}
