<?php

/**
 * MembershipType.GetDatesForMembershipType API specification
 *
 * @param array $spec
 *
 * @return void
 */
function _civicrm_api3_membership_type_getdatesFormembershiptype_spec(&$spec) {
  $spec['membership_type_id'] = [
    'name' => 'membership_type_id',
    'title' => 'Membership Type ID',
    'description' => 'Membership Type ID for the calculation',
    'type' => CRM_Utils_Type::T_INT,
    'FKClassName' => 'CRM_Member_DAO_MembershipType',
    'FKApiName' => 'MembershipType',
    'api.required' => 1,
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

  $spec['join_date'] = [
    'name' => 'join_date',
    'title' => 'Member Since Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0
  ];
}

/**
 * MembershipType.GetDatesForMembershipType API
 *
 * Returns the membership type dates for a membership type.
 *
 * @param array $params
 */
function civicrm_api3_membership_type_getdatesformembershipType($params) {
  $startDate = !empty($params['start_date']) ? new DateTime($params['start_date']) : NULL;
  $endDate = !empty($params['end_date']) ? new DateTime($params['end_date']) : NULL;
  $joinDate = !empty($params['join_date']) ? new DateTime($params['join_date']) : NULL;
  $membershipTypeID = $params['membership_type_id'];
  $membershipType = CRM_Member_BAO_MembershipType::findById($membershipTypeID);

  if ($membershipType->period_type == 'rolling') {
    throw new API_Exception('Membership Period Type is not of type Fixed');
  }

  $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
  $membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType($membershipType, $startDate, $endDate, $joinDate);
  $membershipTypeDates = array_map('_civicrm_api3_membership_type_process_dates', $membershipTypeDates);

  return civicrm_api3_create_success($membershipTypeDates, $params);
}

/**
 * Formats the date in the Y-m-d format
 *
 * @param string $date
 */
function _civicrm_api3_membership_type_process_dates($date) {
  return date('Y-m-d', strtotime($date));
}
