<?php

use CRM_MembershipExtras_Service_UpfrontInstalments_AbstractUpfrontInstalmentsCreator as AbstractUpfrontInstalmentsCreator;

class CRM_MembershipExtras_Service_UpfrontInstalments_PaymentSchemeUpfrontInstalmentsCreator extends AbstractUpfrontInstalmentsCreator {

  /**
   * @var array
   */
  private $paymentPlanSchedule;

  public function __construct($currentRecurContributionId, $paymentPlanSchedule) {
    parent::__construct($currentRecurContributionId);

    $this->paymentPlanSchedule = $paymentPlanSchedule;
  }

  protected function calculateReceiveDate($contributionNumber) {
    $instalmentIndex = $contributionNumber - 1;
    return $this->paymentPlanSchedule['instalments'][$instalmentIndex]['charge_date'];
  }

}
