<?php

use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;
use CRM_MembershipExtras_Service_PaymentPlanStatusCalculator as PaymentPlanStatusCalculator;

/**
 * Implements pre hook on ContributionRecur entity.
 */
class CRM_MembershipExtras_Hook_Pre_ContributionRecur {

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
    if ($this->operation == 'create') {
      $this->calculateCycleDay();
    }

    $paymentProcessorID = CRM_Utils_Array::value('payment_processor_id', $this->recurringContribution, 0);
    $isManualPaymentPlan = ManualPaymentProcessors::isManualPaymentProcessor($paymentProcessorID);

    if ($isManualPaymentPlan) {
      $this->preventUpdatingNextScheduledContributionDate();
    }

    if ($this->operation == 'edit' && $isManualPaymentPlan) {
      $this->rectifyPaymentPlanStatus();
    }
  }

  private function calculateCycleDay() {
    if (!empty($this->params['start_date']) && !empty($this->params['frequency_unit'])) {
      $this->params['cycle_day'] =
        CRM_MembershipExtras_Service_CycleDayCalculator::calculate($this->params['start_date'], $this->params['frequency_unit']);
    }
  }

  /**
   * Prevents any update to 'next_sched_contribution_date' field through API or DAO.
   * We do this since we don't want CiviCRM core or webform_civicrm module to alter
   * the value of this field, any update to it should only happen through Membershipextras,
   * since this field has an impact on auto-renewal.
   * This also means that Membershipextras cannot alter the value of
   * this field through DAO or API, but rather through direct SQL query
   * or otherwise the value will be unset here.
   */
  private function preventUpdatingNextScheduledContributionDate() {
    if (!empty($this->params['next_sched_contribution_date'])) {
      unset($this->params['next_sched_contribution_date']);
    }
  }

  /**
   * Calculates recurring contribution status.
   */
  private function rectifyPaymentPlanStatus() {
    $paymentPlanStatusCalculator = new PaymentPlanStatusCalculator($this->recurringContribution['id']);
    $newStatus = $paymentPlanStatusCalculator->calculate();

    if ($newStatus == NULL) {
      return;
    }

    $contributionStatus = CRM_Contribute_PseudoConstant::contributionStatus(NULL, 'name');
    $statusID = array_search($newStatus, $contributionStatus);
    $this->params['contribution_status_id'] = $statusID;

    if ($newStatus === PaymentPlanStatusCalculator::CONTRIBUTION_STATUS_COMPLETED && $this->recurringContribution['installments'] > 1) {
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

}
