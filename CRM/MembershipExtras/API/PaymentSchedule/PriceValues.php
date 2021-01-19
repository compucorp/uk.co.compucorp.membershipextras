<?php

/**
 * Class CRM_MembershipExtras_API_PriceValues
 */
class CRM_MembershipExtras_API_PaymentSchedule_PriceValues extends CRM_MembershipExtras_API_PaymentSchedule_Base {
  /**
   * @var array
   */
  private $nonMembershipPriceFieldValues;
  /**
   * @var array
   */
  private $membershipTypes;

  /**
   * CRM_MembershipExtras_API_PriceValues constructor.
   * @param $params
   *
   * @throws API_Exception
   */
  public function __construct($params) {
    $this->params = $params;
  }

  /**
   * @return array
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   * @throws CiviCRM_API3_Exception
   * @throws Exception
   */
  public function getPaymentSchedule() {
    $this->validateSchedule();
    $this->processPriceFieldValues();

    return $this->getInstalments($this->membershipTypes, $this->nonMembershipPriceFieldValues);
  }

  /**
   * @return mixed
   */
  public function getPaymentScheduleOptions() {
    $this->processPriceFieldValues();

    return $this->getMembershipTypeScheduleOptions($this->membershipTypes[0]);
  }

  private function processPriceFieldValues() {
    $priceFieldValues = $this->params['price_field_values']['IN'];

    $priceFieldValueItems = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'id' => ['IN' => array_keys($priceFieldValues)],
    ])['values'];

    $this->membershipTypes = [];
    $this->nonMembershipPriceFieldValues = [];

    foreach ($priceFieldValueItems as $priceFieldValueItem) {
      if (empty($priceFieldValueItem['membership_type_id'])) {
        $priceFieldValueItemId = $priceFieldValueItem['id'];
        $this->nonMembershipPriceFieldValues[$priceFieldValueItemId]['values'] = $priceFieldValueItem;
        $this->nonMembershipPriceFieldValues[$priceFieldValueItemId]['quantity'] = $priceFieldValues[$priceFieldValueItemId];
        continue;
      }

      $membershipType = CRM_Member_BAO_MembershipType::findById($priceFieldValueItem['membership_type_id']);

      $membershipType->minimum_fee = $priceFieldValueItem['amount'];
      $membershipType->financial_type_id = $priceFieldValueItem['financial_type_id'];
      $this->membershipTypes[] = $membershipType;
    }

    if (empty($membershipTypes)) {
      new API_Exception(ts('At least one price field items must be of type membership'));
    }
  }

}
