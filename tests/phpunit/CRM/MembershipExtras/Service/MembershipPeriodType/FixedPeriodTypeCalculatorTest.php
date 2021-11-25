<?php

use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodCalculator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculatorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculatorTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;
  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;

  public function setUp() {
    $this->mockSalesTaxFinancialAccount();
  }

  /**
   * Test calculate pro-rata by month
   *
   * @throws Exception
   */
  public function testCalculateProRataByMonth() {
    $startDate = new DateTime('today');
    $membershipType = $this->fabricateMembeshipType(['name' => 'xyz']);
    $this->mockSettings($membershipType->id, FixedPeriodCalculator::BY_MONTHS);

    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, new MembershipTypeDatesCalculator());
    $diffInMonths = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($startDate);

    $expectedAmount = ($membershipType->minimum_fee / 12) * $diffInMonths;
    $expectedTaxAmount = $this->getTaxAmount($membershipType, $expectedAmount);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;
    $calculator = new FixedPeriodCalculator([$membershipType]);
    $calculator->setStartDate($startDate);
    $calculator->calculate();
    $this->assertEquals($expectedAmount, $calculator->getAmount());
    $this->assertEquals($expectedTaxAmount, $calculator->getTaxAmount());
    $this->assertEquals($expectedTotalAmount, $calculator->getTotalAmount());
    $this->assertNotEmpty($calculator->getLineItems());
    $this->assertEquals($diffInMonths, $calculator->getProRatedNumber());
    $this->assertEquals(FixedPeriodCalculator::BY_MONTHS, $calculator->getProRatedUnit());
  }

  /**
   * Tests calculate pro-rata by days
   *
   * @throws Exception
   */
  public function testCalculateProRataByDays() {
    $startDate = new DateTime('today');
    $membershipType = $this->fabricateMembeshipType(['name' => 'xyz']);
    $this->mockSettings($membershipType->id, FixedPeriodCalculator::BY_DAYS);

    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, new MembershipTypeDatesCalculator());
    $membershipTypeDurationInDays = $membershipTypeDurationCalculator->calculateOriginalInDays();
    $diffInDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($startDate);
    $expectedAmount = ($membershipType->minimum_fee / $membershipTypeDurationInDays) * $diffInDays;
    $expectedTaxAmount = $this->getTaxAmount($membershipType, $expectedAmount);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;
    $calculator = new FixedPeriodCalculator([$membershipType]);
    $calculator->setStartDate($startDate);
    $calculator->calculate();
    $this->assertEquals($expectedAmount, $calculator->getAmount());
    $this->assertEquals($expectedTaxAmount, $calculator->getTaxAmount());
    $this->assertEquals($expectedTotalAmount, $calculator->getTotalAmount());
    $this->assertNotEmpty($calculator->getLineItems());
    $this->assertEquals($diffInDays, $calculator->getProRatedNumber());
    $this->assertEquals(FixedPeriodCalculator::BY_DAYS, $calculator->getProRatedUnit());
  }

  protected function fabricateMembeshipType($params = []) {
    $defaultMembershipTypeParams = [
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
    $params = array_merge($defaultMembershipTypeParams, $params);
    $membershipType = MembershipTypeFabricator::fabricate($params);
    return CRM_Member_BAO_MembershipType::findById($membershipType['id']);
  }

  protected function getTaxAmount($membershipType, $amount = NULL) {
    $taxCalculator = new MembershipInstalmentTaxAmountCalculator();

    return $taxCalculator->calculateByMembershipType($membershipType, $amount);
  }

}
