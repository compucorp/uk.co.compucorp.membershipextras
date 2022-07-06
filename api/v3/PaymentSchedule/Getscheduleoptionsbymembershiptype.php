<?php

function _civicrm_api3_payment_schedule_getscheduleoptionsbymembershiptype_spec(&$spec) {
  $spec['membership_type_id'] = [
    'name' => 'membership_type_id',
    'title' => 'Membership Type ID',
    'description' => 'Membership Type ID',
    'type' => CRM_Utils_Type::T_INT,
    'FKClassName' => 'CRM_Member_DAO_MembershipType',
    'FKApiName' => 'MembershipType',
    'api.required' => 1,
  ];
}

/**
 * PaymentSchedule.getScheduleOptionsByMembershipType API
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @throws API_Exception
 */
function civicrm_api3_payment_schedule_getscheduleoptionsbymembershiptype($params) {
  $membershipTypePaymentSchedule = new CRM_MembershipExtras_API_PaymentSchedule_MembershipType($params);
  $options = $membershipTypePaymentSchedule->getPaymentScheduleOptions();

  return civicrm_api3_create_success($options, $params);
}
