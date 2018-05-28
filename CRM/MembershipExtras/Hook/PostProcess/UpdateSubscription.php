<?php

use CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator as InstallmentReceiveDateCalculator;

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

  public function __construct(CRM_Contribute_Form_UpdateSubscription $form) {
    $this->form = $form;
    $this->recurringContributionID = CRM_Utils_Request::retrieve('crid', 'Integer', $this->form, TRUE);
    $this->recurringContribution = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->recurringContributionID
    ]);
    $this->receiveDateCalculator = new InstallmentReceiveDateCalculator($this->recurringContribution);
  }

  /**
   * Postprocesses CRM_Contribute_Form_UpdateSubscription to deal with added
   * fields.
   */
  public function postProcess() {
    $updateInstallments = CRM_Utils_Request::retrieve('update_installments', 'Integer', $this->form, FALSE);

    if ($updateInstallments == '1') {
      $params = $this->form->exportValues();
      $contributions = civicrm_api3('Contribution', 'get', [
        'sequential' => 1,
        'contribution_recur_id' => $this->recurringContributionID,
        'options' => ['limit' => 0, 'sort' => 'receive_date ASC'],
      ]);
      $newFirstInstallmentReceiveDate = $this->calculateFirstReceiveDate(
        $contributions['values'][0]['receive_date'],
        $params['cycle_day']
      );

      $this->receiveDateCalculator->setStartDate($newFirstInstallmentReceiveDate);
      $installmentCount = 0;

      foreach ($contributions['values'] as $payment) {
        $installmentCount++;

        if ($payment['contribution_status'] != 'Pending') {
          continue;
        }

        $receiveDate = $this->receiveDateCalculator->calculate($installmentCount);
        civicrm_api3('Contribution', 'create', [
          'id' => $payment['id'],
          'payment_instrument_id' => $params['payment_instrument_id'],
          'receive_date' => $receiveDate,
        ]);
      }
    }
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
    } elseif ($difference < 0) {
      $date->sub(new DateInterval($interval));
    }

    return $date->format('Y-m-d H:i:s');
  }

}
