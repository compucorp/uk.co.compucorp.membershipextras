<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator as InvalidMembershipTypeInstalmentCalculator;
use CRM_MembershipExtras_DTO_ScheduleInstalmentAmount as ScheduleInstalmentAmount;

/**
 * Class CRM_MembershipExtras_Service_MembershipInstalmentsSchedule
 */
class CRM_MembershipExtras_Service_MembershipInstalmentsSchedule {

  const MONTHLY = 'monthly';
  const QUARTERLY = 'quarterly';
  const ANNUALLY = 'annually';

  const MONTHLY_INSTALMENT_COUNT = 12;
  const QUARTERLY_INSTALMENT_COUNT = 4;
  const ANNUALLY_INSTALMENT_COUNT = 1;

  /**
   * @var \CRM_MembershipExtras_Service_MembershipTypeDatesCalculator
   */
  private $membershipTypeDatesCalculator;

  /**
   * @var array
   */
  private $membershipTypes;

  /**
   * @var \CRM_MembershipExtras_Service_MembershipTypeTaxAmountCalculator
   */
  private $membershipTypeTaxAmountCalculator;

  /**
   * @var string
   */
  private $schedule;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeInstalment constructor.
   *
   * @param array $membershipTypes
   * @param string $schedule
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  public function __construct(
    array $membershipTypes,
    string $schedule
  ) {
    $this->membershipTypeTaxAmountCalculator = new CRM_MembershipExtras_Service_MembershipTypeTaxAmountCalculator();
    $this->membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $this->membershipTypes = $membershipTypes;
    $this->schedule = $schedule;
    $this->validateMembershipTypeForInstalment();
  }

  /**
   * Generates instalments for a set of membership types given that the conditions
   * for calculating instalments
   *
   * @param DateTime|null $startDate
   * @param DateTime|null $endDate
   * @param DateTime|null $joinDate
   * @return mixed
   * @throws Exception
   */
  public function generate(DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    if (empty($startDate)) {
      $startDate = new DateTime($this->getMembershipStartDate($startDate, $endDate, $joinDate));
    }

    $instalmentAmount = $this->calculateInstalmentAmount();
    $firstInstalment = $this->calculateFirstInstalment($instalmentAmount, $startDate, $endDate);
    $instalments[] = $firstInstalment;

    switch ($this->schedule) {
      case self::MONTHLY:
        $noOfInstalment = self::MONTHLY_INSTALMENT_COUNT;
        break;

      case self::QUARTERLY:
        $noOfInstalment = self::QUARTERLY_INSTALMENT_COUNT;
        break;

      default:
        $noOfInstalment = self::ANNUALLY_INSTALMENT_COUNT;
    }

    if ($noOfInstalment > 1) {
      $nextInstalmentDate = $startDate->format('Y-m-d');
      for ($i = 1; $i < $noOfInstalment; $i++) {
        $intervalSpec = 'P1M';
        if ($this->schedule == self::QUARTERLY) {
          $intervalSpec = 'P4M';
        }
        $instalmentDate = new DateTime($nextInstalmentDate);
        $instalmentDate->add(new DateInterval($intervalSpec));
        $nextInstalmentDate = $instalmentDate->format('Y-m-d');
        $followingInstalment = new CRM_MembershipExtras_DTO_ScheduleInstalment();
        $followingInstalment->setInstalmentDate($instalmentDate);
        $followingInstalment->setInstalmentAmount($instalmentAmount);
        array_push($instalments, $followingInstalment);
      }
    }

    return $instalments;
  }

  /**
   * Gets Membership start date
   *
   * @param DateTime|NULL $startDate
   * @param DateTime|NULL $endDate
   * @param DateTime|NULL $joinDate
   * @return mixed
   */
  private function getMembershipStartDate(DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    $membershipDates = $this->membershipTypeDatesCalculator->getDatesForMembershipType(
      $this->membershipTypes[0]->id,
      $startDate,
      $endDate,
      $joinDate
    );

    return $membershipDates['start_date'];
  }

