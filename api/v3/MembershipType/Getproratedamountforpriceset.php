<?php

/**
 * MembershipType.Getproratedamountforpriceset specification
 *
 * @param array $spec
 *
 * @return void
 */
function _civicrm_api3_membership_type_getproratedamountforpriceset_spec(&$spec) {
  $spec['price_set_id'] = [
    'name' => 'price_set_id',
    'title' => 'Price Set ID',
    'description' => 'Price Set ID for the calculation',
    'type' => CRM_Utils_Type::T_INT,
    'FKClassName' => 'CRM_Price_DAO_PriceSet',
    'FKApiName' => 'PriceSet',
    'api.required' => 1,
  ];
  $spec['join_date'] = [
    'name' => 'join_date',
    'title' => 'Membership Since Date',
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
 * MembershipType.Getproratedamountforpriceset API
 * Returns the prorated amount for the field values in the Price set.
 * If a price field value item in the price set is not of type membership
 * it is not prorated and hence not returned.
 *
 * @param array $params
 *
 * @return array API result descriptor
 */
function civicrm_api3_membership_type_getproratedamountforpriceset($params) {
  $startDate = !empty($params['start_date']) ? new DateTime($params['start_date']) : NULL;
  $endDate = !empty($params['end_date']) ? new DateTime($params['end_date']) : NULL;
  $joinDate = !empty($params['end_date']) ? new DateTime($params['join_date']) : NULL;
  $priceSetId = $params['price_set_id'];
  $isFixedMembershipOnly = !empty($params['is_fixed_membership']);
  $priceFieldValues = _civicrm_api3_membership_type_getPriceFieldValues($priceSetId);
  $results = [];

  foreach ($priceFieldValues as $priceFieldValue) {
    if (empty($priceFieldValue['membership_type_id'])) {
      continue;
    }

    $membershipType = CRM_Member_BAO_MembershipType::findById($priceFieldValue['membership_type_id']);

    if ($isFixedMembershipOnly && $membershipType->period_type == 'rolling') {
      continue;
    }

    $membershipType->minimum_fee = $priceFieldValue['amount'];
    $membershipType->financial_type_id = $priceFieldValue['financial_type_id'];
    $proRata = _civicrm_api3_membership_type_geProRata($membershipType, $startDate, $endDate, $joinDate);

    $results[$priceFieldValue['id']] = [
      'price_field_id' => $priceFieldValue['price_field_id'],
      'price_value_id' => $priceFieldValue['id'],
      'pro_rated_amount' => $proRata,
      'financial_type_id' => $priceFieldValue['financial_type_id']
    ];
  }

  return civicrm_api3_create_success($results, $params);
}

/**
 * Returns the field values for the Price Set ID.
 *
 * @param int $priceSetId
 *
 * @return array
 */
function _civicrm_api3_membership_type_getPriceFieldValues($priceSetId) {
  $result = civicrm_api3('PriceSet', 'getsingle', [
    'id' => $priceSetId,
    'api.PriceField.get' => ['price_set_id' => "\$value.id"],
  ]);

  if ($result['api.PriceField.get']['count'] == 0) {
    return [];
  }

  $priceFields = [];
  foreach ($result['api.PriceField.get']['values'] as $priceField) {
    $priceFields[] = $priceField['id'];
  }

  $result = civicrm_api3('PriceFieldValue', 'get', [
    'sequential' => 1,
    'price_field_id' => ['IN' => $priceFields],
  ]);

  if ($result['count'] == 0) {
    return [];
  }

  return $result['values'];
}

/**
 * Calculates the Pro rated amount for the membership type.
 *
 * @param CRM_Member_BAO_MembershipType $membershipType
 * @param \DateTime|NULL $startDate
 * @param \DateTime|NULL $endDate
 * @param \DateTime|NULL $joinDate
 *
 * @return float
 */
function _civicrm_api3_membership_type_geProRata($membershipType, DateTime $startDate = NULL, DateTime $endDate = NULL, $joinDate = NULL) {
  $membershipTypeDates = new CRM_MembershipExtras_Service_MembershipTypeDates();
  $membershipTypeDuration = new CRM_MembershipExtras_Service_MembershipTypeDuration($membershipType, $membershipTypeDates);
  $membershipTypeTaxAmount = new CRM_MembershipExtras_Service_MembershipTypeTaxAmount();
  $membershipTypeAmount = new CRM_MembershipExtras_Service_MembershipTypeAmount($membershipTypeDuration, $membershipTypeTaxAmount);

  return $membershipTypeAmount->calculateProRata($membershipType, $startDate, $endDate, $joinDate);
}
