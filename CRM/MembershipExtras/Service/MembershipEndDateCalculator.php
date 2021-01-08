<?php

/**
 * Class CRM_MembershipExtras_Service_MembershipEndDateCalculator.
 */
class CRM_MembershipExtras_Service_MembershipEndDateCalculator {

  /**
   * Calculates the membership new end date
   * for renewal.
   *
   * @param int $membershipID
   *
   * @return string
   * @throws \CiviCRM_API3_Exception
   * @throws \CiviCRM_API3_Exception|\Exception
   */
  public static function calculate($membershipID) {
    $newEndDate = 'null';
    $membershipDetails = self::getMembership($membershipID);
    $interval = self::getMembershipPeriodInterval($membershipDetails);
    if (!empty($interval)) {
      $currentEndDate = new DateTime($membershipDetails['end_date']);
      $currentEndDate->add(new DateInterval($interval));
      $newEndDate = $currentEndDate->format('Ymd');
    }

    return $newEndDate;
  }

  /**
   * Calculates the previous end date for the given membership.
   *
   * @param int $membershipID
   *
   * @return string
   * @throws \CiviCRM_API3_Exception|\Exception
   */
  public static function calculatePreviousEndDate($membershipID) {
    $previousEndDate = 'null';
    $membershipDetails = self::getMembership($membershipID);
    $interval = self::getMembershipPeriodInterval($membershipDetails);
    if (!empty($interval)) {
      $currentEndDate = new DateTime($membershipDetails['end_date']);
      $currentEndDate->sub(new DateInterval($interval));
      $previousEndDate = $currentEndDate->format('Ymd');
    }

    return $previousEndDate;
  }

  /**
   * Obtains information of the membership with the given ID.
   *
   * @param int $membershipID
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  private static function getMembership($membershipID) {
    return civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ['end_date', 'membership_type_id.duration_unit', 'membership_type_id.duration_interval'],
      'id' => $membershipID,
    ])['values'][0];
  }

  /**
   * Calculates the period string to be used to calculate membership end date.
   *
   * @param array $membershipDetails
   *
   * @return string|null
   */
  private static function getMembershipPeriodInterval(array $membershipDetails) {
    switch ($membershipDetails['membership_type_id.duration_unit']) {
      case 'month':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] . 'M';
        break;

      case 'day':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] . 'D';
        break;

      case 'year':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] . 'Y';
        break;

      default:
        $interval = NULL;
        break;
    }

    return $interval;
  }

}
