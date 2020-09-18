<?php

class CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType implements CRM_MembershipExtras_SelectValues_SelectInterface {

  const MEMBER_SINCE = 1;

  const MEMBER_START = 2;

  public static function getAll() {
    return [
      self::MEMBER_SINCE  => 'Member Since',
      self::MEMBER_START  => 'Member Start',
    ];
  }

}
