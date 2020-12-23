<?php

/**
 * Class CRM_MembershipExtras_API_MembershipType
 */
class CRM_MembershipExtras_API_PaymentSchedule_MembershipType extends CRM_MembershipExtras_API_PaymentSchedule_Base {

  /**
   * CRM_MembershipExtras_API_MembershipType constructor.
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
   */
  public function getPaymentSchedule() {
    $membershipTypeID = $this->params['membership_type_id'];
    $membershipType = CRM_Member_BAO_MembershipType::findById($membershipTypeID);

    return $this->getInstalments([$membershipType]);
  }

}
