<?php

class CRM_MyExtension_Upgrader_Steps_Step0007 {

  public function apply() {
    $disable = new CRM_MembershipExtras_Setup_Configure_DisableContributionCancelActionsExtension();
    $disable->apply();
  }

}
