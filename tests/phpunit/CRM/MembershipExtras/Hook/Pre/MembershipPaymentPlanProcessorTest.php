<?php

use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor as MembershipPaymentPlanProcessor;

/**
 * CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessorTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;

  /**
   * Tests create payment plan with month duration
   * for rolling membership type with monthly schedule
   */
  public function testCreatePaymentPlanWithMonthDurationRollingMembershipTypeWithMonthlySchedule() {
    $_REQUEST['payment_plan_schedule'] = 'monthly';
    $params = $this->mockFormParams('rolling', 'month');
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->createPaymentPlan();
    $createdPaymentPlan = $this->getCreatedPaymentPlan($processor);
    $this->assertEquals(1, $createdPaymentPlan['installments']);
    $this->assertEquals($params['total_amount'], $createdPaymentPlan['amount']);
  }

  /**
   * Tests create payment plan with year duration
   * for rolling membership type with monthly schedule
   */
  public function testCreatePaymentPlanWithYearDurationRollingMembershipTypeWithMonthlySchedule() {
    $_REQUEST['payment_plan_schedule'] = 'monthly';
    $params = $this->mockFormParams('rolling', 'year');
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->createPaymentPlan();
    $createdPaymentPlan = $this->getCreatedPaymentPlan($processor);
    $this->assertEquals(12, $createdPaymentPlan['installments']);
    $this->assertEquals($params['total_amount'], $createdPaymentPlan['amount']);
  }

  /**
   * Tests create payment plan with year duration
   * for rolling membership type with quarterly schedule
   */
  public function testCreatePaymentPlanWithYearDurationRollingMembershipTypeAndQuarterlySchedule() {
    $_REQUEST['payment_plan_schedule'] = 'quarterly';
    $params = $this->mockFormParams('rolling', 'year');
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->createPaymentPlan();
    $createdPaymentPlan = $this->getCreatedPaymentPlan($processor);
    $this->assertEquals(4, $createdPaymentPlan['installments']);
    $this->assertEquals($params['total_amount'], $createdPaymentPlan['amount']);
  }

  /**
   * Tests create payment plan with year duration
   * for rolling membership type with annual schedule
   */
  public function testCreatePaymentPlanWithYearDurationRollingMembershipTypeAndAnnualSchedule() {
    $_REQUEST['payment_plan_schedule'] = 'annual';
    $params = $this->mockFormParams('rolling', 'year');
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->createPaymentPlan();
    $createdPaymentPlan = $this->getCreatedPaymentPlan($processor);
    $this->assertEquals(1, $createdPaymentPlan['installments']);
    $this->assertEquals($params['total_amount'], $createdPaymentPlan['amount']);
  }

  /**
   * Tests create payment plan with year duration
   * for fixed membership type with monthly schedule
   */
  public function testCreatePaymentPlanWithYearDurationFixedMembershipTypeAndMonthlySchedule() {
    $_REQUEST['payment_plan_schedule'] = 'monthly';
    $params = $this->mockFormParams('fixed', 'year');
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->createPaymentPlan();
    $createdPaymentPlan = $this->getCreatedPaymentPlan($processor);
    $this->assertEquals(12, $createdPaymentPlan['installments']);
    $this->assertEquals($params['total_amount'], $createdPaymentPlan['amount']);
  }

  /**
   * Tests create payment plan with year duration
   * for fixed membership type with annual schedule
   */
  public function testCreatePaymentPlanWithYearDurationFixMembershipTypeAndAnnualSchedule() {
    $_REQUEST['payment_plan_schedule'] = 'annual';
    $params = $this->mockFormParams('fixed', 'year');
    $processor = new MembershipPaymentPlanProcessor($params);
    $processor->createPaymentPlan();
    $createdPaymentPlan = $this->getCreatedPaymentPlan($processor);
    $this->assertEquals(1, $createdPaymentPlan['installments']);
    $this->assertEquals($params['total_amount'], $createdPaymentPlan['amount']);
  }

  /**
   * Get payment plan from reflection object
   *
   * @param CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor $processor
   * @return mixed
   */
  private function getCreatedPaymentPlan(MembershipPaymentPlanProcessor $processor) {
    $ref = new ReflectionObject($processor);
    $recurringContributionProperty = $ref->getProperty('recurringContribution');
    $recurringContributionProperty->setAccessible(TRUE);

    return $recurringContributionProperty->getValue($processor);
  }

  /**
   * Mock contribution params as the membership form is submitted.
   *
   * @param $membershipPeriodType
   * @param $durationUnit
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  private function mockFormParams($membershipPeriodType, $durationUnit) {
    $this->mockSalesTaxFinancialAccount();
    $contact = ContactFabricator::fabricate();
    $startDate = date('Y-m-d');
    $membershipType = $membershipType = MembershipTypeFabricator::fabricate([
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
    $membership = MembershipFabricator::fabricate([
      'contact_id' => $contact['id'],
      'membership_type_id' => $membershipType['id'],
      'join_date' => $startDate,
      'start_date' => $startDate,
    ]);
    $financialTypeId = $this->getFinancialTypeID('Member Dues');
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = CRM_Utils_Array::value($financialTypeId, $taxRates, 0);
    $taxAmount  = ($membershipType['minimum_fee'] * $rate) / 100;
    $totalAmount = $membershipType['minimum_fee'] + $taxAmount;
    return [
      'currency' => 'GBP',
      'receipt_date' => NULL,
      'source' => NULL,
      'non_deductible_amount' => 0,
      'skipCleanMoney' => 1,
      'payment_processor' => NULL,
      'contact_id' => $contact['id'],
      'fee_amount' => 0,
      'total_amount' => $totalAmount,
      'receive_date' => $startDate,
      'financial_type_id' => $financialTypeId,
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
  }

}
