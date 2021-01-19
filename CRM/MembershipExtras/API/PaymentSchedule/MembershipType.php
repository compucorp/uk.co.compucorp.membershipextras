<?php

/**
 * Class CRM_MembershipExtras_API_MembershipType
 */
class CRM_MembershipExtras_API_PaymentSchedule_MembershipType extends CRM_MembershipExtras_API_PaymentSchedule_Base {

  /**
   * @var CRM_Core_DAO
   */
  private $membershipType;

  /**
   * CRM_MembershipExtras_API_MembershipType constructor.
   * @param $params
   *
   * @throws API_Exception
   */
  public function __construct($params) {
    $this->params = $params;
    $this->membershipType = CRM_Member_BAO_MembershipType::findById($this->params['membership_type_id']);
  }

  /**
   * @return array
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   * @throws CiviCRM_API3_Exception
   */
  public function getPaymentSchedule() {
    $this->validateSchedule();
    return $this->getInstalments([$this->membershipType]);
  }

  /**
   * @return array
   */
  public function getPaymentScheduleOptions() {
    return $this->getMembershipTypeScheduleOptions($this->membershipType);
  }

}
