<?php

use CRM_MembershipExtras_Test_Fabricator_Base as BaseFabricator;
use CRM_Member_BAO_MembershipType as MembershipType;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_MembershipType.
 */
class CRM_MembershipExtras_Test_Fabricator_MembershipType extends BaseFabricator {

  /**
 * Default parameters that will be used to fabricate a membership type.
 *
 * @var array
 */
  protected static $defaultParams = [
    'duration_unit' => 'year',
    'period_type' => 'fixed',
    'duration_interval' => 1,
    'fixed_period_start_day' => 101,
    'fixed_period_rollover_day' => 1231,
    'domain_id' => 1,
    'member_of_contact_id' => 1,
    'financial_type_id' => 1,
  ];

  /**
   * Fabricates a membership type.
   *
   * @param array $params
   * @param bool $saveObject
   *
   * @return \CRM_Member_BAO_MembershipType
   */
  public static function fabricate($params = [], $saveObject = FALSE) {
    $params = array_merge(static::$defaultParams, $params);
    $membershipType = new MembershipType();

    foreach ($params as $property => $value) {
      $membershipType->$property = $value;
    }

    if ($saveObject) {
      return $membershipType->save();
    }

    return $membershipType;
  }

}
