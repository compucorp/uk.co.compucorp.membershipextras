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

function _civicrm_api3_contribution_recur_line_item_calculatetaxamount_spec(&$spec) {
  $spec['amount_exc_tax'] = [
    'title' => ts('Amount Exc Tax'),
    'api.required' => TRUE,
    'type' => CRM_Utils_Type::T_INT,
  ];

  $spec['financial_type_id'] = [
    'title' => ts('Financial Type Id'),
    'api.required' => TRUE,
    'type' => CRM_Utils_Type::T_INT,
  ];
}

/**
 * ContributionRecurLineItem.calculateTaxAmount API
 *
 * Calculates the tax amount for any given amount if the
 * financial type is given.
 */
function civicrm_api3_contribution_recur_line_item_calculatetaxamount($params) {
  $amountExcTax = $params['amount_exc_tax'];
  $financialTypeId = $params['financial_type_id'];

  $taxAmount = 0;
  $taxRates = CRM_Core_PseudoConstant::getTaxRates();
  if (!empty($taxRates[$financialTypeId])) {
    $taxRate = $taxRates[$financialTypeId];
    $taxAmount = CRM_Contribute_BAO_Contribution_Utils::calculateTaxAmount($amountExcTax, $taxRate);
    $taxAmount = CRM_MembershipExtras_Service_MoneyUtilities::roundToPrecision($taxAmount['tax_amount'], 2);
  }

  $totalAmount = $amountExcTax + $taxAmount;
  $totalAmount = CRM_MembershipExtras_Service_MoneyUtilities::roundToPrecision($totalAmount, 2);

  return ['total_amount' => $totalAmount, 'tax_amount' => $taxAmount];
}

function _civicrm_api3_contribution_recur_line_item_calcmembershipprorata_spec(&$spec) {
  $spec['recur_contribution_id'] = [
    'title' => ts('Recurring Contribution Id'),
    'api.required' => TRUE,
    'type' => CRM_Utils_Type::T_INT,
  ];

  $spec['membership_type_id'] = [
    'title' => ts('Membership Type Id'),
    'api.required' => TRUE,
    'type' => CRM_Utils_Type::T_INT,
  ];

  $spec['from_date'] = [
    'title' => ts('From Date'),
    'api.required' => TRUE,
    'type' => CRM_Utils_Type::T_DATE,
  ];

  $spec['to_date'] = [
    'title' => ts('To Date'),
    'api.required' => FALSE,
    'type' => CRM_Utils_Type::T_DATE,
  ];

  $spec['financial_type_id'] = [
    'title' => ts('Financial Type Id'),
    'api.required' => FALSE,
    'type' => CRM_Utils_Type::T_INT,
  ];
}

/**
 * @param $params
 * @return array
 */
function civicrm_api3_contribution_recur_line_item_calcmembershipprorata($params) {
  $params['to_date'] = CRM_Utils_Array::value('to_date', $params);
  $params['financial_type_id'] = CRM_Utils_Array::value('financial_type_id', $params);
  return CRM_MembershipExtras_Service_MembershipLineProRataCalculator::calculateAmounts($params['recur_contribution_id'], $params['membership_type_id'], $params['from_date'], $params['to_date'], $params['financial_type_id']);
}
