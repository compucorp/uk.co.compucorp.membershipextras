<?php
use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * AutoMembershipUpgradeRule.create API specification
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_auto_membership_upgrade_rule_create_spec(&$spec) {
  $spec['label']['api.required'] = 1;
  $spec['from_membership_type_id']['api.required'] = 1;
  $spec['to_membership_type_id']['api.required'] = 1;
}

/**
 * AutoMembershipUpgradeRule.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_auto_membership_upgrade_rule_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * AutoMembershipUpgradeRule.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_auto_membership_upgrade_rule_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * AutoMembershipUpgradeRule.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_auto_membership_upgrade_rule_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}
