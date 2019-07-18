<?php
use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * MembershipPeriod.create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_membership_period_create_spec(&$spec) {
  $spec['membership_id']['api.required'] = 1;
  $spec['start_date']['api.required'] = 1;
}

/**
 * MembershipPeriod.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_membership_period_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * MembershipPeriod.updateperiod API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_membership_period_updateperiod($params) {
  try {
    $updatedPeriod = CRM_MembershipExtras_BAO_MembershipPeriod::updatePeriod($params);
    return civicrm_api3_create_success($updatedPeriod->toArray());
  }
  catch (CRM_Core_Exception $exception) {
    return civicrm_api3_create_error($exception->getMessage());
  }
}

/**
 * MembershipPeriod.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_membership_period_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * MembershipPeriod.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_membership_period_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}
