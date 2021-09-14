<?php

use CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator as InstalmentReceiveDateCalculator;

/**
 * Post-processes Recurring Conribution Update form.
 */
class CRM_MembershipExtras_Hook_PostProcess_UpdateSubscription {

  /**
   * Form to be processed.
   *
   * @var \CRM_Contribute_Form_UpdateSubscription
   */
  private $form;

  /**
   * The submitted form values
   *
   * @var array
   */
  private $formValues;

  /**
   * Array with the data of the recurring contribution that is being updated.
   *
   * @var array
   */
  private $recurringContribution;

  /**
   * Object that calcuates installment receive date.
   *
   * @var CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator
   */
  private $receiveDateCalculator;

  public function __construct(CRM_Contribute_Form_UpdateSubscription $form) {
    $this->form = $form;
    $this->formValues = $this->form->exportValues();
    $this->setRecurringContribution();
  }

  /**
   * Loads data for recurring contribution identified by 'crid' parameter in
   * http request.
   */
  private function setRecurringContribution() {
    $recurringContributionID = CRM_Utils_Request::retrieve('crid', 'Integer', $this->form, TRUE);
    $this->recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContributionID,
    ])['values'][0];
  }

  /**
   * Post-processes CRM_Contribute_Form_UpdateSubscription to deal with added
   * fields.
   */
  public function postProcess() {
    $this->updateMemberships();
    $this->updateRelatedInstallments();
    $this->updateRecurringContribution();
    $this->updateNextScheduledContributionDate();
    $this->updateSubscriptionLineItems();
  }

  /**
   * Updates data for recurring contribution.
   *
   * @throws \Exception
   */
  private function updateRecurringContribution() {
    $autoRenew = $this->form->getElementValue('auto_renew');
    $params = [
      'id' => $this->recurringContribution['id'],
      'auto_renew' => $autoRenew,
    ];

    if ($this->isUpdatedCycleDay()) {
      $firstInstallment = $this->getFirstInstallment();
      if ($firstInstallment['contribution_status'] == 'Pending') {
        $params['start_date'] = $firstInstallment['receive_date'];
      }
    }

    civicrm_api3('ContributionRecur', 'create', $params);
  }

  private function updateNextScheduledContributionDate() {
    $nextScheduledDate = CRM_Utils_Array::value('next_sched_contribution_date', $this->formValues);
    if (empty($nextScheduledDate)) {
      return;
    }

    if ($this->isUpdatedCycleDay()) {
      $currentDayInMonth = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($nextScheduledDate, 'month');
      $currentCycleDay = $this->formValues['cycle_day'];
      $adjustmentDaysAmount = $currentCycleDay - $currentDayInMonth;

      $nextScheduledDate = new DateTime($nextScheduledDate);
      $nextScheduledDate->modify("$adjustmentDaysAmount day");
      $nextScheduledDate = $nextScheduledDate->format('Y-m-d 00:00:00');
    }

    $query = 'UPDATE civicrm_contribution_recur SET next_sched_contribution_date = %1 WHERE id = %2';
    CRM_Core_DAO::executeQuery($query, [
      1 => [$nextScheduledDate, 'String'],
      2 => [$this->recurringContribution['id'], 'Integer'],
    ]);
  }

  /**
   * Obtains the start date of the first contribution.
   *
   * @return array
   *   Array with the data for the first installment.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getFirstInstallment() {
    $contributions = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'options' => ['limit' => 1, 'sort' => 'receive_date ASC'],
    ]);

    if ($contributions['count'] > 0) {
      return $contributions['values'][0];
    }

    return [];
  }

  /**
   * Updates recurring line items associated to the recurring contribution.
   */
  private function updateSubscriptionLineItems() {
    $autoRenew = $this->form->getElementValue('auto_renew');

    if (!$autoRenew) {
      return;
    }

    civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'is_removed' => 0,
      'options' => ['limit' => 0],
      'api.ContributionRecurLineItem.create' => [
        'id' => '$value.id',
        'auto_renew' => $autoRenew,
      ],
    ]);
  }

  /**
   * Updates membership if necessary.
   */
  private function updateMemberships() {
    $autoRenew = $this->form->getElementValue('auto_renew');
    $memberships = $this->getRelatedMembershipIDs();

    foreach ($memberships as $relatedMembership) {
      $params = [
        'id' => $relatedMembership,
        'contribution_recur_id' => '',
      ];

      if ($autoRenew) {
        $params['contribution_recur_id'] = $this->recurringContribution['id'];
      }

      civicrm_api3('Membership', 'create', $params);
    }
  }

  /**
   * Obtains membership IDs of payments done with contributions related to
   * current recurring contribution.
   */
  private function getRelatedMembershipIDs() {
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'api.MembershipPayment.get' => ['contribution_id' => '$value.id'],
      'options' => ['limit' => 0, 'sort' => 'id DESC'],
    ]);

    if (!$result['count']) {
      return [];
    }

    $relatedMemberships = [];
    foreach ($result['values'] as $relatedContribution) {
      $membersipPayments = $relatedContribution['api.MembershipPayment.get'];
      if (!$membersipPayments['count']) {
        continue;
      }

      foreach ($membersipPayments['values'] as $payment) {
        if (!in_array($payment['membership_id'], $relatedMemberships)) {
          $relatedMemberships[] = $payment['membership_id'];
        }
      }
    }

    return $relatedMemberships;
  }

  /**
   * Updates related contributions with values set on the form.
   */
  private function updateRelatedInstallments() {
    $contributions = $this->getContributions();
    $updateInstallments = CRM_Utils_Request::retrieve('update_installments', 'Integer', $this->form, FALSE);

    if (count($contributions) < 1 || !$updateInstallments) {
      return;
    }

    $newFirstInstallmentReceiveDate = $this->calculateFirstReceiveDate(
      $contributions[0]['receive_date'],
      $this->formValues['cycle_day']
    );

    $installmentCount = 0;
    $this->receiveDateCalculator = new InstalmentReceiveDateCalculator($this->recurringContribution);
    $this->receiveDateCalculator->setStartDate($newFirstInstallmentReceiveDate);

    foreach ($contributions as $payment) {
      $installmentCount++;
      $this->updateContribution(
        $payment,
        $this->formValues['payment_instrument_id'],
        $installmentCount
      );
    }
  }

  /**
   * Returns list of contributions associated to current recurring contribution.
   *
   * @return array
   */
  private function getContributions() {
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'options' => ['limit' => 0, 'sort' => 'receive_date ASC'],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return array();
  }

  /**
   * Calculates first receive date for new cycle day.
   *
   * @param string $currentDate
   * @param int $newCycleDay
   *
   * @return string
   */
  private function calculateFirstReceiveDate($currentDate, $newCycleDay) {
    $frequency = $this->recurringContribution['frequency_unit'];
    $currentCycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($currentDate, $frequency);

    $difference = $newCycleDay - $currentCycleDay;
    $absoluteDifference = abs($difference);
    $interval = "P{$absoluteDifference}D";

    $date = new DateTime($currentDate);

    if ($difference > 0) {
      $date->add(new DateInterval($interval));
    }
    elseif ($difference < 0) {
      $date->sub(new DateInterval($interval));
    }

    return $date->format('Y-m-d H:i:s');
  }

  /**
   * Respectively updates receive date and/or payment instrument if either of
   * those were modified for the current recurring contribution.
   *
   * @param array $contribution
   * @param int $instrumentID
   * @param int $installmentNumber
   *
   * @throws \Exception
   */
  private function updateContribution($contribution, $instrumentID, $installmentNumber) {
    if ($contribution['contribution_status'] != 'Pending') {
      return;
    }

    $params = [];
    if ($this->isUpdatedCycleDay() && !$this->isReceiveDateInThePast($contribution)) {
      $params['receive_date'] = $this->receiveDateCalculator->calculate($installmentNumber);
    }

    if ($this->isUpdatedPaymentInstrument()) {
      $params['payment_instrument_id'] = $instrumentID;
    }

    if (!empty($params)) {
      $params['id'] = $contribution['id'];
      civicrm_api3('Contribution', 'create', $params);
    }
  }

  /**
   * Checks if the given contribution has a receive date in the future.
   *
   * @param array $contribution
   *
   * @return bool
   * @throws \Exception
   */
  private function isReceiveDateInThePast($contribution) {
    $now = new DateTime(date('Y-m-d 00:00:00'));
    $receiveDate = new DateTime($contribution['receive_date']);

    if ($receiveDate < $now) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks if cycle date was updated by user.
   *
   * @return bool
   *   True if the value was changed by user, false otherwise.
   */
  private function isUpdatedCycleDay() {
    $oldCycleDay = CRM_Utils_Request::retrieve('old_cycle_day', 'Integer', $this->form, TRUE);

    return $this->formValues['cycle_day'] != $oldCycleDay;
  }

  /**
   * Checks if payment instrument was updated by user.
   *
   * @return bool
   *   True if the value was changed by user, false otherwise.
   */
  private function isUpdatedPaymentInstrument() {
    $oldPaymentInstrument = CRM_Utils_Request::retrieve('old_payment_instrument_id', 'Integer', $this->form, TRUE);

    return $this->formValues['cycle_day'] != $oldPaymentInstrument;
  }

}
