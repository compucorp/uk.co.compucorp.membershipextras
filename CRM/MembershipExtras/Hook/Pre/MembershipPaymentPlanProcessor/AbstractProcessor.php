<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Utils_InstalmentSchedule as InstalmentScheduleUtils;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator as InstalmentAmountCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_AbstractProcessor {

  /**
   * The number of instalments to be created.
   *
   * @var int
   */
  protected $instalmentsCount;

  /**
   * The frequency of the recurring contribution instalments.
   *
   * @var int
   */
  protected $instalmentsFrequency;

  /**
   * The frequency unit of the recurring contribution instalments.
   *
   * @var string
   */
  protected $instalmentsFrequencyUnit;

  /**
   * The selected payment plan schedule.
   *
   * @var string
   */
  protected $paymentPlanSchedule;

  /**
   * Membership ID is attached to an object either Contribution or Line Item.
   *
   * @var int
   */
  protected $membershipId;

  /**
   * The contribution or line item to-be-created parameters passed from the hook.
   *
   * @var array
   */
  protected $params;

  /**
   * Calculates tax amount for given amount.
   *
   * @param float $totalAmount
   * @param string $financialType
   *
   * @return float
   */
  protected function calculateInstalmentTax($totalAmount, $financialType) {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = CRM_Utils_Array::value($financialType, $taxRates, 0);

    return MoneyUtilities::roundToCurrencyPrecision(
      ($totalAmount * ($rate / 100)) / (1 + ($rate / 100))
    );
  }

  /**
   * Calculates single installment amount.
   *
   * @param float $amount
   * @param float $divisor
   *
   * @return float
   */
  protected function calculateSingleInstalmentAmount($amount, $divisor = NULL) {
    if (is_null($divisor)) {
      $divisor = $this->instalmentsCount;
    }
    return MoneyUtilities::roundToCurrencyPrecision($amount / $divisor, 2);
  }

  /**
   * Checks if priceset was selected on the form to create the membership.
   */
  protected function isUsingPriceSet() {
    $priceSetID = CRM_Utils_Request::retrieve('price_set_id', 'Int');

    if (!empty($priceSetID)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Asigns instalments count, instalments frequency,
   * instalment frequency unit from payment plan schedule param
   */
  protected function assignInstalmentDetails() {
    if (array_key_exists('membership_id', $this->params)) {
      //Contribution object
      $this->membershipId = $this->params['membership_id'];
    }
    else {
      //LineItem object
      $this->membershipId = $this->params['entity_id'];
    }
    $this->paymentPlanSchedule = CRM_Utils_Request::retrieve('payment_plan_schedule', 'String');
    $instalmentDetails = InstalmentScheduleUtils::getInstalmentDetails($this->paymentPlanSchedule, $this->membershipId);
    $this->instalmentsCount = $instalmentDetails['instalments_count'];
    $this->instalmentsFrequency = $instalmentDetails['instalments_frequency'];
    $this->instalmentsFrequencyUnit = $instalmentDetails['instalments_frequency_unit'];
  }

  /**
   * Gets pro rated instalment amount
   */
  protected function getProRatedInstalmentAmount(array $membershipTypes) {
    $fixedPeriodTypeCalculator = new FixedPeriodTypeCalculator($membershipTypes);
    $fixedPeriodTypeCalculator->setStartDate(new DateTime($this->getMembership()['start_date']));
    $fixedPeriodTypeCalculator->setEndDate(new DateTime($this->getMembership()['end_date']));
    $fixedPeriodTypeCalculator->setJoinDate(new DateTime($this->getMembership()['join_date']));
    $instalmentAmountCalculator = new InstalmentAmountCalculator($fixedPeriodTypeCalculator);
    $instalmentAmountCalculator->getCalculator()->calculate();

    return $instalmentAmountCalculator;
  }

  protected function getInstalmentCountForFixedMembeship($fixedMembershipType) {
    if ($this->instalmentsCount == 1) {
      return $this->instalmentsCount;
    }
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($fixedMembershipType, new MembershipTypeDatesCalculator());

    $startDate = new DateTime($this->getMembership()['start_date']);
    $endDate = new DateTime($this->getMembership()['end_date']);
    return $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($startDate, $endDate);
  }

  protected function getMembership() {
    return civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => $this->membershipId,
    ])['values'][0];
  }

}
