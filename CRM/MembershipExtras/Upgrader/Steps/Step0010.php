<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0010 {

  public function apply() {
    CRM_MembershipExtras_Upgrader_Base::instance()->executeSqlFile('sql/membershipratetable_install.sql');

    return TRUE;
  }

}
