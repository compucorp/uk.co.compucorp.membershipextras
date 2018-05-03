<?php

class CRM_MembershipExtras_Service_CycleDayCalculator {

  /**
   * Calculates the cycle date for
   * the recurring contribution given the
   * date (or start date) and the frequency unit.
   *
   * @param string $targetDate
   *   DateTime acceptable format
   *
   * @param string $frequencyUnit
   *   CiviCRM acceptable unit (week, month, year ..etc)
   *
   * @return int
   */
  public static function calculate($targetDate, $frequencyUnit) {;
    $recurContStartDate = new DateTime($targetDate);

    switch ($frequencyUnit) {
      case 'week':
        $cycleDay =  $recurContStartDate->format('N');
        break;
      case 'month':
        $cycleDay =  $recurContStartDate->format('j');
        break;
      case 'year':
        $cycleDay =  $recurContStartDate->format('z');
        break;
      default:
        $cycleDay = 1;
    }

    return $cycleDay;
  }

}
