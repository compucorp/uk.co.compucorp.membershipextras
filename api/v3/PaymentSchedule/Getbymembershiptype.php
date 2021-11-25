<?php

function _civicrm_api3_payment_schedule_getbymembershiptype_spec(&$spec) {
  $spec['membership_type_id'] = [
    'name' => 'membership_type_id',
    'title' => 'Membership Type ID',
    'description' => 'Membership Type ID',
    'type' => CRM_Utils_Type::T_INT,
    'FKClassName' => 'CRM_Member_DAO_MembershipType',
    'FKApiName' => 'MembershipType',
    'api.required' => 1,
  ];

  $spec['schedule'] = [
    'name' => 'schedule',
    'title' => 'Schedule (monthly, quarterly, annual)',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];

  $spec['payment_method'] = [
    'name' => 'payment_method',
    'title' => 'Payment method',
    'type' => CRM_Utils_Type::T_INT,
    'api.required' => 1,
  ];

  $spec['start_date'] = [
    'name' => 'start_date',
    'title' => 'Membership Start Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0,
  ];

  $spec['end_date'] = [
    'name' => 'end_date',
    'title' => 'Membership End Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0,
  ];

  $spec['join_date'] = [
    'name' => 'join_date',
    'title' => 'Member Since Date',
    'type' => CRM_Utils_Type::T_DATE,
    'api.required' => 0,
  ];
}

/**
 * PaymentSchedule.getByMembershipType API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws API_Exception
 * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentAmount|CiviCRM_API3_Exception
 * @throws Exception
 */
function civicrm_api3_payment_schedule_getbymembershiptype($params) {
  $membershipTypeSchedule = new CRM_MembershipExtras_API_PaymentSchedule_MembershipType($params);
  $schedule = $membershipTypeSchedule->getPaymentSchedule();
  $membershipTypeSchedule->formatInstalments($schedule['instalments']);

  return civicrm_api3_create_success($schedule, $params);
}
