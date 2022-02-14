<?php

use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contribution as ContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;
use CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_LineItem as MembershipPaymentPlanProcessor;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodCalculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_LineItemTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_LineItemTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;
  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;

  private $priceSet;
  /**
   * @var mixed
   */
  private $membership;

  public function setUp() {
    //We mocked membership ID as the membership ID is always when
    //Contribution pre_process hook is called.
    MembershipPaymentPlanProcessor::$membership_id = $this->membership['id'];;
  }

  /**
   * Tests pro rated price set contribution line item on calucation by month for fixed period membership type.
   *
   * @throws Exception
   */
  public function testProRatedPriceSetContributionLineItemOnCalculationByMonthFixedMembershipType() {
    $params = $this->mockParams('fixed', 'year', FixedPeriodCalculator::BY_MONTHS);
    //Since we test price set, line item amount can be different
    //from membership type that attached to price field value
    //the line total is changed here to test if the hook
    //is working correct with different price.
    $params['line_total'] = 240;

    $memTypeObj = CRM_Member_BAO_MembershipType::findById($params['membership_type_id']);
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($memTypeObj, new MembershipTypeDatesCalculator());
    $diffInMonths = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates(new DateTime($this->membership['start_date']));
    $expectedLineToTal = MoneyUtilities::roundToPrecision($params['line_total'] / 12 * $diffInMonths, 2);
    $expectedTaxAmount = MoneyUtilities::roundToPrecision(($params['tax_rate'] / 100) * $expectedLineToTal, 2);

    $_REQUEST['price_set_id'] = $this->priceSet['id'];
    $_REQUEST['payment_plan_schedule'] = 'annual';
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->alterLineItemParameters();

    $this->assertEquals($expectedLineToTal, $params['line_total']);
    $this->assertEquals($expectedTaxAmount, $params['tax_amount']);
  }

  /**
   * Tests pro rated price set contribuiton line item on calucation by days for fixed period membership type.
   *
   * @throws Exception
   */
  public function testProRatedPriceSetContributionLineItemOnCalculationByDaysFixedMembershipType() {
    $params = $this->mockParams('fixed', 'year', FixedPeriodCalculator::BY_DAYS);
    $memTypeObj = CRM_Member_BAO_MembershipType::findById($params['membership_type_id']);
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($memTypeObj, new MembershipTypeDatesCalculator());
    $membershipTypeDurationInDays = $membershipTypeDurationCalculator->calculateOriginalInDays();
    $diffInDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates(new DateTime($this->membership['start_date']));
    $expectedLineToTal = MoneyUtilities::roundToPrecision(($params['line_total'] / $membershipTypeDurationInDays) * $diffInDays, 2);
    $expectedTaxAmount = MoneyUtilities::roundToPrecision(($params['tax_rate'] / 100) * $expectedLineToTal, 2);

    $_REQUEST['price_set_id'] = $this->priceSet['id'];
    $_REQUEST['payment_plan_schedule'] = 'annual';
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->alterLineItemParameters();

    $this->assertEquals($expectedLineToTal, MoneyUtilities::roundToPrecision($params['line_total'], 2));
    $this->assertEquals($expectedTaxAmount, MoneyUtilities::roundToPrecision($params['tax_amount'], 2));
  }

  /**
   * Tests alter non price set line item for fixed period membership type.
   *
   * @throws Exception
   */
  public function testAlterNonPriceSetLineItemForFixedMembershipType() {
    $defaultPriceSet = $this->getDefaultMembershipTypeAmountPriceSet();
    $params = $this->mockParams('fixed', 'year', FixedPeriodCalculator::BY_DAYS, $defaultPriceSet);

    $memTypeObj = CRM_Member_BAO_MembershipType::findById($params['membership_type_id']);
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($memTypeObj, new MembershipTypeDatesCalculator());
    $membershipTypeDurationInDays = $membershipTypeDurationCalculator->calculateOriginalInDays();
    $diffInDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates(new DateTime($this->membership['start_date']));

    //Make sure we pro rated the line item amounts before calling processor for default membership type price set
    //as when we select non price set price on the membership sign up form, the default line item will be used
    //and the price will already be pro prorated as per total amount thus we need to do there to make sure that
    //the processor will calculate line item amounts correctly.
    $amount = $params['line_total'];
    $params['line_total'] = MoneyUtilities::roundToPrecision(($amount / $membershipTypeDurationInDays) * $diffInDays, 2);
    $params['unit_price'] = $params['line_total'];
    $params['tax_amount'] = MoneyUtilities::roundToPrecision(($params['tax_rate'] / 100) * $params['line_total'], 2);

    //diffInMonths = number of instalment as we are paying monthly
    $diffInMonths = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates(new DateTime($this->membership['start_date']));
    $expectedLineToTal = MoneyUtilities::roundToPrecision($params['line_total'] / $diffInMonths, 2);
    $expectedTaxAmount = MoneyUtilities::roundToPrecision($params['tax_amount'] / $diffInMonths, 2);

    //Make sure we unset $_REQUEST array before calling processor
    unset($_REQUEST);
    $_REQUEST['payment_plan_schedule'] = 'monthly';
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->alterLineItemParameters();

    $this->assertEquals($expectedLineToTal, $params['line_total']);
    $this->assertEquals($expectedTaxAmount, $params['tax_amount']);
  }

  /**
   * Tests alter line itiem for rolling period membership type.
   */
  public function testAlterLineItemForRollingMembershipType() {
    $defaultPriceSet = $this->getDefaultMembershipTypeAmountPriceSet();
    $params = $this->mockParams('rolling', 'year', NULL, $defaultPriceSet);

    //12 = number of instalment as we are paying montthly
    $expectedLineToTal = MoneyUtilities::roundToPrecision($params['line_total'] / 12, 2);
    $expectedTaxAmount = MoneyUtilities::roundToPrecision($params['tax_amount'] / 12, 2);

    $_REQUEST['payment_plan_schedule'] = 'monthly';
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->alterLineItemParameters();

    $this->assertEquals($expectedLineToTal, $params['line_total']);
    $this->assertEquals($expectedTaxAmount, $params['tax_amount']);
  }

  /**
   * @param $membershipPeriodType
   * @param $membershipTypeDuration
   * @param null $membershipTypeSetting
   * @param null $priceSet
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  private function mockParams($membershipPeriodType, $membershipTypeDuration, $membershipTypeSetting = NULL, $priceSet = NULL) {
    $this->mockSalesTaxFinancialAccount();
    $contact = ContactFabricator::fabricate();
    $membershipType = $this->mockMembershipType($membershipPeriodType, $membershipTypeDuration, $membershipTypeSetting);
    $this->membership = $this->mockMembership($contact['id'], $membershipType['id'], date('Y-m-d'));
    $contribution = $this->mockContribution($this->membership, $membershipType);

    if (is_null($priceSet)) {
      $this->priceSet = $this->mockPriceSet();
      $priceField = $this->mockPriceField($this->priceSet['id'], 'price field for price set ' . $priceSet['id']);
    }
    else {
      $priceField = $this->mockPriceField($priceSet['id']);
    }

    $priceFieldValue = $this->mockPriceFieldValue($priceField['id'], $membershipType['id'], $membershipType['minimum_fee']);
    $rate = $this->getMemberDuesTaxRate();

    return [
      'price_field_id' => $priceField['id'],
      'price_field_value_id' => $priceFieldValue['id'],
      'label' => $priceFieldValue['label'],
      'field_title' => $priceFieldValue['label'],
      'qty' => 1,
      'unit_price' => $priceFieldValue['amount'],
      'line_total' => $priceFieldValue['amount'],
      'membership_type_id' => $membershipType['id'],
      'membership_num_terms' => 1,
      'auto_renew' => 1,
      'html_type' => $priceField['html_type'],
      'financial_type_id' => 2,
      'tax_amount' => ($priceFieldValue['amount'] * $rate) / 100,
      'non_deductible_amount' => 0.00,
      'tax_rate' => $rate,
      'contribution_id' => $contribution['id'],
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->membership['id'],
    ];
  }

  /**
   * @param $membershipPeriodType
   * @param $durationUnit
   * @param $setting
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockMembershipType($membershipPeriodType, $durationUnit, $setting) {
    $memType = MembershipTypeFabricator::fabricate([
      'name' => 'Mock Membership type',
      'period_type' => $membershipPeriodType,
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => $durationUnit,
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
    ]);
    if (isset($setting)) {
      $this->mockSettings($memType['id'], $setting);
    }

    return $memType;
  }

  /**
   * @param $contactID
   * @param $membershipTypeID
   * @param $startDate
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockMembership($contactID, $membershipTypeID, $startDate) {
    return MembershipFabricator::fabricate([
      'contact_id' => $contactID,
      'membership_type_id' => $membershipTypeID,
      'join_date' => $startDate,
      'start_date' => $startDate,
    ]);
  }

  /**
   * @param $membership
   * @param $membershipType
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockContribution($membership, $membershipType) {
    $rate = $this->getMemberDuesTaxRate();
    $taxAmount  = ($membershipType['minimum_fee'] * $rate) / 100;
    $totalAmount = $membershipType['minimum_fee'] + $taxAmount;
    $params = [
      'currency' => 'GBP',
      'receipt_date' => NULL,
      'source' => NULL,
      'non_deductible_amount' => 0,
      'skipCleanMoney' => 1,
      'payment_processor' => NULL,
      'contact_id' => $membership['contact_id'],
      'fee_amount' => 0,
      'total_amount' => $totalAmount,
      'receive_date' => $membership['start_date'],
      'financial_type_id' => $this->getFinancialTypeId('Member Dues'),
      'payment_instrument_id' => 4,
      'trxn_id' => NULL,
      'invoice_id' => NULL,
      'is_test' => NULL,
      'contribution_status_id]' => 2,
      'check_number' => NULL,
      'campaign_id' => NULL,
      'is_pay_later' => 1,
      'membership_id' => $membership['id'],
      'tax_amount' => $taxAmount,
      'skipLineItem' => 1,
      'contribution_recur_id' => NULL,
      'pan_truncation' => NULL,
      'card_type_id' => NULL,
    ];

    return ContributionFabricator::fabricate($params);
  }

  /**
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceSet() {
    $priceSetParams = [
      'name' => "test_price_set",
      'extends' => "CiviMember",
      'financial_type_id' => "Member Dues",
      'is_active' => 1,
    ];

    return PriceSetFabricator::fabricate($priceSetParams);
  }

  /**
   * @param $priceSetId
   * @param string $priceFieldLabel
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceField($priceSetId, $priceFieldLabel = 'Membership Amount') {
    return PriceFieldFabricator::fabricate([
      'price_set_id' => $priceSetId,
      'label' => "$priceFieldLabel",
      'name' => "price_field_1",
      'html_type' => "Radio",
    ]);
  }

  /**
   *
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceFieldValue($priceFieldId, $membershipTypeId, $priceFieldAmount) {
    return PriceFieldValueFabricator::fabricate([
      'price_field_id' => $priceFieldId,
      'label' => "Price Field Value with Membership Type " . $membershipTypeId,
      'amount' => $priceFieldAmount,
      'membership_type_id' => $membershipTypeId,
      'financial_type_id' => "Member Dues",
    ]);
  }

  /**
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function getMemberDuesTaxRate() {
    $financialTypeId = $this->getFinancialTypeID('Member Dues');
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    return CRM_Utils_Array::value($financialTypeId, $taxRates, 0);
  }

  /**
   * Obtains ID for the given financial type name.
   *
   * @param $financialType
   *
   * @return int|array
   * @throws \CiviCRM_API3_Exception
   */
  private function getFinancialTypeID($financialType) {
    return civicrm_api3('FinancialType', 'getvalue', [
      'return' => 'id',
      'name' => $financialType,
    ]);
  }

  /**
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function getDefaultMembershipTypeAmountPriceSet() {
    return civicrm_api3('PriceSet', 'get', [
      'sequential' => 1,
      'name' => "default_membership_type_amount",
    ])['values'][0];
  }

}
