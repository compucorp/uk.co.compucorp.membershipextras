<?php
use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * ContributionRecurLineItem.create API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contribution_recur_line_item_create_spec(&$spec) {
  // $spec['some_parameter']['api.required'] = 1;
}

/**
 * Declare metadata for createsubscriptionline api call.
 *
 * @param $spec
 */
function _civicrm_api3_contribution_recur_line_item_createsubscriptionline_spec(&$spec) {
  $spec['contribution_recur_id'] = array (
    'title' => 'Contribution Recur ID',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  );
}

/**
 * ContributionRecurLineItem.create API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_contribution_recur_line_item_create($params) {
  return _civicrm_api3_basic_create(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * ContributionRecurLineItem.createsubscriptionline API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_contribution_recur_line_item_createsubscriptionline($params) {
  $line = new CRM_MembershipExtras_Hook_PostProcess_RecurringContributionLineItemCreator($params['contribution_recur_id']);
  $line->create();
  return civicrm_api3_create_success();
}

/**
 * ContributionRecurLineItem.delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_contribution_recur_line_item_delete($params) {
  return _civicrm_api3_basic_delete(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}

/**
 * ContributionRecurLineItem.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 */
function civicrm_api3_contribution_recur_line_item_get($params) {
  return _civicrm_api3_basic_get(_civicrm_api3_get_BAO(__FUNCTION__), $params);
}
