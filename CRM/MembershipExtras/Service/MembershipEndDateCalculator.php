<?php

class CRM_MembershipExtras_Service_MembershipEndDateCalculator {

  /**
   * Calculates the membership new end date
   * for renewal.
   *
   * @param int $membershipId
   *
   * @return string
   */
  public static function calculate($membershipId) {
    $membershipDetails = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ['end_date', 'membership_type_id.duration_unit', 'membership_type_id.duration_interval'],
      'id' => $membershipId,
    ])['values'][0];

    $currentEndDate = new DateTime($membershipDetails['end_date']);
    switch ($membershipDetails['membership_type_id.duration_unit']) {
      case 'month':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] . 'M';
        break;

      case 'day':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] .'D';
        break;

      case 'year':
        $interval = 'P' . $membershipDetails['membership_type_id.duration_interval'] .'Y';
        break;

      default:
        $interval = NULL;
        break;
    }

    $newEndDate = 'null';
    if (!empty($interval)) {
      $currentEndDate->add(new DateInterval($interval));
      $newEndDate = $currentEndDate->format('Ymd');
    }

    return $newEndDate;
  }

}
