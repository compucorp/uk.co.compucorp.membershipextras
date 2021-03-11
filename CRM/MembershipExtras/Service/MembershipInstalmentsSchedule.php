<?php

use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment as InvalidMembershipTypeInstalment;
use CRM_MembershipExtras_DTO_ScheduleInstalmentAmount as ScheduleInstalmentAmount;
use CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator as InstalmentAmountCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator as RollingPeriodCalculator;

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
    $this->startDate = $startDate;
    $this->endDate = $endDate;
    $this->joinDate = $joinDate;

    $instalmentAmount = $this->calculateInstalmentAmount();

    if (!empty($this->nonMembershipPriceFieldValues)) {
      $this->applyNonMembershipPriceFieldValueAmount($instalmentAmount);
    }

    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalment();
    $instalment->setInstalmentDate($startDate);
    $instalment->setInstalmentAmount($instalmentAmount);

    $instalments['instalments'][] = $instalment;

    $noOfInstalment = $this->getInstalmentsNumber($this->membershipTypes[0], $this->schedule, $this->startDate, $this->endDate, $this->joinDate);
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
        array_push($instalments['instalments'], $followingInstalment);
      }
    }

    $instalments['sub_total'] = $this->getInstalmentsSubTotalAmount($instalments['instalments']);
    $instalments['tax_amount'] = $this->getInstalmentsTaxAmount($instalments['instalments']);
    $instalments['total_amount'] = $this->getInstalmentsTotalAmount($instalments['instalments']);

    $instalments['membership_start_date'] = $this->startDate->format('Y-m-d');
    $instalments['membership_end_date'] = $this->endDate->format('Y-m-d');

    if ($this->membershipTypes[0]->period_type == 'fixed') {
      $instalments['prorated_number'] = $this->instalmentCalculator->getCalculator()->getProRatedNumber();
      $instalments['prorated_unit'] = $this->instalmentCalculator->getCalculator()->getProRatedUnit();
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
   * @throws Exception
   */
  private function calculateInstalmentAmount() {
    $this->getInstalmentAmountCalculator();
    $this->instalmentCalculator->getCalculator()->calculate();
    $divisor = $this->getInstalmentsNumber($this->membershipTypes[0], $this->schedule, $this->startDate, $this->endDate, $this->joinDate);
    $amount = $this->calculateSingleInstalmentAmount($this->instalmentCalculator->getCalculator()->getAmount(), $divisor);
    $taxAmount = $this->calculateSingleInstalmentAmount($this->instalmentCalculator->getCalculator()->getTaxAmount(), $divisor);
    $totalAmount = $this->calculateSingleInstalmentAmount($this->instalmentCalculator->getCalculator()->getTotalAmount(), $divisor);

    $instalment = new CRM_MembershipExtras_DTO_ScheduleInstalmentAmount();
    $instalment->setAmount($amount);
    $instalment->setTaxAmount($taxAmount);
    $instalment->setTotalAmount($totalAmount);
    $lineItems = $this->instalmentCalculator->getCalculator()->getLineItems();
    if ($divisor != 1) {
      $lineItems = $this->setLineItemsAmountPerInstalment($lineItems, $divisor);
    }
    $instalment->setLineItems($lineItems);
    return $instalment;
  }

  /**
   * @param array $lineItems
   * @param $instalmentCount
   * @return array
   */
  private function setLineItemsAmountPerInstalment(array $lineItems, $instalmentCount) {
    $newInstalmentLineItems = [];
    foreach ($lineItems as $lineItem) {
      $instalmentUnitPrice = $this->calculateSingleInstalmentAmount($lineItem->getUnitPrice(), $instalmentCount);
      $instalmentSubTotal = $this->calculateSingleInstalmentAmount($lineItem->getSubTotal(), $instalmentCount);
      $instalmentTaxAmount = $this->calculateSingleInstalmentAmount($lineItem->getTaxAmount(), $instalmentCount);
      $instalmentTotal = $this->calculateSingleInstalmentAmount($lineItem->getTotalAmount(), $instalmentCount);

      $lineItem->setUnitPrice($instalmentUnitPrice);
      $lineItem->setSubTotal($instalmentSubTotal);
      $lineItem->setTaxAmount($instalmentTaxAmount);
      $lineItem->setTotalAmount($instalmentTotal);

      array_push($newInstalmentLineItems, $lineItem);
    }

    return $newInstalmentLineItems;
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
   * Applies amount, tax amount from Non Membership Price Field Value
   * to instalment amount
   *
   * @param CRM_MembershipExtras_DTO_ScheduleInstalmentAmount $instalmentAmount
   * @throws Exception
   */
  private function applyNonMembershipPriceFieldValueAmount(ScheduleInstalmentAmount $instalmentAmount) {
    $totalNonMembershipPriceFieldValueAmount = 0;
    $totalNonMembershipPriceFieldValueTaxAmount = 0;
    $lineItemTotalAmount = 0;
    $nonMembershipPriceFieldValueLineItems = [];
    $divisor = $this->getInstalmentsNumber($this->membershipTypes[0], $this->schedule, $this->startDate, $this->endDate, $this->joinDate);
    foreach ($this->nonMembershipPriceFieldValues as $priceFieldValue) {
      $quantity = $priceFieldValue['quantity'];
      $amount = $priceFieldValue['values']['amount'];
      $subTotal = (float) $amount * (float) $quantity;
      $totalNonMembershipPriceFieldValueAmount += $subTotal;
      $salesTax = $this->membershipInstalmentTaxAmountCalculator->calculateByPriceFieldValue($priceFieldValue['values']) * (float) $quantity;
      $totalNonMembershipPriceFieldValueTaxAmount += $salesTax;

      $scheduleInstalmentLineItem = new CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem();
      $financialTypeId = $priceFieldValue['values']['financial_type_id'];
      $scheduleInstalmentLineItem->setFinancialTypeId($financialTypeId);
      $scheduleInstalmentLineItem->setQuantity($quantity);
      $scheduleInstalmentLineItem->setUnitPrice($this->calculateSingleInstalmentAmount($amount, $divisor));
      $scheduleInstalmentLineItem->setSubTotal($this->calculateSingleInstalmentAmount($subTotal, $divisor));
      $scheduleInstalmentLineItem->setTaxRate($this->membershipInstalmentTaxAmountCalculator->getTaxRateByFinancialTypeId($financialTypeId));
      $scheduleInstalmentLineItem->setTaxAmount($this->calculateSingleInstalmentAmount($salesTax, $divisor));
      $lineItemTotalAmount = $this->calculateSingleInstalmentAmount($subTotal + $salesTax, $divisor);
      $scheduleInstalmentLineItem->setTotalAmount($lineItemTotalAmount);
      $nonMembershipPriceFieldValueLineItems[] = $scheduleInstalmentLineItem;
    }

    $totalNonMembershipPriceFieldValueAmountPerInstalment = $this->calculateSingleInstalmentAmount($totalNonMembershipPriceFieldValueAmount, $divisor);
    $newInstalmentAmount = $totalNonMembershipPriceFieldValueAmountPerInstalment + $instalmentAmount->getAmount();
    $totalNonMembershipPriceFieldValueTaxAmountPerInstalment = $this->calculateSingleInstalmentAmount($totalNonMembershipPriceFieldValueTaxAmount, $divisor);
    $newInstalmentTaxAmount = $totalNonMembershipPriceFieldValueTaxAmountPerInstalment + $instalmentAmount->getTaxAmount();

    $instalmentAmount->setAmount($newInstalmentAmount);
    $instalmentAmount->setTaxAmount($newInstalmentTaxAmount);

    $nonMembershipPriceFieldValueTotalAmount = $totalNonMembershipPriceFieldValueAmountPerInstalment + $totalNonMembershipPriceFieldValueTaxAmountPerInstalment;
    $currentTotalAmount = $instalmentAmount->getTotalAmount();
    $totalAmount = $nonMembershipPriceFieldValueTotalAmount + $currentTotalAmount;
    $instalmentAmount->setTotalAmount($totalAmount);

    if (empty($nonMembershipPriceFieldValueLineItems)) {
      return;
    }

    $currentLineItems = $instalmentAmount->getLineItems();
    foreach ($nonMembershipPriceFieldValueLineItems as $priceFieldLineItem) {
      array_push($currentLineItems, $priceFieldLineItem);
    }
    $instalmentAmount->setLineItems($currentLineItems);
  }

  /**
   * Validates the membership types passed in to ensure they meets the criteria for calculating
   *
   * @throws InvalidMembershipTypeInstalment
   */
  private function validateMembershipTypeForInstalment() {
    $fixedPeriodStartDays = [];
    $periodTypes = [];
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
    }

    $hasFixedMembershipType = in_array('fixed', $periodTypes);

    if ($hasFixedMembershipType && $this->schedule == self::QUARTERLY) {
      throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::QUARTERLY_NOT_SUPPORT));
    }

    if ($hasFixedMembershipType && in_array('rolling', $periodTypes)) {
      throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::PERIOD_TYPE));
    }

    if ($hasFixedMembershipType) {
      $fixedPeriodStartDays = array_unique($fixedPeriodStartDays);
      if (!empty($fixedPeriodStartDays) && count($fixedPeriodStartDays) != 1) {
        throw new InvalidMembershipTypeInstalment(ts(InvalidMembershipTypeInstalment::SAME_PERIOD_START_DAY));
      }
    }
  }

  /**
   * Calculates Instalments Sub Total Amount
   *
   * @param array $instalments
   * @return float
   */
  private function getInstalmentsSubTotalAmount(array $instalments) {
    $subTotalAmount = 0.0;
    foreach ($instalments as $instalment) {
      $subTotalAmount += $instalment->getInstalmentAmount()->getAmount();
    }
    return $subTotalAmount;
  }

  /**
   * Calculates Instalments Tax Amount
   *
   * @param array $instalments
   * @return float
   */
  private function getInstalmentsTaxAmount(array $instalments) {
    $taxAmount = 0.0;
    foreach ($instalments as $instalment) {
      $taxAmount += $instalment->getInstalmentAmount()->getTaxAmount();
    }
    return $taxAmount;
  }

  /**
   * Calculates instalment total amount.
   *
   * @param array $instalments
   * @return float
   */
  private function getInstalmentsTotalAmount(array $instalments) {
    $totalAmount = 0.0;
    foreach ($instalments as $instalment) {
      $totalAmount += $instalment->getInstalmentAmount()->getTotalAmount();
    }
    return $totalAmount;
  }

  /**
   * Sets Non Membership Price Field values to the class property.
   *
   * @param array $nonMembershipPriceFieldValues
   */
  public function setNonMembershipPriceFieldValues(array $nonMembershipPriceFieldValues) {
    $this->nonMembershipPriceFieldValues = $nonMembershipPriceFieldValues;
  }

  /**
   * Calculates Single Instalment Amount
   *
   * @param $amount
   * @param $divisor
   * @return float|int
   */
  private function calculateSingleInstalmentAmount($amount, $divisor) {
    return $amount / $divisor;
  }

}
