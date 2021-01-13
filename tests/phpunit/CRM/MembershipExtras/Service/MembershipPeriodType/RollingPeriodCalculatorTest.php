<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_AbstractBaseTest as BaseTest;
use CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator as RollingPeriodCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodCalculatorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodCalculatorTest extends BaseTest {

  /**
   * Tests Rolling Period Amounts
   *
   * @throws Exception
   */
  public function testRollingPeriodAmounts() {
    $this->mockSalesTaxFinancialAccount();

    $membershipTypeAbc = $this->fabricateMembeshipType([
      'period_type' => 'rolling',
      'name' => 'abc',
      'minimum_fee' => 120,
    ]);
    $membershipTypeXyz = $this->fabricateMembeshipType([
      'period_type' => 'rolling',
      'name' => 'xyz',
      'minimum_fee' => 240,
    ]);

    $expectedAmount = $membershipTypeAbc->minimum_fee + $membershipTypeXyz->minimum_fee;
    $expectedTaxAmount = $this->getTaxAmount($membershipTypeAbc) + $this->getTaxAmount($membershipTypeXyz);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;
    $calculator = new RollingPeriodCalculator([$membershipTypeAbc, $membershipTypeXyz]);
    $calculator->calculate();
    $this->assertEquals($expectedAmount, $calculator->getAmount());
    $this->assertEquals($expectedTaxAmount, $calculator->getTaxAmount());
    $this->assertEquals($expectedTotalAmount, $calculator->getTotalAmount());
  }

}
