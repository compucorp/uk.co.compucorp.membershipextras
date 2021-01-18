<?php

use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeMonthlyCalculator as FixedPeriodCalculator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeMonthlyCalculatorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeMonthlyCalculatorTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;

  public function setUp() {
    $this->mockSalesTaxFinancialAccount();
  }

  /**
   * Tests calculate amounts
   */
  public function testCalculate() {
    $startDate = new DateTime('today');
    $membershipTypeParams = [
      'name' => 'abc',
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'duration_interval' => 1,
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
      'domain_id' => 1,
      'member_of_contact_id' => 1,
      'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      'minimum_fee' => 120,
    ];
    $params = array_merge($membershipTypeParams, $membershipTypeParams);
    $memType = MembershipTypeFabricator::fabricate($params);
    $membershipType = CRM_Member_BAO_MembershipType::findById($memType['id']);

    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, new MembershipTypeDatesCalculator());
    $diffInMonths = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($startDate);

    $expectedAmount = ($membershipType->minimum_fee / 12) * $diffInMonths;
    $taxCalculator = new MembershipInstalmentTaxAmountCalculator();
    $expectedTaxAmount = $taxCalculator->calculateByMembershipType($membershipType, $expectedAmount);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;

    $calculator = new FixedPeriodCalculator([$membershipType]);
    $calculator->setStartDate($startDate);
    $calculator->calculate();
    $this->assertEquals($expectedAmount, $calculator->getAmount());
    $this->assertEquals($expectedTaxAmount, $calculator->getTaxAmount());
    $this->assertEquals($expectedTotalAmount, $calculator->getTotalAmount());
  }

}
