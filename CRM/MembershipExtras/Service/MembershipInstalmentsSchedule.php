<?php

use CRM_MembershipExtras_Validate_PaymentPlan_MembershipType as membershipTypeValidator;
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
   * @var \CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator
   */
  private $installmentReceiveDateCalculator;

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
    $this->installmentReceiveDateCalculator = new CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator();
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

    $intervalSpecSchedule = [
      self::ANNUAL => 12,
      self::MONTHLY => 1,
      self::QUARTERLY => 3,
    ];

    if ($this->instalmentCount > 1) {
      $previousInstalmentDate = $firstInstalmentDate;
      $firstInstalmentDateTime = new DateTime($firstInstalmentDate);

      for ($instalmentNumber = 2; $instalmentNumber <= $this->instalmentCount; $instalmentNumber++) {
        $intervalSpec = CRM_Utils_Array::value($this->schedule, $intervalSpecSchedule, 1);

        $params['previous_instalment_date'] = $previousInstalmentDate;

        $instalmentDate = $this->installmentReceiveDateCalculator->getSameDayNextMonth($firstInstalmentDateTime, $intervalSpec * ($instalmentNumber - 1));
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
    $validator = new membershipTypeValidator($this->membershipTypes, $this->schedule);
    $validator->validateBail();
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
