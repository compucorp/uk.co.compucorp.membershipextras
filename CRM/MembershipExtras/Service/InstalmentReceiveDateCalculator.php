<?php

class CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator {

  /**
   * The recurring contribution of
   * the instalment that we want
   * to calculate its receive date.
   *
   * @var array
   */
  private $recurContribution;

  /**
   * Initial date from which other dates are calculated.
   *
   * @var string
   */
  private $startDate;

  /**
   * CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator constructor.
   *
   * @param array $recurContribution
   */
  public function __construct($recurContribution = []) {
    if (!empty($recurContribution)) {
      $this->recurContribution = $recurContribution;
      $this->startDate = $this->recurContribution['start_date'];
    }
  }

  /**
   * Sets initial date from which subsequent dates are calculated.
   *
   * @param $date
   */
  public function setStartDate($date) {
    $this->startDate = $date;
  }

  /**
   * Calculates the receive date for the instalment
   * contribution to be created based on the instalment
   * contribution number.
   *
   * The linked recurring contribution details is the base
   * for this calculation.
   *
   * @param int $contributionNumber
   *   The number of the instalment contribution.
   *
   * @return string
   */
  public function calculate($contributionNumber = 1) {
    $intervalFrequency = $this->recurContribution['frequency_interval'];
    $frequencyUnit = $this->recurContribution['frequency_unit'];

    $receiveDate = new DateTime($this->startDate);
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
   * we want it to be 2018-03-01 instead.
   *
   * This method ensure that adding months
   * to a date get calculated properly.
   *
   *
   * @param DateTime $startDate
   * @param int $numberOfMonthsToAdd
   *
   * @return DateTime
   */
  public function getSameDayNextMonth(DateTime $startDate, $numberOfMonthsToAdd = 1) {
    $intervalSpec = "P{$numberOfMonthsToAdd}M";
    if (in_array($startDate->format('j'), [29, 30, 31])) {
      $startDate->modify('first day of next month');
    }
    $startDate->add(new DateInterval($intervalSpec));

    return $startDate;
  }

}
