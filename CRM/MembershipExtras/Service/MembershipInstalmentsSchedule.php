<?php

use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment as InvalidMembershipTypeInstalment;
use CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator as InstalmentAmountCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator as RollingPeriodCalculator;
use CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate as CalculateContributionReceiveDateDispatcher;
use CRM_MembershipExtras_Helper_InstalmentSchedule as InstalmentScheduleHelper;

/**
 * Class CRM_MembershipExtras_Service_MembershipInstalmentsSchedule
 */
class CRM_MembershipExtras_Service_MembershipInstalmentsSchedule {

  use CRM_MembershipExtras_Helper_InstalmentHelperTrait;

  const MONTHLY = 'monthly';
  const QUARTERLY = 'quarterly';
  const ANNUAL = 'annual';

  const MONTHLY_INSTALMENT_COUNT = 12;
  const QUARTERLY_INSTALMENT_COUNT = 4;
  const ANNUAL_INTERVAL_COUNT = 1;

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
   * @var DateTime|null
   */
  private $startDate;
  /**
   * @var DateTime|null
   */
  private $endDate;
  /**
   * @var DateTime|null
   */
  private $joinDate;
  /**
   * @var \CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator
   */
  private $instalmentCalculator;
  /**
   * @var int
   */
  private $instalmentCount;

  /**
   * CRM_MembershipExtras_Service_MembershipTypeInstalment constructor.
   *
   * @param array $membershipTypes
   * @param string $schedule
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   */
  public function __construct(array $membershipTypes, string $schedule) {
    $this->membershipInstalmentTaxAmountCalculator = new CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator();
    $this->membershipTypes = $membershipTypes;
    $this->schedule = $schedule;
    $this->validateMembershipTypeForInstalment();
  }

  /**
   * Generates instalments for a set of membership types given that the conditions
   * for calculating instalments
   *
   * @param int|string $paymentMethod
   * @param DateTime|null $startDate
   * @param DateTime|null $endDate
   * @param DateTime|null $joinDate
   *
   * @return mixed
   * @throws Exception
   */
  public function generate($paymentMethod, DateTime $startDate = NULL, DateTime $endDate = NULL, DateTime $joinDate = NULL) {
    if (empty($startDate)) {
      $startDate = new DateTime($this->getMembershipStartDate($this->membershipTypes[0]->id, $startDate, $endDate, $joinDate));
    }
    $this->startDate = $startDate;
    $this->endDate = $endDate;
    $this->joinDate = $joinDate;
    $this->instalmentCount = $this->getInstalmentsNumber(
      $this->membershipTypes[0], $this->schedule, $this->startDate, $this->endDate, $this->joinDate
    );

    $instalmentAmount = $this->calculateInstalmentAmount();

    if (!empty($this->nonMembershipPriceFieldValues)) {
      $this->instalmentCalculator->setNonMembershipPriceFieldValueAmount(
        $instalmentAmount,
        $this->nonMembershipPriceFieldValues,
        $this->membershipInstalmentTaxAmountCalculator,
        $this->instalmentCount
      );
    }

    $firstInstalmentDate = $this->startDate->format('Y-m-d');
    $instalmentFrequencyInterval = InstalmentScheduleHelper::getFrequencyInterval($this->schedule);
    $instalmentFrequencyUnit = InstalmentScheduleHelper::getFrequencyUnit($this->schedule, $instalmentFrequencyInterval);

    $params = [
      'membership_id' => NULL,
      'contribution_recur_id' => NULL,
      'previous_instalment_date' => NULL,
      'payment_schedule' => $this->schedule,
      'payment_instrument_id' => $paymentMethod,
      'membership_start_date' => $firstInstalmentDate,
      'frequency_interval' => $instalmentFrequencyInterval,
      'frequency_unit' => $instalmentFrequencyUnit,
    ];

    $this->dispatchContributionReceiveDateCalculation(1, $firstInstalmentDate, $params);

    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalment();
    $instalment->setInstalmentDate(new DateTime($firstInstalmentDate));
    $instalment->setInstalmentAmount($instalmentAmount);

    $instalments['instalments'][] = $instalment;

    if ($this->instalmentCount > 1) {
      $previousInstalmentDate = $firstInstalmentDate;
      for ($instalmentNumber = 2; $instalmentNumber <= $this->instalmentCount; $instalmentNumber++) {
        $intervalSpec = 'P1M';
        if ($this->schedule == self::QUARTERLY) {
          $intervalSpec = 'P3M';
        }

        $params['previous_instalment_date'] = $previousInstalmentDate;

        $instalmentDate = new DateTime($previousInstalmentDate);
        $instalmentDate->add(new DateInterval($intervalSpec));
        $instalmentDate = $instalmentDate->format('Y-m-d');

        $dispatchedInstalmentDate = $instalmentDate;

        $this->dispatchContributionReceiveDateCalculation($instalmentNumber, $dispatchedInstalmentDate, $params);

        $previousInstalmentDate = $dispatchedInstalmentDate;

        $followingInstalment = new CRM_MembershipExtras_DTO_ScheduleInstalment();
        $followingInstalment->setInstalmentDate(new DateTime($dispatchedInstalmentDate));
        $followingInstalment->setInstalmentAmount($instalmentAmount);
        array_push($instalments['instalments'], $followingInstalment);
      }
    }

    $instalments['sub_total'] = $this->instalmentCalculator->getInstalmentsSubTotalAmount($instalments['instalments']);
    $instalments['tax_amount'] = $this->instalmentCalculator->getInstalmentsTaxAmount($instalments['instalments']);
    $instalments['total_amount'] = $this->instalmentCalculator->getInstalmentsTotalAmount($instalments['instalments']);

    $instalments['membership_start_date'] = $this->startDate->format('Y-m-d');
    $instalments['membership_end_date'] = $this->endDate->format('Y-m-d');

    if ($this->instalmentCalculator->getCalculator() instanceof FixedPeriodTypeCalculator) {
      $instalments['prorated_number'] = $this->instalmentCalculator->getCalculator()->getProRatedNumber();
      $instalments['prorated_unit'] = $this->instalmentCalculator->getCalculator()->getProRatedUnit();
    }

    return $instalments;
  }

