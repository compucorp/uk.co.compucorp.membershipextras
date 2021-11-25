<?php

use CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodTypeCalculator as RollingPeriodCalculator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodCalculatorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipPeriodType_RollingPeriodCalculatorTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;

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
