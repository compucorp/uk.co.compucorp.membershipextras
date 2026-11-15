<?php

class CRM_MembershipExtras_Service_PaymentPlanStatusCalculator {

  const CONTRIBUTION_STATUS_PENDING = 'Pending';
  const CONTRIBUTION_STATUS_INPROGRESS = 'In Progress';
  const CONTRIBUTION_STATUS_COMPLETED = 'Completed';

  private $recurContributionId;

  /**
   * Recurring contribution (plan) status values, keyed by name.
   *
   * @var array
   */
  private $recurStatusValueMap;

  private $installmentsCount;

  private $currentStatusId;

  public function __construct($recurContributionId) {
    $this->recurContributionId = $recurContributionId;

    $this->setRecurStatusValueMap();
    $this->setRecurContributionDetails();
  }

  /**
   * Loads the plan statuses from the recurring contribution status options.
   *
   * Not the contribution status options: on CiviCRM 6.x those have no
   * "In Progress", and some values mean different statuses in the two.
   */
  private function setRecurStatusValueMap() {
    $statuses = CRM_Contribute_BAO_ContributionRecur::buildOptions('contribution_status_id', 'validate');
    $this->recurStatusValueMap = is_array($statuses) ? array_flip($statuses) : [];
  }

  private function setRecurContributionDetails() {
    $recurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'return' => ['contribution_status_id', 'installments'],
      'id' => $this->recurContributionId,
    ])['values'][0];

    $this->installmentsCount = CRM_Utils_Array::value('installments', $recurContribution, 1);
    $this->currentStatusId = $recurContribution['contribution_status_id'];
  }

  /**
   * Calculates the status of the payment
   * plan (recuring contribution) based
   * on its current status and the related
   * contributions statuses.
   *
   * @return string
   */
  public function calculate() {
    $processableStatuses = array_filter([
      $this->recurStatusValueMap[self::CONTRIBUTION_STATUS_PENDING] ?? NULL,
      $this->recurStatusValueMap[self::CONTRIBUTION_STATUS_COMPLETED] ?? NULL,
      $this->recurStatusValueMap[self::CONTRIBUTION_STATUS_INPROGRESS] ?? NULL,
    ]);
    if (!in_array($this->currentStatusId, $processableStatuses)) {
      return NULL;
    }

    $paidInstallmentsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->recurContributionId,
      'contribution_status_id' => self::CONTRIBUTION_STATUS_COMPLETED,
    ]);

    $partiallyPaidInstallmentsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->recurContributionId,
      'contribution_status_id' => 'Partially paid',
    ]);

    $allPaid = $paidInstallmentsCount >= $this->installmentsCount;
    $moreThanOneInstallment = $this->installmentsCount > 1;

    switch (TRUE) {
      case $moreThanOneInstallment && $allPaid:
        $status = self::CONTRIBUTION_STATUS_COMPLETED;
        break;

      case $paidInstallmentsCount >= 1 || $partiallyPaidInstallmentsCount >= 1:
        $status = self::CONTRIBUTION_STATUS_INPROGRESS;
        break;

      default:
        $status = self::CONTRIBUTION_STATUS_PENDING;
    }

    return $status;
  }

}
