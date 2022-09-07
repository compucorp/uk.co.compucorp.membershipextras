<?php

use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment as InvalidMembershipTypeInstalment;

/**
 * Validates the membership types passed in to ensure they meets the criteria for calculating installment.
 */
class CRM_MembershipExtras_Validate_PaymentPlan_MembershipType {

  const MONTHLY = 'monthly';
  const QUARTERLY = 'quarterly';
  const ANNUAL = 'annual';

  const MONTHLY_INSTALMENT_COUNT = 12;
  const QUARTERLY_INSTALMENT_COUNT = 4;
  const ANNUAL_INTERVAL_COUNT = 1;

  /**
   * @var array
   *  The array of errors.
   */
  private $errorBag = [];

  /**
   * @var array
   *  The array of membership types to validate
   */
  private $membershipTypes;

  /**
   * @var string
   *  The Installment schedule
   */
  private $schedule;

  /**
   * CRM_MembershipExtras_Validate_PaymentPlan_MembershipType constructor.
   *
   * @param array $membershipTypes
   * @param string $schedule
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   */
  public function __construct(array $membershipTypes, $schedule = NULL) {
    $this->membershipTypes = $membershipTypes;
    $this->schedule = $schedule;
    $this->validateMembershipTypeFields();
  }

  /**
   * Determines if the data fails the validation rules.
   *
   * @return bool
   */
  public function passes() {
    return count($this->errorBag) == 0;
  }

  /**
   * Determines if the data fails the validation rules.
   *
   * @return bool
   */
  public function fails() {
    return !$this->passes();
  }

  /**
   * "Break" on first validation error.
   *
   * @throws InvalidMembershipTypeInstalment
   */
  public function validateBail() {
    if ($this->fails()) {
      throw new InvalidMembershipTypeInstalment($this->errorBag[0]);
    }
  }

  /**
   * Returns the list of errors.
   *
   * @return array
   */
  public function errors() {
    return $this->errorBag;
  }

  /**
   * Returns the last error encountered otherwise null.
   *
   * @return string
   */
  public function lastError() {
    if ($this->passes()) {
      return NULL;
    }

    return $this->errorBag[count($this->errorBag) - 1];
  }

  /**
   * Performs validation logic.
   *
   * @return self
   */
  private function validateMembershipTypeFields() {
    $periodTypes = [];
    $durationUnits = [];
    $durationIntervals = [];
    $fixedPeriodStartDays = [];

    foreach ($this->membershipTypes as $membershipType) {
      $membershipType = (array) $membershipType;

      if ($membershipType['period_type'] == 'fixed') {
        if ($membershipType['duration_unit'] != 'year') {
          $this->errorBag[] = ts(InvalidMembershipTypeInstalment::ONE_YEAR_DURATION);
        }
        if ($membershipType['duration_interval'] != 1) {
          $this->errorBag[] = ts(InvalidMembershipTypeInstalment::DURATION_INTERVAL);
        }
        $fixedPeriodStartDays[] = $membershipType['fixed_period_start_day'];
      }
      else {
        if ($membershipType['duration_unit'] == 'day') {
          $this->errorBag[] = ts(InvalidMembershipTypeInstalment::DAY_DURATION);
        }
      }

      if ($membershipType['duration_unit'] == 'lifetime') {
        $this->errorBag[] = ts(InvalidMembershipTypeInstalment::LIFETIME_DURATION);
      }

      $periodTypes[] = $membershipType['period_type'];
      $durationUnits[] = $membershipType['duration_unit'];
      $durationIntervals[] = $membershipType['duration_interval'];
    }

    $hasFixedMembershipType = in_array('fixed', $periodTypes);
    $hasRollingMembershipType = in_array('rolling', $periodTypes);

    if (count(array_unique($periodTypes)) != 1 || count(array_unique($durationUnits)) != 1 || count(array_unique($durationIntervals)) != 1) {
      $this->errorBag[] = ts(InvalidMembershipTypeInstalment::SAME_PERIOD_AND_DURATION);
    }

    if ($hasFixedMembershipType) {
      $fixedPeriodStartDays = array_unique($fixedPeriodStartDays);
      if (!empty($fixedPeriodStartDays) && count($fixedPeriodStartDays) != 1) {
        $this->errorBag[] = ts(InvalidMembershipTypeInstalment::SAME_PERIOD_START_DAY);
      }
    }

    if (!empty($this->schedule)) {
      if ($hasFixedMembershipType && $this->schedule == self::QUARTERLY) {
        $this->errorBag[] = ts(InvalidMembershipTypeInstalment::QUARTERLY_NOT_SUPPORT);
      }

      if ($this->schedule != self::MONTHLY && $hasRollingMembershipType && $durationUnits[0] == 'month') {
        $this->errorBag[] = ts(InvalidMembershipTypeInstalment::MONTHLY_INSTALMENT_FOR_MONTH_UNIT);
      }
    }

    return $this;
  }

}
