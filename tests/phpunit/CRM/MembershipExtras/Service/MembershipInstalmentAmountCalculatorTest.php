<?php

use PHPUnit\Framework\TestCase;
use CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface as PeriodTypeCalculatorInterface;

/**
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculatorTest extends TestCase {

  /**
   * Verifies tax remainder is carried to the final instalment.
   *
   * £100 + 20% VAT = £120, 12 monthly instalments.
   * Regular: sub=8.33, tax=1.67, total=10.00
   * Last:    sub=8.37, tax=1.63, total=10.00
   */
  public function testLastInstalmentIncludesTaxRemainder() {
    $lineItem = $this->createLineItem(100.00, 100.00, 20.0, 20.00, 120.00);

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

  /**
   * Verifies sub-total remainder without tax.
   *
   * £685 no tax, 12 monthly instalments.
   * Regular: 57.08, Last: 57.12 (685 - 57.08×11 = 57.12)
   */
  public function testLastInstalmentIncludesSubTotalRemainderWithoutTax() {
    $lineItem = $this->createLineItem(685.00, 685.00, 0.0, 0.00, 685.00);

    $calculator = new CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator(
      new MembershipInstalmentAmountCalculatorTestStub([$lineItem])
    );

    $regularInstalment = $calculator->calculateInstalmentAmount(12);
    $lastInstalment = $calculator->getLastInstalmentAmount();

    $this->assertNotNull($lastInstalment);
    $this->assertEquals(57.08, $regularInstalment->getAmount());
    $this->assertEquals(0.00, $regularInstalment->getTaxAmount());
    $this->assertEquals(57.08, $regularInstalment->getTotalAmount());

    $this->assertEquals(57.12, $lastInstalment->getAmount());
    $this->assertEquals(0.00, $lastInstalment->getTaxAmount());
    $this->assertEquals(57.12, $lastInstalment->getTotalAmount());

    // Verify totals reconstruct to original amount.
    $totalAmount = ($regularInstalment->getTotalAmount() * 11) + $lastInstalment->getTotalAmount();
    $this->assertEquals(685.00, round($totalAmount, 2));
  }

  /**
   * Verifies both sub-total and tax remainder with VAT.
   *
   * £685 + 20% VAT = £822, 12 monthly instalments.
   * Regular: sub=57.08, tax=11.42, total=68.50
   * Last:    sub=57.12, tax=11.38, total=68.50
   */
  public function testLastInstalmentIncludesSubTotalAndTaxRemainderWithVAT() {
    $lineItem = $this->createLineItem(685.00, 685.00, 20.0, 137.00, 822.00);

    $calculator = new CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator(
      new MembershipInstalmentAmountCalculatorTestStub([$lineItem])
    );

    $regularInstalment = $calculator->calculateInstalmentAmount(12);
    $lastInstalment = $calculator->getLastInstalmentAmount();

    $this->assertNotNull($lastInstalment);
    $this->assertEquals(57.08, $regularInstalment->getAmount());
    $this->assertEquals(11.42, $regularInstalment->getTaxAmount());
    $this->assertEquals(68.50, $regularInstalment->getTotalAmount());

    $this->assertEquals(57.12, $lastInstalment->getAmount());
    $this->assertEquals(11.38, $lastInstalment->getTaxAmount());
    $this->assertEquals(68.50, $lastInstalment->getTotalAmount());

    // Verify totals reconstruct to original amounts.
    $totalSub = ($regularInstalment->getAmount() * 11) + $lastInstalment->getAmount();
    $totalTax = ($regularInstalment->getTaxAmount() * 11) + $lastInstalment->getTaxAmount();
    $this->assertEquals(685.00, round($totalSub, 2));
    $this->assertEquals(137.00, round($totalTax, 2));
  }

  /**
   * Verifies even split produces identical regular and last instalments.
   *
   * £50 no tax, 4 quarterly instalments.
   * All instalments: 12.50 (50/4 = 12.50 exactly)
   */
  public function testEvenSplitQuarterlyProducesSameRegularAndLastInstalment() {
    $lineItem = $this->createLineItem(50.00, 50.00, 0.0, 0.00, 50.00);

    $calculator = new CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator(
      new MembershipInstalmentAmountCalculatorTestStub([$lineItem])
    );

    $regularInstalment = $calculator->calculateInstalmentAmount(4);
    $lastInstalment = $calculator->getLastInstalmentAmount();

    $this->assertNotNull($lastInstalment);
    $this->assertEquals(12.50, $regularInstalment->getAmount());
    $this->assertEquals(12.50, $lastInstalment->getAmount());
    $this->assertEquals(0.00, $regularInstalment->getTaxAmount());
    $this->assertEquals(0.00, $lastInstalment->getTaxAmount());
    $this->assertEquals(12.50, $regularInstalment->getTotalAmount());
    $this->assertEquals(12.50, $lastInstalment->getTotalAmount());
  }

  /**
   * Verifies quarterly split with VAT where amounts divide evenly.
   *
   * £100 + 20% VAT = £120, 4 quarterly instalments.
   * All instalments: sub=25.00, tax=5.00, total=30.00
   */
  public function testQuarterlyWithVATEvenSplit() {
    $lineItem = $this->createLineItem(100.00, 100.00, 20.0, 20.00, 120.00);

    $calculator = new CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator(
      new MembershipInstalmentAmountCalculatorTestStub([$lineItem])
    );

    $regularInstalment = $calculator->calculateInstalmentAmount(4);
    $lastInstalment = $calculator->getLastInstalmentAmount();

    $this->assertNotNull($lastInstalment);
    $this->assertEquals(25.00, $regularInstalment->getAmount());
    $this->assertEquals(5.00, $regularInstalment->getTaxAmount());
    $this->assertEquals(30.00, $regularInstalment->getTotalAmount());

    $this->assertEquals(25.00, $lastInstalment->getAmount());
    $this->assertEquals(5.00, $lastInstalment->getTaxAmount());
    $this->assertEquals(30.00, $lastInstalment->getTotalAmount());
  }

  /**
   * Verifies each line item's remainder is calculated independently.
   *
   * Line 1: £100 + 20% VAT = £120, Line 2: £50 + 20% VAT = £60
   * 12 monthly instalments.
   * Line 1 regular: sub=8.33, tax=1.67; last: sub=8.37, tax=1.63
   * Line 2 regular: sub=4.17, tax=0.83; last: sub=4.13, tax=0.87
   */
  public function testMultipleLineItemsHandleRemaindersIndependently() {
    $lineItem1 = $this->createLineItem(100.00, 100.00, 20.0, 20.00, 120.00);
    $lineItem2 = $this->createLineItem(50.00, 50.00, 20.0, 10.00, 60.00);

    $calculator = new CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator(
      new MembershipInstalmentAmountCalculatorTestStub([$lineItem1, $lineItem2])
    );

    $regularInstalment = $calculator->calculateInstalmentAmount(12);
    $lastInstalment = $calculator->getLastInstalmentAmount();

    $this->assertNotNull($lastInstalment);

    // Combined regular: sub=8.33+4.17=12.50, tax=1.67+0.83=2.50, total=15.00
    $this->assertEquals(12.50, $regularInstalment->getAmount());
    $this->assertEquals(2.50, $regularInstalment->getTaxAmount());
    $this->assertEquals(15.00, $regularInstalment->getTotalAmount());

    // Combined last: sub=8.37+4.13=12.50, tax=1.63+0.87=2.50, total=15.00
    $this->assertEquals(12.50, $lastInstalment->getAmount());
    $this->assertEquals(2.50, $lastInstalment->getTaxAmount());
    $this->assertEquals(15.00, $lastInstalment->getTotalAmount());

    // Verify individual line items on last instalment.
    $lastLineItems = $lastInstalment->getLineItems();
    $this->assertCount(2, $lastLineItems);
    $this->assertEquals(8.37, $lastLineItems[0]->getSubTotal());
    $this->assertEquals(1.63, $lastLineItems[0]->getTaxAmount());
    $this->assertEquals(4.13, $lastLineItems[1]->getSubTotal());
    $this->assertEquals(0.87, $lastLineItems[1]->getTaxAmount());

    // Verify totals reconstruct to original amounts.
    $totalSub = ($regularInstalment->getAmount() * 11) + $lastInstalment->getAmount();
    $totalTax = ($regularInstalment->getTaxAmount() * 11) + $lastInstalment->getTaxAmount();
    $this->assertEquals(150.00, round($totalSub, 2));
    $this->assertEquals(30.00, round($totalTax, 2));
  }

  /**
   * Verifies single instalment returns full amount with no splitting.
   *
   * £685 no tax, 1 annual instalment.
   * Regular and last are both 685.00.
   */
  public function testSingleInstalmentReturnsFullAmountWithNoSplitting() {
    $lineItem = $this->createLineItem(685.00, 685.00, 0.0, 0.00, 685.00);

    $calculator = new CRM_MembershipExtras_Service_MembershipInstalmentAmountCalculator(
      new MembershipInstalmentAmountCalculatorTestStub([$lineItem])
    );

    $regularInstalment = $calculator->calculateInstalmentAmount(1);
    $lastInstalment = $calculator->getLastInstalmentAmount();

    $this->assertNotNull($lastInstalment);
    $this->assertEquals(685.00, $regularInstalment->getAmount());
    $this->assertEquals(0.00, $regularInstalment->getTaxAmount());
    $this->assertEquals(685.00, $regularInstalment->getTotalAmount());

    $this->assertEquals(685.00, $lastInstalment->getAmount());
    $this->assertEquals(0.00, $lastInstalment->getTaxAmount());
    $this->assertEquals(685.00, $lastInstalment->getTotalAmount());
  }

  /**
   * Creates a ScheduleInstalmentLineItem with the given amounts.
   */
  private function createLineItem($unitPrice, $subTotal, $taxRate, $taxAmount, $totalAmount) {
    $lineItem = new CRM_MembershipExtras_DTO_ScheduleInstalmentLineItem();
    $lineItem->setFinancialTypeId(1);
    $lineItem->setQuantity(1);
    $lineItem->setUnitPrice($unitPrice);
    $lineItem->setSubTotal($subTotal);
    $lineItem->setTaxRate($taxRate);
    $lineItem->setTaxAmount($taxAmount);
    $lineItem->setTotalAmount($totalAmount);

    return $lineItem;
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
