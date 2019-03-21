<?php

use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

class CRM_MembershipExtras_Hook_PreProcess_ContributionView {

  /**
   * @var CRM_Contribute_Form_ContributionView
   */
  private $form;

  /**
   * CRM_MembershipExtras_Hook_PreProcess_ContributionView constructor.
   *
   * @param \CRM_Contribute_Form_ContributionView $form
   */
  public function __construct(CRM_Contribute_Form_ContributionView $form) {
    $this->form = $form;
  }

  /**
   * Implements PreProcess hook on CRM_Contribute_Form_ContributionView form.
   */
  public function preProcess() {
    if ($this->isManualPaymentPlan() && $this->isThereMoreThanOneLineItem()) {
      $this->form->assign('displayLineItems', TRUE);
    }
  }

  /**
   * Checks if the contribution being viewed corresponds to a manual payment
   * plan.
   *
   * @return bool
   * @throws \CRM_Extension_Exception
   */
  public function isManualPaymentPlan() {
    $contributionID = $this->form->get('id');
    $contribution = $this->getContribution($contributionID);
    if (empty(CRM_Utils_Array::value('id', $contribution, 0))) {
      throw new CRM_Extension_Exception('MembershipExtras: could not find contribution with ID ' . $contributionID);
    }

    if (empty($contribution['contribution_recur_id'])) {
      return false;
    }

    $recurringContribution = $this->getRecurringContribution($contribution['contribution_recur_id']);
    $processorID = CRM_Utils_Array::value('payment_processor_id', $recurringContribution);
    $isManualPaymentPlan = ManualPaymentProcessors::isManualPaymentProcessor($processorID);

    if ($isManualPaymentPlan) {
      return true;
    }

    return false;
  }

  /**
   * Obtains array with contribution's information from given contribution ID.
   *
   * @param int $contributionID
   *
   * @return array
   */
  private function getContribution($contributionID) {
    try {
      return civicrm_api3('Contribution', 'getsingle', [
        'id' => $contributionID,
      ]);
    } catch (CiviCRM_API3_Exception $exception) {
      return [];
    }
  }

  /**
   * @param int $recurringContributionID
   *
   * @return array
   */
  private function getRecurringContribution($recurringContributionID) {
    try {
      return civicrm_api3('ContributionRecur', 'getsingle', [
        'id' => $recurringContributionID,
      ]);
    } catch (CiviCRM_API3_Exception $exception) {
      return [];
    }
  }

  /**
   * Counts number of line items associated to contribution and checks if there is more than one.
   *
   * @return bool
   */
  private function isThereMoreThanOneLineItem() {
    $contributionID = $this->form->get('id');
    $lineItemsCount = civicrm_api3('LineItem', 'getcount', [
      'contribution_id' => $contributionID,
    ]);

    if ($lineItemsCount > 1) {
      return true;
    }

    return false;
  }

}
