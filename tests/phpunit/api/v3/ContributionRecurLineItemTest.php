<?php

/**
 * Class api_v3_PaymentSchedule_ContributionRecurLineItemTest
 *
 * @group headless
 */
class api_v3_PaymentSchedule_ContributionRecurLineItemTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;

  const DONATION_FINANCIAL_TYPE_ID = 1;

  public function testCalculateTaxAndTotalAmountForAFinancialTypeWithoutSalesTax() {
    $result = civicrm_api3('ContributionRecurLineItem', 'calculatetaxamount', [
      'amount_exc_tax' => 100.56,
      'financial_type_id' => self::DONATION_FINANCIAL_TYPE_ID,
    ]);
    $this->assertEquals(100.56, $result['total_amount']);
    $this->assertEquals(0, $result['tax_amount']);
  }

  public function testCalculateTaxAndTotalAmountForAFinancialTypeWithSalesTax() {
    $this->mockSalesTaxFinancialAccount(10, 'Donation');

    $result = civicrm_api3('ContributionRecurLineItem', 'calculatetaxamount', [
      'amount_exc_tax' => 100.56,
      'financial_type_id' => self::DONATION_FINANCIAL_TYPE_ID,
    ]);
    $this->assertEquals(110.62, $result['total_amount']);
    $this->assertEquals(10.06, $result['tax_amount']);
  }

}
