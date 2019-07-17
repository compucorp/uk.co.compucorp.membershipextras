<?php

/**
 * MembershipType.Getproratedamount specification
 *
 * @param array $spec
 *
 * @return void
 */
function _civicrm_api3_membership_type_getproratedamount_spec(&$spec) {
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

  $spec['is_fixed_membership'] = [
    'name' => 'is_fixed_membership',
    'title' => 'Calculate For Only Fixed Membership Types?',
    'description' => 'Calculate For Only Fixed Membership Types',
    'type' => CRM_Utils_Type::T_BOOLEAN,
    'api.required' => 0,
  ];
}

/**
 * MembershipType.Getproratedamount API
 * Returns the prorated amount for the membership Type Id
 *
 * @param array $params
 *
 * @return array API result descriptor
 */
function civicrm_api3_membership_type_getproratedamount($params) {
  $startDate = !empty($params['start_date']) ? new DateTime($params['start_date']) : NULL;
  $endDate = !empty($params['end_date']) ? new DateTime($params['end_date']) : NULL;
  $joinDate = !empty($params['join_date']) ? new DateTime($params['join_date']) : NULL;
  $membershipTypeID = $params['membership_type_id'];
  $isFixedMembershipOnly = !empty($params['is_fixed_membership']);

  $membershipType = CRM_Member_BAO_MembershipType::findById($membershipTypeID);

  if ($isFixedMembershipOnly && $membershipType->period_type == 'rolling') {
    throw new API_Exception('Membership Period Type is not of type Fixed');
  }

  $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
  $membershipTypeDurationCalculator = new CRM_MembershipExtras_Service_MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
  $membershipTypeTaxAmountCalculator = new CRM_MembershipExtras_Service_MembershipTypeTaxAmountCalculator();
  $membershipTypeAmountProrater = new CRM_MembershipExtras_Service_MembershipTypeAmountProrater($membershipTypeDurationCalculator, $membershipTypeTaxAmountCalculator);
  $proRata = $membershipTypeAmountProrater->calculateProRata($membershipType, $startDate, $endDate, $joinDate);

  $results = [
    'membership_type_id' => $membershipTypeID,
    'pro_rated_amount' => $proRata
  ];
  $extraParams = ['duration_in_days' => $membershipTypeDurationCalculator->calculateDaysBasedOnDates($startDate, $endDate, $joinDate)];

  return civicrm_api3_create_success($results, $params, null, 'create', $membershipType, $extraParams);
}