  /**
   * Calculates the instalment amount for a set of membership types given that the
   * condition for calculating the following instalment amount is met.
   * Calculation is calculated by calculator class based on membership type
   *
   * @return CRM_MembershipExtras_DTO_ScheduleInstalmentAmount
   * @throws Exception
   */
  private function calculateInstalmentAmount() {
    $this->getInstalmentAmountCalculator();
    $this->instalmentCalculator->getCalculator()->calculate();

    return $this->instalmentCalculator->calculateInstalmentAmount($this->instalmentCount);
  }

  /**
   * Provides instalment calculator object based on membership type
   *
   */
  private function getInstalmentAmountCalculator() {
    if ($this->membershipTypes[0]->period_type == 'fixed') {
      $fixedPeriodTypCalculator = new FixedPeriodTypeCalculator($this->membershipTypes);
      $fixedPeriodTypCalculator->setStartDate($this->startDate);
      $fixedPeriodTypCalculator->setEndDate($this->endDate);
      $fixedPeriodTypCalculator->setJoinDate($this->joinDate);
      $this->instalmentCalculator = new InstalmentAmountCalculator($fixedPeriodTypCalculator);
    }
    else {
      $this->instalmentCalculator = new InstalmentAmountCalculator(new RollingPeriodCalculator($this->membershipTypes));
    }
  }

  /**
   * Validates the membership types passed in to ensure they meets the criteria for calculating
   *
   * @throws InvalidMembershipTypeInstalment
   */
  private function validateMembershipTypeForInstalment() {
    $fixedPeriodStartDays = [];
    $periodTypes = [];
    $durationUnits = [];

    foreach ($this->membershipTypes as $membershipType) {
      if ($membershipType->duration_interval != 1) {
        throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::DURATION_INTERVAL));
      }
      if ($membershipType->period_type == 'fixed') {
        if ($membershipType->duration_unit != 'year') {
          throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::ONE_YEAR_DURATION));
        }
        $fixedPeriodStartDays[] = $membershipType->fixed_period_start_day;
      }
      else {
        if ($membershipType->duration_unit == 'day') {
          throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::DAY_DURATION));
        }
      }
      $periodTypes[] = $membershipType->period_type;
      $durationUnits[] = $membershipType->duration_unit;
    }

    $hasFixedMembershipType = in_array('fixed', $periodTypes);

    if ($hasFixedMembershipType && $this->schedule == self::QUARTERLY) {
      throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::QUARTERLY_NOT_SUPPORT));
    }

    if (count(array_unique($periodTypes)) != 1 || count(array_unique($durationUnits)) != 1) {
      throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::SAME_PERIOD_AND_DURATION));
    }

    if ($hasFixedMembershipType) {
      $fixedPeriodStartDays = array_unique($fixedPeriodStartDays);
      if (!empty($fixedPeriodStartDays) && count($fixedPeriodStartDays) != 1) {
        throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::SAME_PERIOD_START_DAY));
      }
    }
  }

  /**
   * Sets Non Membership Price Field values to the class property.
   *
   * @param array $nonMembershipPriceFieldValues
   */
  public function setNonMembershipPriceFieldValues(array $nonMembershipPriceFieldValues) {
    $this->nonMembershipPriceFieldValues = $nonMembershipPriceFieldValues;
  }

  private function dispatchContributionReceiveDateCalculation($instalmentNumber, &$instalmentDate, $params) {
    $dispatcher = new CalculateContributionReceiveDateDispatcher($instalmentNumber, $instalmentDate, $params);
    $dispatcher->dispatch();
  }

}
