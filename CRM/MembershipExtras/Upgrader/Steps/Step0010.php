<?php

use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_Upgrader_Steps_Step0010 {

  /**
   * Creates membershipextras_payment_scheme table
   *
   * @return void
   */
  public function apply() {
    $base = new CRM_MembershipExtras_Upgrader();
    $base->executeSqlFile(E::path('sql/upgrade/Step0010.sql'));

    CRM_Utils_System::flushCache();
  }

}
