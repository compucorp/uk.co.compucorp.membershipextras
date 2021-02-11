<?php

use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Utils_InstalmentSchedule as InstalmentScheduleUtils;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeAnnualCalculator as FixedPeriodTypeAnnualCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeMonthlyCalculator as FixedPeriodTypeMonthlyCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentAmount as InstalmentAmount;

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
   *
   * @return float
   */
  protected function calculateSingleInstalmentAmount($amount) {
    return MoneyUtilities::roundToCurrencyPrecision($amount / $this->instalmentsCount);
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
  protected function getProRatedInstalmentAmount(array $membershipTypes, $membershipStartDate) {
    if ($this->paymentPlanSchedule == CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::MONTHLY) {
      $fixedPeriodTypeMonthlyCalculator = new FixedPeriodTypeMonthlyCalculator($membershipTypes);
      $fixedPeriodTypeMonthlyCalculator->setStartDate(new DateTime($membershipStartDate));
      $instalmentAmount = new InstalmentAmount($fixedPeriodTypeMonthlyCalculator);
    }
    else {
      $fixedPeriodTypeAnnualCalculator = new FixedPeriodTypeAnnualCalculator($membershipTypes);
      $fixedPeriodTypeAnnualCalculator->setStartDate(new DateTime($membershipStartDate));
      $instalmentAmount = new InstalmentAmount($fixedPeriodTypeAnnualCalculator);
    }

    $instalmentAmount->getCalculator()->calculate();

    return $instalmentAmount;
  }

}
