<?php

class CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator {

  /**
   * The recurring contribution of
   * the installment that we want
   * to calculate its receive date.
   *
   * @var array
   */
  private $recurContribution;

  public function __construct($recurContribution) {
    $this->recurContribution = $recurContribution;
  }

  /**
   * Calculates the receive date for the installment
   * contribution to be created based on the installment
   * contribution number.
   *
   * The linked recurring contribution details is the base
   * for this calculation.
   *
   * @param int
   *   The number of the installment contribution.
   *
   * @return string
   */
  public function calculate($contributionNumber = 1) {
    $firstDate = $this->recurContribution['start_date'];
    $intervalFrequency = $this->recurContribution['frequency_interval'];
    $frequencyUnit = $this->recurContribution['frequency_unit'];

    $receiveDate = new DateTime($firstDate);
    $numberOfIntervals = ($contributionNumber - 1) * $intervalFrequency;

    switch ($frequencyUnit) {
      case 'day':
        $interval = "P{$numberOfIntervals}D";
        $receiveDate->add(new DateInterval($interval));
        break;

      case 'week':
        $interval = "P{$numberOfIntervals}W";
        $receiveDate->add(new DateInterval($interval));
        break;

      case 'month':
        $receiveDate = $this->getSameDayNextMonth($receiveDate, $numberOfIntervals);
        break;

      case 'year':
        $interval = "P{$numberOfIntervals}Y";
        $receiveDate->add(new DateInterval($interval));
        break;
    }

    return $receiveDate->format('Y-m-d');
  }

  /**
   * Adding month in PHP using DateTime
   * class will render the date in a way that
   * we do not desire, for example adding one month
   * to 2018-01-31 will result in 2018-03-03 but
   * we want it to be 2018-02-28 instead.
   *
   * This method ensure that adding months
   * to a date get caculated properly.
   *
   *
   * @param DateTime $startDate
   * @param int $numberOfMonthsToAdd
   *
   * @return DateTime
   */
  private function getSameDayNextMonth(DateTime $startDate, $numberOfMonthsToAdd = 1) {
    $startDateDay = (int) $startDate->format('j');
    $startDateMonth = (int) $startDate->format('n');
    $startDateYear = (int) $startDate->format('Y');

    $numberOfYearsToAdd = floor(($startDateMonth + $numberOfMonthsToAdd) / 12);
    if ((($startDateMonth + $numberOfMonthsToAdd) % 12) === 0) {
      $numberOfYearsToAdd--;
    }
    $year = $startDateYear + $numberOfYearsToAdd;

    $month = ($startDateMonth + $numberOfMonthsToAdd) % 12;
    if ($month === 0) {
      $month = 12;
    }
    $month = sprintf('%02s', $month);

    $numberOfDaysInMonth = (new DateTime("$year-$month-01"))->format('t');
    $day = $startDateDay;
    if ($startDateDay > $numberOfDaysInMonth) {
      $day = $numberOfDaysInMonth;
    }
    $day = sprintf('%02s', $day);

    return new DateTime("$year-$month-$day");
  }

}
