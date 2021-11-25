<?php

use CRM_MembershipExtras_ExtensionUtil as E;

function _civicrm_api3_payment_schedule_getscheduleoptionsbypricefieldvalues_spec(&$spec) {
  $spec['price_field_values'] = [
    'name' => 'price_field_value',
    'title' => 'Price Field Values',
    'description' => 'Price Field Values for generating schedule options',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 1,
  ];
}

/**
 * PaymentSchedule.getScheduleOptionsByPriceFieldValues
 *
 * @param array $params
 *
 * @return array API result descriptor
 *
 * @throws API_Exception
 */
function civicrm_api3_payment_schedule_getscheduleoptionsbypricefieldvalues($params) {
  $membershipTypePaymentSchedule = new CRM_MembershipExtras_API_PaymentSchedule_PriceValues($params);
  $options = $membershipTypePaymentSchedule->getPaymentScheduleOptions();

  return civicrm_api3_create_success($options, $params);
}
