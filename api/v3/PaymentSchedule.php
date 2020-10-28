<?php

use CRM_MembershipExtras_ExtensionUtil as E;

function _civicrm_api3_payment_schedule_get_spec(&$spec) {
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
    'title' => 'Schedule (monthly, quarterly, annually)',
    'type' => CRM_Utils_Type::T_STRING,
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
 * PaymentSchedule.get API
 *
 * @param array $params
 * @return array API result descriptor
 * @throws API_Exception
 * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentAmount|CiviCRM_API3_Exception
 * @throws Exception
 */
function civicrm_api3_payment_schedule_get($params) {
  $joinDate = !empty($params['join_date']) ? new DateTime($params['join_date']) : NULL;
  $startDate = !empty($params['start_date']) ? new DateTime($params['start_date']) : NULL;
  $endDate = !empty($params['end_date']) ? new DateTime($params['end_date']) : NULL;

  $membershipTypeID = $params['membership_type_id'];
  $schedule = $params['schedule'];

  if (!in_array($schedule, [
    CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::MONTHLY,
    CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::QUARTERLY,
    CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::ANNUALLY,
  ])) {
    throw new API_Exception('Schedule is not of monthly, quarterly or annually');
  }

  $membershipType = CRM_Member_BAO_MembershipType::findById($membershipTypeID);

  $membershipInstalmentsSchedule = new CRM_MembershipExtras_Service_MembershipInstalmentsSchedule(
    [$membershipType],
    $schedule
  );

  $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
  $membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
    $membershipType->id, $startDate, $endDate, $joinDate);

  $membershipTypeDates = array_map(function ($date) {
    return new DateTime($date);
  }, $membershipTypeDates);

  $instalments = $membershipInstalmentsSchedule->generate(
    $membershipTypeDates['start_date'],
    $membershipTypeDates['end_date'],
    $membershipTypeDates['join_date']
  );

  $pendingStatusLabel = civicrm_api3('OptionValue', 'get', [
    'sequential' => 1,
    'option_group_id' => "contribution_status",
    'name' => "pending",
  ])['values'][0]['label'];

  $currencySymbol = CRM_Core_BAO_Country::defaultCurrencySymbol();

  $formattedInstalments = [];
  foreach ($instalments as $key => $instalment) {
    $instalmentDate = CRM_Utils_Date::customFormat($instalment->getInstalmentDate()->format('Y-m-d'), NULL);
    $instalmentAmount = $currencySymbol . $instalment->getInstalmentAmount()->getTaxAmount();;
    $instalmentTaxAmount = $currencySymbol . $instalment->getInstalmentAmount()->getAmount();;
    $formattedInstalment['instalment_no'] = $key + 1;
    $formattedInstalment['instalment_date'] = $instalmentDate;
    $formattedInstalment['instalment_tax_amount'] = $instalmentAmount;
    $formattedInstalment['instalment_amount'] = $instalmentTaxAmount;
    $formattedInstalment['instalment_status'] = $pendingStatusLabel;
    array_push($formattedInstalments, $formattedInstalment);
  }

  return civicrm_api3_create_success($formattedInstalments, $params);
}
