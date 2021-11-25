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

    $paymentMethod = $this->params['payment_method'];

    return $membershipInstalmentsSchedule->generate(
      $paymentMethod,
      $membershipTypeDates['start_date'],
      $membershipTypeDates['end_date'],
      $membershipTypeDates['join_date']
    );
  }

  /**
   * Formats instalments for a result of the PaymentSchedule API.
   *
   * @param array $instalments
   *
   * @throws CiviCRM_API3_Exception
   */
  public function formatInstalments(array &$instalments) {
    $formattedInstalments = [];
    foreach ($instalments as $key => $instalment) {
      $formattedInstalment['instalment_no'] = $key + 1;
      $formattedInstalment['instalment_date'] = $instalment->getInstalmentDate()->format('Y-m-d');
      $formattedInstalment['instalment_tax_amount'] = $instalment->getInstalmentAmount()->getTaxAmount();
      $formattedInstalment['instalment_amount'] = $instalment->getInstalmentAmount()->getAmount();
      $formattedInstalment['instalment_total_amount'] = $instalment->getInstalmentAmount()->getTotalAmount();
      $formattedInstalment['instalment_status'] = $this->getPendingStatusValue();
      $formattedLineItems = [];
      foreach ($instalment->getInstalmentAmount()->getLineItems() as $lineKey => $lineItem) {
        $formattedLineItems[$lineKey]['item_no'] = $lineKey + 1;
        $formattedLineItems[$lineKey]['financial_type_id'] = $lineItem->getFinancialTypeId();
        $formattedLineItems[$lineKey]['quantity'] = $lineItem->getQuantity();
        $formattedLineItems[$lineKey]['unit_price'] = $lineItem->getUnitPrice();
        $formattedLineItems[$lineKey]['sub_total'] = $lineItem->getSubTotal();
        $formattedLineItems[$lineKey]['tax_rate'] = $lineItem->getTaxRate();
        $formattedLineItems[$lineKey]['tax_amount'] = $lineItem->getTaxAmount();
        $formattedLineItems[$lineKey]['total_amount'] = $lineItem->getTotalAmount();
      }

      $formattedInstalment['instalment_lineitems'] = $formattedLineItems;
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

  private function getPendingStatusValue() {
    return civicrm_api3('OptionValue', 'getsingle', [
      'sequential' => 1,
      'option_group_id' => "contribution_status",
      'name' => "Pending",
    ])['value'];
  }

}
