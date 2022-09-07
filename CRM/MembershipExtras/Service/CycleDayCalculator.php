<?php

class CRM_MembershipExtras_Service_CycleDayCalculator {

  /**
   * Calculates the cycle date for
   * the recurring contribution given the
   * date (or start date).
   *
   * hence that we are currently only support cycle
   * day for monthly payment plans, so for everything
   * else we return 1 which is the default value for
   * this field in CiviCRM.
   *
   * @param string $targetDate
   *   DateTime acceptable format
   *
   * @param string $frequencyUnit
   *   CiviCRM acceptable unit (week, month, year ..etc)
   *
   * @return int
   */
  public static function calculate($targetDate, $frequencyUnit) {
    if ($frequencyUnit == 'month') {
      $recurContStartDate = new DateTime($targetDate);
      $day = $recurContStartDate->format('j');
      return min($day, 28);
    }

    return 1;
  }

}
