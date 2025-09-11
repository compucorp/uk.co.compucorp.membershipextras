<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0013 {

  public function apply() {
    $removalSteps = [
      new CRM_MembershipExtras_Setup_Manage_OfflineAutoRenewalScheduledJob(),
    ];
    foreach ($removalSteps as $step) {
      $step->remove();
    }
  }

}
