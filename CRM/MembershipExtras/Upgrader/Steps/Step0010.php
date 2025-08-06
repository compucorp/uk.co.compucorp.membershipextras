<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0010 {

  /**
   * Creates membershipextras_payment_scheme table
   *
   * @return void
   */
  public function apply() {
    $base = new CRM_MembershipExtras_Upgrader();
    $base->init(['key' => CRM_MembershipExtras_ExtensionUtil::LONG_NAME]);
    $base->executeSqlFile('sql/upgrade/Step0010.sql');

    CRM_Utils_System::flushCache();
  }

}
