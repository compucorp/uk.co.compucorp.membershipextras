<?php

use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

/**
 * Implements pre hook on ContributionRecur entity.
 */
class CRM_MembershipExtras_Hook_Pre_ContributionRecur {
  const CONTRIBUTION_STATUS_PENDING = 'Pending';
  const CONTRIBUTION_STATUS_INPROGRESS = 'In Progress';
  const CONTRIBUTION_STATUS_COMPLETED = 'Completed';

  /**
   * Operation being performed.
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
   * List of possible contribution statuses, mapping names to machine values.
   *
   * @var array
   */
  private $contributionStatusValueMap;

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
    $this->contributionStatusValueMap = $this->getContributionStatusesValueMap();
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
   * Builds an array mapping contribution status name's to their value.
   *
   * @return array
   */
  private function getContributionStatusesValueMap() {
    $contributionStatuses = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contribution_status',
      'options' => ['limit' => 0],
    ]);
    $contributionStatusValueMap = [];
    foreach ($contributionStatuses['values'] as $currentStatus) {
      $contributionStatusValueMap[$currentStatus['name']] = $currentStatus['value'];
    }

    return $contributionStatusValueMap;
  }

  /**
   * Pre-processes the parameters being used to create or update the recurring
   * contribution.
   */
  public function preProcess() {
    $paymentProcessorID = CRM_Utils_Array::value('payment_processor_id', $this->recurringContribution, 0);
    $isManualPaymentPlan = ManualPaymentProcessors::isManualPaymentProcessor($paymentProcessorID);

    if ($this->operation == 'edit' && $isManualPaymentPlan) {
      $this->rectifyPaymentPlanStatus();
    }
  }

  /**
   * Calculates recurring contribution status.
   */
  private function rectifyPaymentPlanStatus() {
    $currentContributionStatus = $this->recurringContribution['contribution_status_id'];
    $processableContributionStatuses = [
      $this->contributionStatusValueMap[self::CONTRIBUTION_STATUS_PENDING],
      $this->contributionStatusValueMap[self::CONTRIBUTION_STATUS_COMPLETED],
      $this->contributionStatusValueMap[self::CONTRIBUTION_STATUS_INPROGRESS],
    ];
    if (!in_array($currentContributionStatus, $processableContributionStatuses)) {
      return;
    }

    $status = $this->calculateRecurringContributionStatus();
    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $statusID = array_search($status, $contributionStatus);
    $this->params['contribution_status_id'] = $statusID;

    if ($status === self::CONTRIBUTION_STATUS_COMPLETED && $this->recurringContribution['installments'] > 1) {
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
      'contribution_status_id' => self::CONTRIBUTION_STATUS_COMPLETED,
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
        $status = self::CONTRIBUTION_STATUS_COMPLETED;
        break;

      case $paidInstallmentsCount >= 1 || $partiallyPaidInstallmentsCount >=  1:
        $status = self::CONTRIBUTION_STATUS_INPROGRESS;
        break;

      default:
        $status = self::CONTRIBUTION_STATUS_PENDING;
    }

    return $status;
  }

}
