<?php

/**
 * Class CRM_MembershipExtras_API_PriceValues
 */
class CRM_MembershipExtras_API_PaymentSchedule_PriceValues extends CRM_MembershipExtras_API_PaymentSchedule_Base {

  /**
   * CRM_MembershipExtras_API_PriceValues constructor.
   * @param $params
   *
   * @throws API_Exception
   */
  public function __construct($params) {
    $this->params = $params;
    $this->validateSchedule();
  }

  /**
   * @return array
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   * @throws CiviCRM_API3_Exception
   * @throws Exception
   */
  public function getPaymentSchedule() {
    $priceFieldValues = $this->params['price_field_values']['IN'];

    $priceFieldValueItems = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'id' => ['IN' => array_keys($priceFieldValues)],
    ])['values'];

    $membershipTypes = [];
    $nonMembershipPriceFieldValues = [];

    foreach ($priceFieldValueItems as $priceFieldValueItem) {
      if (empty($priceFieldValueItem['membership_type_id'])) {
        $priceFieldValueItemId = $priceFieldValueItem['id'];
        $nonMembershipPriceFieldValues[$priceFieldValueItemId]['values'] = $priceFieldValueItem;
        $nonMembershipPriceFieldValues[$priceFieldValueItemId]['quantity'] = $priceFieldValues[$priceFieldValueItemId];
        continue;
      }

      $membershipType = CRM_Member_BAO_MembershipType::findById($priceFieldValueItem['membership_type_id']);

      $membershipType->minimum_fee = $priceFieldValueItem['amount'];
      $membershipType->financial_type_id = $priceFieldValueItem['financial_type_id'];
      $membershipTypes[] = $membershipType;
    }

    if (empty($membershipTypes)) {
      new API_Exception('At least one price field items must be of type membership');
    }

    return $this->getInstalments($membershipTypes, $nonMembershipPriceFieldValues);
  }

}
