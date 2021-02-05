<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as InstalmentSchedule;

abstract class CRM_MembershipExtras_API_PaymentSchedule_Base {

  protected $params;

  abstract public function getPaymentSchedule();

  abstract public function getPaymentScheduleOptions();

  /**
   * Validates the schedule param
   *
   * @throws API_Exception
   */
  protected function validateSchedule() {
    if (!in_array($this->params['schedule'], [
      CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::MONTHLY,
      CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::QUARTERLY,
      CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::ANNUAL,
    ])) {
      throw new API_Exception('The selected schedule is not monthly, quarterly or annual');
    }
  }

  /**
   * Gets instalment from membership types and non membership price field values
   *
   * @param array $membershipTypes
   * @param array $nonMembershipPriceFieldValues
   *
   * @return array
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   */
  protected function getInstalments(array $membershipTypes, array $nonMembershipPriceFieldValues = []) {
    $joinDate = !empty($this->params['join_date']) ? new DateTime($this->params['join_date']) : NULL;
    $startDate = !empty($this->params['start_date']) ? new DateTime($this->params['start_date']) : NULL;
    $endDate = !empty($this->params['end_date']) ? new DateTime($this->params['end_date']) : NULL;
    $membershipInstalmentsSchedule = new CRM_MembershipExtras_Service_MembershipInstalmentsSchedule(
      $membershipTypes,
      $this->params['schedule']
    );

    if (!empty($nonMembershipPriceFieldValues)) {
      $membershipInstalmentsSchedule->setNonMembershipPriceFieldValues($nonMembershipPriceFieldValues);
    }

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $membershipTypes[0]->id, $startDate, $endDate, $joinDate
    );

    $membershipTypeDates = array_map(function ($date) {
      return new DateTime($date);
    }, $membershipTypeDates);

    return $membershipInstalmentsSchedule->generate(
      $membershipTypeDates['start_date'],
      $membershipTypeDates['end_date'],
      $membershipTypeDates['join_date']
    );
  }

  /**
   * Formats instalments for displaying in Payment Plan toggle screen.
   *
   * @param array $instalments
   *
   * @throws CiviCRM_API3_Exception
   */
  public function formatInstalments(array &$instalments) {
    $pendingStatusLabel = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => "contribution_status",
      'name' => "pending",
    ])['values'][0]['label'];

    $currencySymbol = CRM_Core_BAO_Country::defaultCurrencySymbol();

    $formattedInstalments = [];
    foreach ($instalments as $key => $instalment) {
      $instalmentAmount = $currencySymbol . $instalment->getInstalmentAmount()->getTaxAmount();;
      $instalmentTaxAmount = $currencySymbol . $instalment->getInstalmentAmount()->getAmount();;
      $instalmentDate = CRM_Utils_Date::customFormat($instalment->getInstalmentDate()->format('Y-m-d'), $this->getDateformatFull());
      $formattedInstalment['instalment_no'] = $key + 1;
      $formattedInstalment['instalment_date'] = $instalmentDate;
      $formattedInstalment['instalment_tax_amount'] = $instalmentAmount;
      $formattedInstalment['instalment_amount'] = $instalmentTaxAmount;
      $formattedInstalment['instalment_status'] = $pendingStatusLabel;
      array_push($formattedInstalments, $formattedInstalment);
    }

    $instalments = $formattedInstalments;
  }

  protected function getMembershipTypeScheduleOptions($membershipType) {
    $periodType = $membershipType->period_type;
    $options = [
      InstalmentSchedule::MONTHLY => E::ts('Monthly'),
      InstalmentSchedule::QUARTERLY => E::ts('Quarterly'),
      InstalmentSchedule::ANNUAL => E::ts('Annual'),
    ];
    if ($periodType == 'fixed') {
      unset($options[InstalmentSchedule::QUARTERLY]);
    }
    else {
      $durationUnit = $membershipType->duration_unit;
      if ($durationUnit == 'lifetime' || $durationUnit == 'month') {
        unset($options[InstalmentSchedule::QUARTERLY]);
        unset($options[InstalmentSchedule::ANNUAL]);
      }
    }

    return $options;
  }

  /**
   * Gets CiviCRM's Date Format: Complete Date
   */
  private function getDateformatFull() {
    return civicrm_api3('Setting', 'get', [
      'sequential' => 1,
      'return' => ["dateformatFull"],
    ])['values'][0]['dateformatFull'];
  }

}
