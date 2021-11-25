<?php

function _civicrm_api3_payment_schedule_getbypriceset_spec(&$spec) {
  $spec['price_field_values'] = [
    'name' => 'price_field_value',
    'title' => 'Price Field Values',
    'description' => 'Price Field Values for generating instalment',
    'type' => CRM_Utils_Type::T_STRING,
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
 * PaymentSchedule.getByPriceFieldValues API
 *
 * @param array $params
 *
 * @return array API result descriptor
 * @throws API_Exception
 * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentAmount|CiviCRM_API3_Exception
 * @throws Exception
 */
function civicrm_api3_payment_schedule_getbypricefieldvalues($params) {
  if (!array_key_exists('IN', $params['price_field_values'])) {
    throw new API_Exception('The price_field_values parameter only supports the IN operator');
  }
  $priceValuesPaymentSchedule = new CRM_MembershipExtras_API_PaymentSchedule_PriceValues($params);
  $schedule = $priceValuesPaymentSchedule->getPaymentSchedule();
  $priceValuesPaymentSchedule->formatInstalments($schedule['instalments']);

  return civicrm_api3_create_success($schedule, $params);
}
