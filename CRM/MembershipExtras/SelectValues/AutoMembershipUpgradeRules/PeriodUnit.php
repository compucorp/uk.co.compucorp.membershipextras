<?php

class CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit implements CRM_MembershipExtras_SelectValues_SelectInterface {

  const YEARS = 1;

  const MONTHS = 2;

  const DAYS = 3;

  public static function getAll() {
    return [
      self::YEARS => 'Year(s)',
      self::MONTHS => 'Month(s)',
      self::DAYS => 'Day(s)',
    ];
  }

}
