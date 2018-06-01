<?php

class CRM_MembershipExtras_Hook_Pre_Contribution {


  private $id;

  /**
   * The contribution to-be-created parameters passed from the hook.
   *
   * @var array
   */
  private $params;

  /**
   * The payment plan recurring contribution data if exist
   *
   * @var array|null
   */
  private $recurContribution = NULL;

  /**
   * Is this an offline payment plan payment ?
   *
   * @var bool
   */
  private $isOfflinePaymentPlanPayment = FALSE;

  public function __construct($id, &$params) {
    $this->id = $id;
    $this->params = $params;
    $this->setRecurContribution();
    $this->setIsOfflinePaymentPlanPayment();
  }

  /**
   * Sets $recurContribution
   */
  private function setRecurContribution() {
    if (empty($this->params['prevContribution']->contribution_recur_id)) {
      return;
    }

    $this->recurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $this->params['prevContribution']->contribution_recur_id,
    ])['values'][0];
  }

  /**
   * Sets $isOfflinePaymentPlanPayment
   */
  private function setIsOfflinePaymentPlanPayment() {
    $contributionStatuses = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $previousStatus = $contributionStatuses[$this->params['prevContribution']->contribution_status_id];
    $currentStatus = $contributionStatuses[$this->params['contribution_status_id']];

    if ($previousStatus === 'Pending' && in_array($currentStatus, ['Partially paid', 'Completed']) && !empty($this->recurContribution)) {
      $manualPaymentProcessors = CRM_MembershipExtras_Service_ManualPaymentProcessors::getIDs();
      $isOfflineContribution = empty($this->recurContribution['payment_processor_id']) ||
        in_array($this->recurContribution['payment_processor_id'], $manualPaymentProcessors);

      if (!empty($this->recurContribution['installments']) || !$isOfflineContribution) {
        $this->isOfflinePaymentPlanPayment = TRUE;
      }
    }
  }

  /**
   * Updates the payment plan recurring contribution
   * status based on the number of completed/partially paid
   * installments.
   */
  public function updatePaymentPlanStatus() {
    if (!$this->isOfflinePaymentPlanPayment) {
      return;
    }

    $paidInstallmentsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->recurContribution['id'],
      'contribution_status_id' => 'Completed',
    ]);

    $partiallyPaidInstallmentsCount = civicrm_api3('Contribution', 'getcount', [
      'contribution_recur_id' => $this->recurContribution['id'],
      'contribution_status_id' => 'Partially paid',
    ]);

    $newStatus = NULL;
    if (($partiallyPaidInstallmentsCount == 1 && $paidInstallmentsCount == 0) ||
      ($partiallyPaidInstallmentsCount == 0 && $paidInstallmentsCount == 1) ||
      ($partiallyPaidInstallmentsCount == 0 && $paidInstallmentsCount == 0)
    ) {
      $newStatus = 'In Progress';
    }

    if ($paidInstallmentsCount >= $this->recurContribution['installments']) {
      $newStatus = 'Completed';
    }

    if ($newStatus !== NULL) {
      civicrm_api3('ContributionRecur', 'create', [
        'id' => $this->recurContribution['id'],
        'contribution_status_id' => $newStatus,
      ]);
    }
  }
}
