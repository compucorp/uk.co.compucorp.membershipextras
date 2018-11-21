<?php
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_BAO_MembershipPeriod extends CRM_MembershipExtras_DAO_MembershipPeriod {

  /**
   * Create a new MembershipPeriod based on array-data
   *
   * @param array $params key-value pairs
   *
   * @return CRM_MembershipExtras_DAO_MembershipPeriod|NULL
   */
  public static function create($params) {
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, 'MembershipPeriod', CRM_Utils_Array::value('id', $params), $params);
    $instance = new CRM_MembershipExtras_DAO_MembershipPeriod();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, 'MembershipPeriod', $instance->id, $instance);

    return $instance;
  }

}
