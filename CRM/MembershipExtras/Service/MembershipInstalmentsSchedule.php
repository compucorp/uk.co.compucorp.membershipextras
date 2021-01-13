<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator as InvalidMembershipTypeInstalmentCalculator;
use CRM_MembershipExtras_DTO_ScheduleInstalmentAmount as ScheduleInstalmentAmount;
use CRM_MembershipExtras_Service_MembershipInstalmentAmount as InstalmentAmount;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator as RollingPeriodCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipInstalmentsSchedule
 */
class CRM_MembershipExtras_Service_MembershipInstalmentsSchedule {

  const MONTHLY = 'monthly';
  const QUARTERLY = 'quarterly';
  const ANNUAL = 'annual';

  const MONTHLY_INSTALMENT_COUNT = 12;
  const QUARTERLY_INSTALMENT_COUNT = 4;
  const ANNUAL_INSTALMENT_COUNT = 1;

  /**
   * @var \CRM_MembershipExtras_Service_MembershipTypeDatesCalculator
   */
  private $membershipTypeDatesCalculator;

  /**
   * @var array
   */
  private $membershipTypes;

  /**
   * @var \CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
   */
  private $membershipInstalmentTaxAmountCalculator;

  /**
   * @var string
   */
  private $schedule;
  /**
   * @var array
   */
  private $nonMembershipPriceFieldValues;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeInstalment constructor.
   *
   * @param array $membershipTypes
   * @param string $schedule
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  public function __construct(array $membershipTypes, string $schedule) {
    $this->membershipInstalmentTaxAmountCalculator = new CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator();
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
   *
   * @return mixed
   * @throws Exception
   */
  public function generate(DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    if (empty($startDate)) {
      $startDate = new DateTime($this->getMembershipStartDate($startDate, $endDate, $joinDate));
    }

    $instalmentAmount = $this->calculateInstalmentAmount();

    if (!empty($this->nonMembershipPriceFieldValues)) {
      $this->applyNonMembershipPriceFieldValueAmount($instalmentAmount);
    }

    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalment();
    $instalment->setInstalmentDate($startDate);
    $instalment->setInstalmentAmount($instalmentAmount);

    $instalments[] = $instalment;
    $noOfInstalment = $this->getNumberOfInstalment();
    if ($noOfInstalment > 1) {
      $nextInstalmentDate = $startDate->format('Y-m-d');
      for ($i = 1; $i < $noOfInstalment; $i++) {
        $intervalSpec = 'P1M';
        if ($this->schedule == self::QUARTERLY) {
          $intervalSpec = 'P3M';
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
   *
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
   * Calculation is calculated by calculator class based on membership type
   *
   * @return CRM_MembershipExtras_DTO_ScheduleInstalmentAmount
   */
  private function calculateInstalmentAmount() {
    if ($this->membershipTypes[0]->period_type == 'fixed') {
      $instalmentAmount = new InstalmentAmount(new FixedPeriodCalculator($this->membershipTypes));
    }
    else {
      $instalmentAmount = new InstalmentAmount(new RollingPeriodCalculator($this->membershipTypes));
    }
    $instalmentAmount->getCalculator()->calculate();

    $divisor = $this->getNumberofInstalment();
    $amount = MoneyUtilities::roundToPrecision($instalmentAmount->getCalculator()->getAmount() / $divisor, 2);
    $taxAmount = MoneyUtilities::roundToPrecision($instalmentAmount->getCalculator()->getTaxAmount() / $divisor, 2);
    $taxAmount = MoneyUtilities::roundToPrecision($instalmentAmount->getCalculator()->getTaxAmount() / $divisor, 2);

    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalmentAmount();
    $instalment->setAmount($amount);
    $instalment->setTaxAmount($taxAmount);

    return $instalment;
  }

  /**
   * Applies amount, tax amount from Non Membership Price Field Value
   * to instalment amount
   *
   * @param CRM_MembershipExtras_DTO_ScheduleInstalmentAmount $instalmentAmount
   */
  private function applyNonMembershipPriceFieldValueAmount(ScheduleInstalmentAmount $instalmentAmount) {
    $totalNonMembershipPriceFieldValueAmount = 0;
    $totalNonMembershipPriceFieldValueTaxAmount = 0;
    foreach ($this->nonMembershipPriceFieldValues as $priceFieldValue) {
      $quantity = $priceFieldValue['quantity'];
      $amount = $priceFieldValue['values']['amount'];
      $totalNonMembershipPriceFieldValueAmount += (float) $amount * (float) $quantity;
      $salesTax = $this->membershipInstalmentTaxAmountCalculator->calculateByPriceFieldValue($priceFieldValue['values']);
      $totalNonMembershipPriceFieldValueTaxAmount += (float) $salesTax * (float) $quantity;
    }

    $divisor = $this->getNumberofInstalment();
    $newInstalmentAmount = MoneyUtilities::roundToPrecision(
      ($totalNonMembershipPriceFieldValueAmount / $divisor) + $instalmentAmount->getAmount(), 2
    );
    $newInstalmentTaxAmount = MoneyUtilities::roundToPrecision(
      ($totalNonMembershipPriceFieldValueTaxAmount / $divisor) + $instalmentAmount->getTaxAmount(), 2
    );

    $instalmentAmount->setAmount($newInstalmentAmount);
    $instalmentAmount->setTaxAmount($newInstalmentTaxAmount);
  }

  private function getNumberOfInstalment() {
    switch ($this->schedule) {
      case self::MONTHLY:
        $noOfInstalment = self::MONTHLY_INSTALMENT_COUNT;
        break;

      case self::QUARTERLY:
        $noOfInstalment = self::QUARTERLY_INSTALMENT_COUNT;
        break;

      default:
        $noOfInstalment = self::ANNUAL_INSTALMENT_COUNT;
    }

    return $noOfInstalment;
  }

  /**
   * Validates the membership types passed in to ensure they meets the criteria for calculating
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  private function validateMembershipTypeForInstalment() {
    $fixedPeriodStartDays = [];
    $periodTypes = [];
    foreach ($this->membershipTypes as $membershipType) {
      $periodTypes[] = $membershipType->period_type;
      if ($membershipType->period_type == 'fixed') {
        $fixedPeriodStartDays[] = $membershipType->fixed_period_start_day;
      }
      if ($membershipType->duration_unit != 'year' || $membershipType->duration_interval != 1) {
        throw new InvalidMembershipTypeInstalmentCalculator(ts(InvalidMembershipTypeInstalmentCalculator::ONE_YEAR_DURATION));
      }
    }

    $fixedPeriodStartDays = array_unique($fixedPeriodStartDays);
    if (!empty($fixedPeriodStartDays) && count($fixedPeriodStartDays) != 1) {
      throw new InvalidMembershipTypeInstalmentCalculator(ts(InvalidMembershipTypeInstalmentCalculator::SAME_PERIOD_START_DAY));
    }

    if (in_array('fixed', $periodTypes) && in_array('rolling', $periodTypes)) {
      throw new InvalidMembershipTypeInstalmentCalculator(ts(InvalidMembershipTypeInstalmentCalculator::PERIOD_TYPE));
    }
  }

  public function setNonMembershipPriceFieldValues(array $nonMembershipPriceFieldValues) {
    $this->nonMembershipPriceFieldValues = $nonMembershipPriceFieldValues;
  }

}
