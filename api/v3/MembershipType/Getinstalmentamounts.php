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
  $spec['start_date'] = [
    'name' => 'start_date',
    'title' => 'Membership Start Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 1
  ];
  $spec['end_date'] = [
    'name' => 'end_date',
    'title' => 'Membership End Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 1
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
  $startDate = new DateTime($params['start_date']);
  $endDate = new DateTime($params['end_date']);
  $membershipTypeID = $params['membership_type_id'];
  $membershipType = CRM_Member_BAO_MembershipType::findById($membershipTypeID);
  $membershipTypeTaxAmount = new CRM_MembershipExtras_Service_MembershipTypeTaxAmount();
  $membershipTypeInstalment = new CRM_MembershipExtras_Service_MembershipTypeInstalmentAmount(
    [$membershipType],
    $membershipTypeTaxAmount,
    $startDate,
    $endDate
  );

  $results = [
    'fi_amount' => $membershipTypeInstalment->calculateFirstInstalmentAmount(),
    'foi_amount' => $membershipTypeInstalment->calculateFollowingInstalmentAmount()
  ];

  return civicrm_api3_create_success($results, $params);
}
