<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_AbstractBaseTest as BaseTest;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodCalculatorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodCalculatorTest extends BaseTest {

  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;

  public function setUp() {
    $this->mockSalesTaxFinancialAccount();
  }

  public function testCalculateProRataByMonth() {
    $startDate = new DateTime('today');
    $membershipType = $this->fabricateMembeshipType(['name' => 'xyz']);
    $this->mockSettings($membershipType->id, FixedPeriodCalculator::BY_MONTHS);

    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, new MembershipTypeDatesCalculator());
    $diffInMonths = $membershipTypeDurationCalculator->calculateMonthForAnnualDurationBasedOnDates($startDate);

    $expectedAmount = ($membershipType->minimum_fee / 12) * $diffInMonths;
    $expectedTaxAmount = $this->getTaxAmount($membershipType, $expectedAmount);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;

    $calculator = new FixedPeriodCalculator([$membershipType]);
    $calculator->calculate($startDate);
    $this->assertEquals($expectedAmount, $calculator->getAmount());
    $this->assertEquals($expectedTaxAmount, $calculator->getTaxAmount());
    $this->assertEquals($expectedTotalAmount, $calculator->getTotalAmount());

  }

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
    $calculator->calculate($startDate);
    $this->assertEquals($expectedAmount, $calculator->getAmount());
    $this->assertEquals($expectedTaxAmount, $calculator->getTaxAmount());
    $this->assertEquals($expectedTotalAmount, $calculator->getTotalAmount());

  }

}
