<?php

use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as InstalmentsSchedule;

class CRM_MembershipExtras_Utils_InstalmentSchedule {

  /**
   * Gets Instalment Details by given $schedule and membership ID
   * The instalment details include instalment_count, instalment_frequency
   * and instalment_frequency_unit
   *
   * @param $schedule
   * @param $membershipID
   *
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  public static function getInstalmentDetails($schedule, $membershipID) {
    $membershipType = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => $membershipID,
      'api.MembershipType.get' => [],
    ])['values'][0]['api.MembershipType.get']['values'][0];

    $durationUnit = $membershipType['duration_unit'];
    if ($membershipType['period_type'] == 'rolling' && ($durationUnit == 'lifetime' || $durationUnit == 'month')) {
      $instalmentDetails['instalments_count'] = 1;
    }
    else {
      $instalmentDetails['instalments_count'] = self::getInstalmentCountBySchedule($schedule);
    }
    $instalmentDetails['instalments_frequency'] = self::getFrequencyInterval($schedule);
    $instalmentDetails['instalments_frequency_unit'] = self::getFrequencyUnit($schedule, $instalmentDetails['instalments_frequency']);

    return $instalmentDetails;
  }

  /**
   * Gets frequency interval by schedule
   *
   * For example, if schedule is quaterly interval shall be 3
   * if schedule is annual or monthly interval shall be 1.
   *
   * @param $schedule
   * @return int
   */
  public static function getFrequencyInterval($schedule) {
    return $schedule == InstalmentsSchedule::QUARTERLY ? 3 : 1;
  }

  /**
   * Gets frequency unit by schedule and interval
   *
   * If schedule is annual and frequency is 1
   * the frequency unit shall be year and,
   * quarterly or monthly the frequeny unit
   * will always be month.
   *
   * @param $schedule
   * @param $interval
   * @return string
   */
  public static function getFrequencyUnit($schedule, $interval) {
    return $interval == 1 && $schedule == InstalmentsSchedule::ANNUAL ? 'year' : 'month';
  }

  /**
   * Gets Instalment interval number by given schedule
   *
   * @param $schedule
   *
   * @return int
   */
  public static function getInstalmentCountBySchedule($schedule) {
    switch ($schedule) {
      case InstalmentsSchedule::MONTHLY:
        $instalmentInterval = InstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
        break;

      case InstalmentsSchedule::QUARTERLY:
        $instalmentInterval = InstalmentsSchedule::QUARTERLY_INSTALMENT_COUNT;
        break;

      default:
        $instalmentInterval = InstalmentsSchedule::ANNUAL_INTERVAL_COUNT;
    }

    return $instalmentInterval;
  }

  /**
   * Checks if Payment Plan
   *
   * @return bool
   */
  public static function isPaymentPlanWithSchedule() {
    $paymentPlanSchedule = self::getPaymentPlanSchedule();
    $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($isSavingContribution && $contributionIsPaymentPlan && $paymentPlanSchedule) {
      return TRUE;
    }

    return FALSE;
  }

  public static function getPaymentPlanSchedule() {
    return CRM_Utils_Request::retrieve('payment_plan_schedule', 'String');
  }

}
