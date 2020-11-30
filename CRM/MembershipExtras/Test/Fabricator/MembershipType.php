<?php

use CRM_MembershipExtras_Test_Fabricator_Base as BaseFabricator;
use CRM_Member_BAO_MembershipType as MembershipType;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_MembershipType.
 */
class CRM_MembershipExtras_Test_Fabricator_MembershipType extends BaseFabricator {

  /**
   * Entity's name.
   *
   * @var string
   */
  protected static $entityName = 'MembershipType';

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
    'financial_type_id' => 'Member Dues',
  ];

  /**
   * Fabricates and returns the membership
   * type entity using the BAO class
   * instead of using API.
   *
   * @param array $params
   * @param bool $saveObject
   *
   * @return \CRM_Member_BAO_MembershipType
   */
  public static function fabricateWithBAO($params = [], $saveObject = FALSE) {
    $params = array_merge(static::$defaultParams, $params);

    if (empty($params['name'])) {
      $params['name'] = md5(mt_rand());
    }

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
