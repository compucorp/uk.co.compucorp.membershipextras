<?php

class CRM_MembershipExtras_Service_PaymentPlanStatusCalculator {

  const CONTRIBUTION_STATUS_PENDING = 'Pending';
  const CONTRIBUTION_STATUS_INPROGRESS = 'In Progress';
  const CONTRIBUTION_STATUS_COMPLETED = 'Completed';

  private $recurContributionId;

  private $contributionStatusValueMap;

  private $installmentsCount;

  private $currentStatusId;

  public function __construct($recurContributionId) {
    $this->recurContributionId = $recurContributionId;

    $this->setContributionStatusesValueMap();
    $this->setRecurContributionDetails();
  }

  private function setContributionStatusesValueMap() {
    $contributionStatuses = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'contribution_status',
      'options' => ['limit' => 0],
    ]);
    $contributionStatusValueMap = [];
    foreach ($contributionStatuses['values'] as $currentStatus) {
      $contributionStatusValueMap[$currentStatus['name']] = $currentStatus['value'];
    }

    $this->contributionStatusValueMap =  $contributionStatusValueMap;
  }

  private function setRecurContributionDetails() {
    $recurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'return' => ['contribution_status_id', 'installments'],
      'id' => $this->recurContributionId,
    ])['values'][0];

    $this->installmentsCount = CRM_Utils_Array::value('installments', $recurContribution, 0);
    $this->currentStatusId = $recurContribution['contribution_status_id'];
  }

  /**
   * Calculates the status of the payment
   * plan (recuring contribution) based
   * on its current status and the related
   * contributions statuses.
   *
   * @param int $recurContributionId
   *   DateTime acceptable format
   *
   * @return string
   */
  public function calculate() {
    $processableStatuses = [
      $this->contributionStatusValueMap[self::CONTRIBUTION_STATUS_PENDING],
      $this->contributionStatusValueMap[self::CONTRIBUTION_STATUS_COMPLETED],
      $this->contributionStatusValueMap[self::CONTRIBUTION_STATUS_INPROGRESS],
    ];
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
