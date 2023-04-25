<?php

use CRM_MembershipExtras_Service_UpfrontInstalments_AbstractUpfrontInstalmentsCreator as AbstractUpfrontInstalmentsCreator;
use CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator as InstalmentReceiveDateCalculator;

class CRM_MembershipExtras_Service_UpfrontInstalments_StandardUpfrontInstalmentsCreator extends AbstractUpfrontInstalmentsCreator {

  /**
   * @var \CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator
   */
  private $receiveDateCalculator;

  public function __construct($currentRecurContributionId) {
    parent::__construct($currentRecurContributionId);

    $this->receiveDateCalculator = new InstalmentReceiveDateCalculator($this->currentRecurContribution);
  }

  protected function calculateReceiveDate($contributionNumber) {
    return $this->receiveDateCalculator->calculate($contributionNumber);
  }

}
