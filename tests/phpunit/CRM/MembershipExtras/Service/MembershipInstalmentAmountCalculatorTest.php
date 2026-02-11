<?php

use PHPUnit\Framework\TestCase;
use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as PeriodTypeCalculatorInterface;

/**
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculatorTest extends TestCase {

  /**
   * Verifies tax remainder is carried to the final instalment.
   */
  public function testLastInstalmentIncludesTaxRemainder() {
    $lineItem = new CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem();
    $lineItem->setFinancialTypeId(1);
    $lineItem->setQuantity(1);
    $lineItem->setUnitPrice(100.00);
    $lineItem->setSubTotal(100.00);
    $lineItem->setTaxRate(20.0);
    $lineItem->setTaxAmount(20.00);
    $lineItem->setTotalAmount(120.00);

    $calculator = new CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator(
      new MembershipInstalmentAmountCalculatorTestStub([$lineItem])
    );

    $regularInstalment = $calculator->calculateInstalmentAmount(12);
    $lastInstalment = $calculator->getLastInstalmentAmount();

    $this->assertNotNull($lastInstalment);
    $this->assertEquals(8.33, $regularInstalment->getAmount());
    $this->assertEquals(1.67, $regularInstalment->getTaxAmount());
    $this->assertEquals(10.00, $regularInstalment->getTotalAmount());

    $this->assertEquals(8.37, $lastInstalment->getAmount());
    $this->assertEquals(1.63, $lastInstalment->getTaxAmount());
    $this->assertEquals(10.00, $lastInstalment->getTotalAmount());
  }

}

class MembershipInstalmentAmountCalculatorTestStub implements PeriodTypeCalculatorInterface {

  /**
   * @var array
   */
  private $lineItems;

  public function __construct(array $lineItems) {
    $this->lineItems = $lineItems;
  }

  public function calculate(bool $calculateProRated = TRUE) {
  }

  public function getAmount() {
    return 0;
  }

  public function getTaxAmount() {
    return 0;
  }

  public function getTotalAmount() {
    return 0;
  }

  public function getLineItems() {
    return $this->lineItems;
  }

}
