<?php

use CRM_Member_BAO_MembershipType as MembershipType;

class CRM_MembershipExtras_Test_Fabricator_MembershipType {

  public static function fabricate($params = []) {
    $params = array_merge(static::getDefaultParams(), $params);

    $membershipType = new MembershipType();

    foreach ($params as $property => $value) {
      $membershipType->$property = $value;
    }

    return $membershipType;
  }

  private static function getDefaultParams() {
    return [
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'duration_interval' => 1,
    ];
  }
}
