<?php

use CRM_MembershipExtras_Helper_InstalmentSchedule as InstalmentScheduleHelper;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodTypeCalculator;
use CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator as InstalmentAmountCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator as RollingPeriodTypeCalculator;

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_AbstractProcessor {

  /**
   * Membership ID is attached to an object either Contribution or Line Item.
   *
   * The variable is declared as static variable so it can be shared between sub classes
   * as the membership ID may not exist in line item object e.g. non membership type
   * line item
   *
   * @var int
   */
  public static $membership_id;

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
   * The contribution or line item to-be-created parameters passed from the hook.
   *
   * @var array
   */
  protected $params;

  /**
   * Checks if priceset was selected on the form to create the membership.
   *
   * @throws CRM_Core_Exception
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
   *
   * @throws CRM_Core_Exception
   * @throws CiviCRM_API3_Exception
   */
  protected function assignInstalmentDetails() {
    $this->paymentPlanSchedule = CRM_Utils_Request::retrieve('payment_plan_schedule', 'String');
    $instalmentDetails = InstalmentScheduleHelper::getInstalmentDetails($this->paymentPlanSchedule, self::$membership_id);
    $this->instalmentsCount = $instalmentDetails['instalments_count'];
    $this->instalmentsFrequency = $instalmentDetails['instalments_frequency'];
    $this->instalmentsFrequencyUnit = $instalmentDetails['instalments_frequency_unit'];
  }

  protected function getInstalmentAmountCalculator(array $membershipTypes, $periodType = 'rolling') {
    if ($periodType == 'fixed') {
      $calculator = new FixedPeriodTypeCalculator($membershipTypes);
      $calculator->setStartDate(new DateTime($this->getMembership()['start_date']));
      $calculator->setEndDate(new DateTime($this->getMembership()['end_date']));
      $calculator->setJoinDate(new DateTime($this->getMembership()['join_date']));
    }
    else {
      $calculator = new RollingPeriodTypeCalculator($membershipTypes);
    }

    $instalmentAmountCalculator = new InstalmentAmountCalculator($calculator);
    $instalmentAmountCalculator->getCalculator()->calculate();

    return $instalmentAmountCalculator;
  }

  /**
   * @throws Exception
   */
  protected function getInstalmentCount($membershipType = NULL) {
    if ($this->instalmentsCount == 1) {
      return $this->instalmentsCount;
    }

    if (is_null($membershipType)) {
      $membershipType = CRM_Member_BAO_MembershipType::findById($this->getMembership()['membership_type_id']);
    }

    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, new MembershipTypeDatesCalculator());

    $startDate = new DateTime($this->getMembership()['start_date']);
    $endDate = new DateTime($this->getMembership()['end_date']);
    return $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($startDate, $endDate);
  }

  protected function getMembership() {
    return civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => self::$membership_id,
    ])['values'][0];
  }

}
