<?php

use CRM_MembershipExtra_PaymentProcessorType_ManualRecurringPayment as ManualRecurringPaymenProcessorType;

/**
 * Called by membershipextra_civicrm_pre hook
 * before editing/renewing a membership
 * record.
 *
 * Class CRM_MembershipExtra_Hook_PreEdit_Membership
 */
class CRM_MembershipExtra_Hook_PreEdit_Membership {

  private $id = NULL;

  private $params = [];

  public function __construct($id, &$params) {
    $this->id = $id;
    $this->params = &$params;
  }

  /**
   * Prevents extending offline recurring Membership.
   *
   * If a membership price will be paid on multlipe
   * installments, then each time an installment get
   * paid then the membership will get extended.
   * For example if you have 12 installments for
   * 120 USD - 1 year membership, then each time an
   * installment get paid the membership will get extended
   * by one year (it is how civicrm work!), this method
   * prevent civicrm from doing that.
   */
  public function preventExtendingOfflineRecurringMembership() {
    if ($this->isOfflineRecurringMembership()) {
      unset($this->params['end_date']);
    }
  }

  /**
   * Determines if the payment for a membership
   * subscription is offline (pay later) and recurring.
   *
   * @return bool
   */
  private function isOfflineRecurringMembership() {
    $recContributionID = $this->getMembershipRecurringContribution();

    if ($recContributionID === NULL) {
      return FALSE;
    }

    return $this->isOfflineRecurringContribution($recContributionID);
  }

  /**
   * Gets the recurring contribution
   * for the membership if it does exist.
   *
   * @return int|null
   *   The recurring contribution ID or NULL
   *   if no recurring contribution exist.
   */
  private function getMembershipRecurringContribution() {
    $membershipPayment = civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'membership_id' => $this->id,
      'options' => array('limit' => 1),
      'return' => ['id', 'contribution_id'],
    ]);

    if (empty($membershipPayment['id'])) {
      return NULL;
    }

    $membershipPayment = $membershipPayment['values'][0];

    $membershipContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $membershipPayment['contribution_id'],
      'options' => array('limit' => 1),
      'return' => ['id', 'contribution_recur_id'],
    ]);

    if (empty($membershipContribution['id'])) {
      return NULL;
    }

    $membershipContribution= $membershipContribution['values'][0];

    if (empty($membershipContribution['contribution_recur_id'])) {
      return NULL;
    }

    return $membershipContribution['contribution_recur_id'];
  }


  /**
   * Determines if the recurring
   * contribution is offline (pay later).
   *
   * @param $recurringContributionID
   * @return bool
   */
  private function isOfflineRecurringContribution($recurringContributionID) {
    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContributionID,
    ])['values'][0];

    $moreThanOneInstallment = $recurringContribution['installments'] > 1;

    $offlineRecurringProcessors = self::getOfflineRecurringPaymentProcessors();
    $offlineContribution = empty($recurringContribution['payment_processor_id']) ||
      in_array($recurringContribution['payment_processor_id'], $offlineRecurringProcessors);

    if ($moreThanOneInstallment && $offlineContribution) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the list of offline Recurring Payment Processors
   *
   * @return array
   */
  private function getOfflineRecurringPaymentProcessors() {
    $offlineRecPaymentProcessors = civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'payment_processor_type_id' => ManualRecurringPaymenProcessorType::NAME,
    ]);

    $recPaymentProcessors = [];
    if (!empty($offlineRecPaymentProcessors['values'])) {
      foreach ($offlineRecPaymentProcessors['values'] as $paymentProcessor) {
        $recPaymentProcessors[] = $paymentProcessor['id'];
      }
    }

    return $recPaymentProcessors;
  }

}