  /**
   * Calculates the instalment amount for a set of membership types given that the
   * condition for calculating the following instalment amount is met.
   * Calculation is (total amount of membership(s) cost for a full year / 12
   *
   * @return CRM_MembershipExtras_DTO_ScheduleInstalmentAmount
   */
  private function calculateInstalmentAmount() {
    $totalMembershipTypeAmount = 0;
    $totalMembershipTypeTaxAmount = 0;
    foreach ($this->membershipTypes as $membershipType) {
      $totalMembershipTypeAmount += $membershipType->minimum_fee;
      $totalMembershipTypeTaxAmount = +$this->membershipTypeTaxAmountCalculator->calculateTax($membershipType);
    }
    switch ($this->schedule) {
      case self::MONTHLY:
        $divisor = self::MONTHLY_INSTALMENT_COUNT;
        break;

      case self::QUARTERLY:
        $divisor = self::QUARTERLY_INSTALMENT_COUNT;
        break;

      default:
        $divisor = self::ANNUALLY_INSTALMENT_COUNT;
    }
    $instalmentAmount = MoneyUtilities::roundToPrecision($totalMembershipTypeAmount / $divisor, 2);
    $instalmentTaxAmount = MoneyUtilities::roundToPrecision($totalMembershipTypeTaxAmount / $divisor, 2);

    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalmentAmount();
    $instalment->setAmount($instalmentAmount);
    $instalment->setTaxAmount($instalmentTaxAmount);

    return $instalment;
  }

  /**
   * Calculates the first instalment amount for a set of membership types given that the conditions
   * for calculating the first instalment amount is met.
   *
   * @param CRM_MembershipExtras_DTO_ScheduleInstalmentAmount $instalmentAmount
   * @param DateTime|NULL $startDate
   * @param DateTime|NULL $endDate
   * @return CRM_MembershipExtras_DTO_ScheduleInstalment
   */
  private function calculateFirstInstalment(
    ScheduleInstalmentAmount $instalmentAmount,
    DateTime $startDate = NULL,
    DateTime $endDate = NULL
  ) {

    $amount = $instalmentAmount->getAmount();
    $taxAmount = $instalmentAmount->getTaxAmount();

    if ($this->membershipTypes[0]->period_type == 'fixed') {
      //TODO calculation for pro-rata by start date for fixed membership type
    }

    $fistInstalmentAmount = new ScheduleInstalmentAmount();
    $fistInstalmentAmount->setAmount($amount);
    $fistInstalmentAmount->setTaxAmount($taxAmount);

    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalment();
    $instalment->setInstalmentDate($startDate);
    $instalment->setInstalmentAmount($fistInstalmentAmount);

    return $instalment;
  }

  /**
   * Validates the membership types passed in to ensure they meets the criteria for calculating
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  private function validateMembershipTypeForInstalment() {
    $fixedPeriodStartDays = [];
    foreach ($this->membershipTypes as $membershipType) {
      if ($membershipType->period_type == 'fixed') {
        $fixedPeriodStartDays[] = $membershipType->fixed_period_start_day;
      }
      if ($membershipType->duration_unit != 'year' || $membershipType->duration_interval != 1) {
        throw new InvalidMembershipTypeInstalmentCalculator(InvalidMembershipTypeInstalmentCalculator::ONE_YEAR_DURATION);
      }
    }

    $fixedPeriodStartDays = array_unique($fixedPeriodStartDays);
    if (!empty($fixedPeriodStartDays) && count($fixedPeriodStartDays) != 1) {
      throw new InvalidMembershipTypeInstalmentCalculator(InvalidMembershipTypeInstalmentCalculator::SAME_PERIOD_START_DAY);
    }
  }

  /**
   * Calculates the remaining days from start date to the end of month date.
   *
   * @param DateTime $startDate
   *
   * @return int
   */
  private function calculateRemainingDaysInStartDateMonth(DateTime $startDate) {
    $endOfMonthDate = new DateTime($startDate->format('Y-m-t'));
    $interval = $endOfMonthDate->diff($startDate);

    return (int) $interval->format("%a");
  }

  /**
   * Calculates the number of days in the month the start date falls in.
   *
   * @param DateTime $startDate
   *
   * @return int
   */
  private function getNumberOfDaysInStartDateMonth(DateTime $startDate) {
    return (int) $startDate->format('t');
  }

}
