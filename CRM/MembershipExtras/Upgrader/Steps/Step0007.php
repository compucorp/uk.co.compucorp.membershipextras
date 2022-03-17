<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0007 {

  public function apply() {
    $disable = new CRM_MembershipExtras_Setup_Configure_DisableContributionCancelActionsExtension();
    $disable->apply();
  }

}
