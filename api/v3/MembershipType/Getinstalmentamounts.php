<?php

/**
 * MembershipType.GetInstalmentAmounts specification
 *
 * @param array $spec
 *
 * @return void
 */
function _civicrm_api3_membership_type_getinstalmentamounts_spec(&$spec) {
  $spec['membership_type_id'] = [
    'name' => 'membership_type_id',
    'title' => 'Membership Type ID',
    'description' => 'Membership Type ID for the calculation',
    'type' => CRM_Utils_Type::T_INT,
    'FKClassName' => 'CRM_Member_DAO_MembershipType',
    'FKApiName' => 'MembershipType',
    'api.required' => 1,
  ];
  $spec['join_date'] = [
    'name' => 'join_date',
    'title' => 'Member Since Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0
  ];
  $spec['start_date'] = [
    'name' => 'start_date',
    'title' => 'Membership Start Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0
  ];
  $spec['end_date'] = [
    'name' => 'end_date',
    'title' => 'Membership End Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0
  ];
}

/**
 * MembershipType.GetInstalmentAmounts API
 * Returns the First Instalment and Following Instalment amounts for a
 * membership type.
 *
 * @param array $params
 *
 * @return array API result descriptor
 */
function civicrm_api3_membership_type_getinstalmentamounts($params) {
  $startDate = !empty($params['start_date']) ? new DateTime($params['start_date']) : NULL;
  $endDate = !empty($params['end_date']) ? new DateTime($params['end_date']) : NULL;
  $joinDate = !empty($params['join_date']) ? new DateTime($params['join_date']) : NULL;
  $membershipTypeID = $params['membership_type_id'];

  $membershipType = CRM_Member_BAO_MembershipType::findById($membershipTypeID);
  $membershipTypeTaxAmount = new CRM_MembershipExtras_Service_MembershipTypeTaxAmount();
  $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();

  $membershipTypeInstalment = new CRM_MembershipExtras_Service_MembershipTypeInstalmentAmount(
    [$membershipType],
    $membershipTypeTaxAmount,
    $membershipTypeDatesCalculator
  );

  $results = [
    'fi_amount' => $membershipTypeInstalment->calculateFirstInstalmentAmount($startDate, $endDate, $joinDate),
    'foi_amount' => $membershipTypeInstalment->calculateFollowingInstalmentAmount()
  ];

  return civicrm_api3_create_success($results, $params);
}
