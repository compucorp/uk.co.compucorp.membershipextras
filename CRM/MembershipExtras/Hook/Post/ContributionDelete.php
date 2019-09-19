<?php
use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

/**
 * Implements post-process hooks on ContributionRecur entity.
 */
class CRM_MembershipExtras_Hook_Post_ContributionDelete {

  /**
   * @var int
   */
  private $contributionId;


  public function __construct($contributionBAO) {
    $this->contributionId = $contributionBAO->id;
  }

  public function process() {
    CRM_MembershipExtras_BAO_MembershipPeriod::unlinkPaymentEntity($this->contributionId, 'civicrm_contribution');
  }

}
