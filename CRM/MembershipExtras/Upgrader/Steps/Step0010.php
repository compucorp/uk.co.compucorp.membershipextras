<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0010 {

  /**
   * Creates membershipextras_payment_scheme table
   *
   * @return void
   */
  public function apply() {
    $base = CRM_MembershipExtras_Upgrader_Base::instance();
    $base->executeSqlFile('sql/upgrade/Step0010.sql');

    CRM_Utils_System::flushCache();
  }

}
