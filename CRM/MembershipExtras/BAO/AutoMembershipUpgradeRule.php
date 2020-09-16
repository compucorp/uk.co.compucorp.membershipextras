<?php

class CRM_MembershipExtras_BAO_AutoMembershipUpgradeRule extends CRM_MembershipExtras_DAO_AutoMembershipUpgradeRule {

  /**
   * Create a new AutoMembershipUpgradeRule based on array-data
   *
   * @param array $params key-value pairs
   * @return CRM_MembershipExtras_DAO_AutoMembershipUpgradeRule|NULL
   *
  public static function create($params) {
    $className = 'CRM_MembershipExtras_DAO_AutoMembershipUpgradeRule';
    $entityName = 'AutoMembershipUpgradeRule';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  } */

}
