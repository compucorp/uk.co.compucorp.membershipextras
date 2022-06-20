<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as MembershipInstalmentsSchedule;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment as InvalidMembershipTypeInstalment;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipInstalmentsScheduleTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipInstalmentsScheduleTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;
  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;
  use CRM_MembershipExtras_Test_Helper_PaymentMethodTrait;

  /**
   * Defatuls tax rate
   */
  const TAX_RATE = 20;

  /**
   * Defaults Params for Rolling Membership Type
   * @var array
   */
  private $defaultRollingMembershipTypeParams = [
    'duration_unit' => 'year',
    'period_type' => 'rolling',
    'duration_interval' => 1,
    'domain_id' => 1,
    'member_of_contact_id' => 1,
  ];

  /**
   * Tests getting instalment for one month duration unit for rolling membership type
   */
  public function testOneMonthUnitRollingMembershipType() {
    $rollingOneMonthType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams, [
      'duration_unit' => 'month',
      'duration_interval' => 1,
      'name' => 'xyz',
      'period_type' => 'rolling',
    ]));
    $membershipType = CRM_Member_BAO_MembershipType::findById($rollingOneMonthType['id']);
    $schedule = $this->getMembershipSchedule([$membershipType], MembershipInstalmentsSchedule::MONTHLY);
    //Expected instalment equals 1 for 1 month duration
    $this->assertCount(1, $schedule['instalments']);
  }

  /**
   * Tests exception is thrown when membership type duration unit is one (1) lifetime
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsOneLifeTime() {
    $invalidMembershipType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'lifetime', 'duration_interval' => 1, 'name' => 'xyz', 'period_type' => 'rolling']
    ));

    $membershipType = CRM_Member_BAO_MembershipType::findById($invalidMembershipType['id']);
    $this->expectException(InvalidMembershipTypeInstalment::class);
    $this->expectExceptionMessage(InvalidMembershipTypeInstalment::LIFETIME_DURATION);
    $this->getMembershipInstalmentsSchedule([$membershipType], MembershipInstalmentsSchedule::ANNUAL);
  }

  /**
   * Tests getting instalment for multi year duration unit for rolling membership type with monthly schedule.
   */
  public function testMultiYearUnitRollingMembershipTypeMonthlySchedule() {
    $interval = rand(1, 3);
    $rollingOneMonthType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams, [
      'duration_unit' => 'year',
      'duration_interval' => $interval,
      'name' => 'xyz',
      'period_type' => 'rolling',
    ]));
    $membershipType = CRM_Member_BAO_MembershipType::findById($rollingOneMonthType['id']);
    $schedule = $this->getMembershipSchedule([$membershipType], MembershipInstalmentsSchedule::MONTHLY);
    //Expected instalment equals membership type interval * schedule, e.g. 12 instalments for 1 year duration
    $this->assertCount($interval * 12, $schedule['instalments']);
  }

  /**
   * Tests getting instalment for multi year duration unit for rolling membership type with monthly schedule.
   */
  public function testMultiYearUnitRollingMembershipTypeQuarterlySchedule() {
    $interval = rand(1, 3);
    $rollingOneMonthType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams, [
      'duration_unit' => 'year',
      'duration_interval' => $interval,
      'name' => 'xyz',
      'period_type' => 'rolling',
    ]));
    $membershipType = CRM_Member_BAO_MembershipType::findById($rollingOneMonthType['id']);
    $schedule = $this->getMembershipSchedule([$membershipType], MembershipInstalmentsSchedule::QUARTERLY);
    //Expected instalment equals membership type interval * schedule, e.g. 4 instalments for 1 year duration
    $this->assertCount($interval * 4, $schedule['instalments']);
  }

  /**
   * Tests getting instalment for multi month duration unit for rolling membership type
   */
  public function testMultiMonthUnitRollingMembershipType() {
    $interval = rand(2, 12);
    $rollingOneMonthType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams, [
      'duration_unit' => 'month',
      'duration_interval' => $interval,
      'name' => 'xyz',
      'period_type' => 'rolling',
    ]));
    $membershipType = CRM_Member_BAO_MembershipType::findById($rollingOneMonthType['id']);
    $schedule = $this->getMembershipSchedule([$membershipType], MembershipInstalmentsSchedule::MONTHLY);
    //Expected instalment equals membership type interval, e.g. 6 instalments for 6 month duration
    $this->assertCount($interval, $schedule['instalments']);
  }

  /**
   * Tests rolling membership type schedulesub sub total, total tax amount and total amount
   */
  public function testRollingMembershipTypeScheduleTotalAmounts() {
    $this->mockSalesTaxFinancialAccount();
    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule($membershipTypes, MembershipInstalmentsSchedule::MONTHLY);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, 1);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;
    $this->assertEquals($expectedAmount, $schedule['sub_total']);
    $this->assertEquals($expectedTaxAmount, $schedule['tax_amount']);
    $this->assertEquals($expectedTotalAmount, $schedule['total_amount']);
    $this->assertArrayNotHasKey('prorated_number', $schedule);
    $this->assertArrayNotHasKey('prorated_unit', $schedule);
  }

  /**
   * Tests fixed membership ype schedule sub total, total tax amount and total amount
   */
  public function testFixedMembershipTypeScheduleTotalAmounts() {
    $this->mockSalesTaxFinancialAccount();
    //Mock period start day 01 Oct
    //Mock period rollover day 30 Sep
    $membershipTypes = $this->mockFixedMembershipTypes();
    $startDate = new DateTime('today');
    $schedule = $this->getMembershipSchedule($membershipTypes, MembershipInstalmentsSchedule::MONTHLY, $startDate);

    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipTypes[0], new MembershipTypeDatesCalculator());
    $diffInMonth = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($startDate);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, 12) * $diffInMonth;
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;
    $this->assertEquals($expectedAmount, $schedule['sub_total']);
    $this->assertEquals($expectedTaxAmount, $schedule['tax_amount']);
    $this->assertEquals($expectedTotalAmount, $schedule['total_amount']);
    $this->assertArrayHasKey('prorated_number', $schedule);
    $this->assertArrayHasKey('prorated_unit', $schedule);
  }

  /**
   * Tests price field schedule sub total, total tax amount and total amount
   */
  public function testPriceFieldScheduleTotalAmounts() {
    $this->mockSalesTaxFinancialAccount();
    $priceFieldValues = $this->mockPriceFieldValues();
    $membershipTypes = [];
    $expectedAmount = 0;
    foreach ($priceFieldValues as $priceFieldValue) {
      $membershipTypes[] = $this->assignPriceFieldValueToMembershipType($priceFieldValue);
      $expectedAmount += $priceFieldValue['amount'];
    }
    $schedule = $this->getMembershipSchedule(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );
    $expectedTaxAmount = ($expectedAmount * self::TAX_RATE / 100);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;
    $this->assertEquals($expectedAmount, $schedule['sub_total']);
    $this->assertEquals($expectedTaxAmount, $schedule['tax_amount']);
    $this->assertEquals($expectedTotalAmount, $schedule['total_amount']);
  }

  /**
   * Tests Rolling Monthly Instalment Schedule Amounts
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentAmounts() {
    $this->mockSalesTaxFinancialAccount();

    $monthlyInstalmentCount = 12;
    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule($membershipTypes, MembershipInstalmentsSchedule::MONTHLY);

    $this->assertCount($monthlyInstalmentCount, $schedule['instalments']);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, $monthlyInstalmentCount);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);

    foreach ($schedule['instalments'] as $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
      $this->assertCount(count($membershipTypes), $instalment->getInstalmentAmount()->getLineItems());
      foreach ($instalment->getInstalmentAmount()->getLineItems() as $key => $lineItem) {
        $membershipFee = $membershipTypes[$key]->minimum_fee;
        $expectedLineItemAmount = $this->calculateExpectedLineItemAmount($membershipFee, $monthlyInstalmentCount);
        $expectedLineItemTaxAmount = $this->calculateExpectedTaxAmount($expectedLineItemAmount);
        $expectedLineItemTotalAmount = $expectedLineItemAmount + $expectedLineItemTaxAmount;
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getUnitPrice());
        $this->assertEquals(self::TAX_RATE, $lineItem->getTaxRate());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getSubTotal());
        $this->assertEquals($expectedLineItemTaxAmount, $lineItem->getTaxAmount());
        $this->assertEquals($expectedLineItemTotalAmount, $lineItem->getTotalAmount());
      }
    }
  }

  /**
   * Tests Rolling Quarterly Instalment Amounts
   *
   * @throws Exception
   */
  public function testRollingQuarterlyInstalmentAmounts() {
    $this->mockSalesTaxFinancialAccount();

    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule($membershipTypes, MembershipInstalmentsSchedule::QUARTERLY);

    $expectedCount = 4;
    $this->assertCount($expectedCount, $schedule['instalments']);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, $expectedCount);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);

    foreach ($schedule['instalments'] as $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
      $this->assertCount(count($membershipTypes), $instalment->getInstalmentAmount()->getLineItems());
      foreach ($instalment->getInstalmentAmount()->getLineItems() as $key => $lineItem) {
        $membershipFee = $membershipTypes[$key]->minimum_fee;
        $expectedLineItemAmount = $this->calculateExpectedLineItemAmount($membershipFee, $expectedCount);
        $expectedLineItemTaxAmount = $this->calculateExpectedTaxAmount($expectedLineItemAmount);
        $expectedLineItemTotalAmount = $expectedLineItemAmount + $expectedLineItemTaxAmount;
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getUnitPrice());
        $this->assertEquals(self::TAX_RATE, $lineItem->getTaxRate());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getSubTotal());
        $this->assertEquals($expectedLineItemTaxAmount, $lineItem->getTaxAmount());
        $this->assertEquals($expectedLineItemTotalAmount, $lineItem->getTotalAmount());
      }
    }
  }

  /**
   * Tests Fixed Monthly Instalment Amounts
   *
   * @throws Exception
   */
  public function testFixedMonthlyInstalmentAmounts() {
    $this->mockSalesTaxFinancialAccount();
    //Mock period start day 01 Oct
    //Mock period rollover day 30 Sep
    $membershipTypes = $this->mockFixedMembershipTypes();
    $startDate = new DateTime('today');
    $schedule = $this->getMembershipSchedule($membershipTypes, MembershipInstalmentsSchedule::MONTHLY, $startDate);

    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipTypes[0], new MembershipTypeDatesCalculator());
    $diffInMonth = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($startDate);
    $this->assertCount($diffInMonth, $schedule['instalments']);

    $divisor = 12;
    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, $divisor);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);
    foreach ($schedule['instalments'] as $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
      $this->assertCount(count($membershipTypes), $instalment->getInstalmentAmount()->getLineItems());
      foreach ($instalment->getInstalmentAmount()->getLineItems() as $key => $lineItem) {
        $membershipFee = $membershipTypes[$key]->minimum_fee;
        $expectedLineItemAmount = $this->calculateExpectedLineItemAmount($membershipFee, $divisor);
        $expectedLineItemTaxAmount = $this->calculateExpectedTaxAmount($expectedLineItemAmount);
        $expectedLineItemTotalAmount = $expectedLineItemAmount + $expectedLineItemTaxAmount;
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getUnitPrice());
        $this->assertEquals(self::TAX_RATE, $lineItem->getTaxRate());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getSubTotal());
        $this->assertEquals($expectedLineItemTaxAmount, $lineItem->getTaxAmount());
        $this->assertEquals($expectedLineItemTotalAmount, $lineItem->getTotalAmount());
      }
    }
  }

  /**
   * Tests Rolling Annual Instalment Schedule Amounts
   *
   * @throws Exception
   */
  public function testRollingAnnualInstalmentAmounts() {
    $this->mockSalesTaxFinancialAccount();
    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule($membershipTypes, MembershipInstalmentsSchedule::ANNUAL);

    $expectedCount = 1;
    $this->assertCount($expectedCount, $schedule['instalments']);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, $expectedCount);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);

    $instalment = $schedule['instalments'][0];
    $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
    $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
    $this->assertCount(count($membershipTypes), $instalment->getInstalmentAmount()->getLineItems());
    foreach ($instalment->getInstalmentAmount()->getLineItems() as $key => $lineItem) {
      $membershipFee = $membershipTypes[$key]->minimum_fee;
      $expectedLineItemAmount = $this->calculateExpectedLineItemAmount($membershipFee, $expectedCount);
      $expectedLineItemTaxAmount = $this->calculateExpectedTaxAmount($expectedLineItemAmount);
      $expectedLineItemTotalAmount = $expectedLineItemAmount + $expectedLineItemTaxAmount;
      $this->assertEquals(1, $lineItem->getQuantity());
      $this->assertEquals($expectedLineItemAmount, $lineItem->getUnitPrice());
      $this->assertEquals(self::TAX_RATE, $lineItem->getTaxRate());
      $this->assertEquals($expectedLineItemAmount, $lineItem->getSubTotal());
      $this->assertEquals($expectedLineItemTaxAmount, $lineItem->getTaxAmount());
      $this->assertEquals($expectedLineItemTotalAmount, $lineItem->getTotalAmount());
    }
  }

  /**
   * Tests Rolling Annual Instalment Schedule Amounts
   *
   * @throws Exception
   */
  public function testFixedAnnualInstalmentAmounts() {
    $this->mockSalesTaxFinancialAccount();
    $membershipTypes = $this->mockFixedMembershipTypes();
    $startDate = new DateTime('today');
    $schedule = $this->getMembershipSchedule($membershipTypes, MembershipInstalmentsSchedule::ANNUAL, $startDate);
    $this->assertCount(1, $schedule['instalments']);

    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipTypes[0], new MembershipTypeDatesCalculator());
    $diffInMonths = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($startDate);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, 12, $diffInMonths);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);
    $expectedTotalAmount = $expectedAmount + $expectedTaxAmount;
    $instalment = $schedule['instalments'][0];
    $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
    $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
    $this->assertCount(1, $instalment->getInstalmentAmount()->getLineItems());
    $lineItem = $instalment->getInstalmentAmount()->getLineItems()[0];
    $this->assertEquals(1, $lineItem->getQuantity());
    $this->assertEquals($expectedAmount, $lineItem->getUnitPrice());
    $this->assertEquals(self::TAX_RATE, $lineItem->getTaxRate());
    $this->assertEquals($expectedAmount, $lineItem->getSubTotal());
    $this->assertEquals($expectedTaxAmount, $lineItem->getTaxAmount());
    $this->assertEquals($expectedTotalAmount, $lineItem->getTotalAmount());

  }

  /**
   * Tests Rolling Monthly Instalment Schedule Dates
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentDates() {
    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );

    $mockedDate = $this->mockMembershipDates();
    $expectedDate = $mockedDate['start_date'];
    foreach ($schedule['instalments'] as $index => $instalment) {
      if ($index != 0) {
        $expectedDate->add(new DateInterval('P1M'));
      }
      $this->assertEquals($expectedDate->format('Y-m-d'), $instalment->getInstalmentDate()->format('Y-m-d'));
    }
  }

  /**
   * Tests Rolling Quarterly Instalment Schedule Dates
   *
   * @throws Exception
   */
  public function testRollingQuarterlyInstalmentDates() {
    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule(
      $membershipTypes,
      MembershipInstalmentsSchedule::QUARTERLY
    );

    $mockedDate = $this->mockMembershipDates();
    $expectedDate = $mockedDate['start_date'];
    foreach ($schedule['instalments'] as $index => $instalment) {
      if ($index != 0) {
        $expectedDate->add(new DateInterval('P3M'));
      }
      $this->assertEquals($expectedDate->format('Y-m-d'), $instalment->getInstalmentDate()->format('Y-m-d'));
    }
  }

  /**
   * Tests Rolling Annual Instalment Schedule Dates
   *
   * @throws Exception
   */
  public function testRollingAnnualInstalmentDates() {
    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule(
      $membershipTypes,
      MembershipInstalmentsSchedule::ANNUAL
    );
    $mockedDate = $this->mockMembershipDates();
    $expectedDate = $mockedDate['start_date'];
    $this->assertEquals($expectedDate->format('Y-m-d'), $schedule['instalments'][0]->getInstalmentDate()->format('Y-m-d'));
  }

  /**
   * @throws CiviCRM_API3_Exception
   * @throws InvalidMembershipTypeInstalment
   */
  public function testRollingMembershipTypePriceFieldValuesWithoutTax() {
    $priceFieldValues = $this->mockPriceFieldValues();
    $membershipTypes = [];
    $totalAmount = 0;
    foreach ($priceFieldValues as $priceFieldValue) {
      $membershipTypes[] = $this->assignPriceFieldValueToMembershipType($priceFieldValue);
      $totalAmount += $priceFieldValue['amount'];
    }
    $schedule = $this->getMembershipSchedule(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );
    $expectedAmount = $totalAmount / 12;
    foreach ($schedule['instalments'] as $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals(0, $instalment->getInstalmentAmount()->getTaxAmount());
      $this->assertCount(count($membershipTypes), $instalment->getInstalmentAmount()->getLineItems());
      foreach ($instalment->getInstalmentAmount()->getLineItems() as $key => $lineItem) {
        $membershipFee = $membershipTypes[$key]->minimum_fee;
        $expectedLineItemAmount = $this->calculateExpectedLineItemAmount($membershipFee, 12);
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getUnitPrice());
        $this->assertEquals(0, $lineItem->getTaxRate());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getSubTotal());
        $this->assertEquals(0, $lineItem->getTaxAmount());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getTotalAmount());
      }
    }
  }

  /**
   * Tests price field values with tax instalments
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testPriceFieldValuesWithTax() {
    $this->mockSalesTaxFinancialAccount();
    $priceFieldValues = $this->mockPriceFieldValues();
    $membershipTypes = [];
    $totalAmount = 0;
    foreach ($priceFieldValues as $priceFieldValue) {
      $membershipTypes[] = $this->assignPriceFieldValueToMembershipType($priceFieldValue);
      $totalAmount += $priceFieldValue['amount'];
    }
    $schedule = $this->getMembershipSchedule(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );
    $expectedTaxAmount = ($totalAmount * self::TAX_RATE / 100) / 12;
    foreach ($schedule['instalments'] as $instalment) {
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
      $this->assertCount(count($membershipTypes), $instalment->getInstalmentAmount()->getLineItems());
      foreach ($instalment->getInstalmentAmount()->getLineItems() as $key => $lineItem) {
        $membershipFee = $membershipTypes[$key]->minimum_fee;
        $expectedLineItemAmount = $this->calculateExpectedLineItemAmount($membershipFee, 12);
        $expectedLineItemTaxAmount = $this->calculateExpectedTaxAmount($expectedLineItemAmount);
        $expectedLineItemTotalAmount = $expectedLineItemAmount + $expectedLineItemTaxAmount;
        $this->assertEquals(1, $lineItem->getQuantity());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getUnitPrice());
        $this->assertEquals(self::TAX_RATE, $lineItem->getTaxRate());
        $this->assertEquals($expectedLineItemAmount, $lineItem->getSubTotal());
        $this->assertEquals($expectedLineItemTaxAmount, $lineItem->getTaxAmount());
        $this->assertEquals($expectedLineItemTotalAmount, $lineItem->getTotalAmount());
      }
    }
  }

  /**
   * Tests price field values with non membership type and tax
   *
   * @throws InvalidMembershipTypeInstalment
   */
  public function testPriceFieldValueWithNonMembershipTypeAndTax() {
    $this->mockSalesTaxFinancialAccount();
    $priceFieldValues = $this->mockPriceFieldValues(TRUE);
    $membershipTypes = [];
    $totalAmount = 0;
    $nonMembershipPriceFieldValues = [];
    //Mock qty of select price field value equals 10
    $mockedQuantity = 10;
    foreach ($priceFieldValues as $priceFieldValue) {
      if (empty($priceFieldValue['membership_type_id'])) {
        $nonMembershipPriceFieldValues[$priceFieldValue['id']]['values'] = $priceFieldValue;
        $nonMembershipPriceFieldValues[$priceFieldValue['id']]['quantity'] = $mockedQuantity;
        continue;
      }
      $membershipTypes[] = $this->assignPriceFieldValueToMembershipType($priceFieldValue);
      $totalAmount += $priceFieldValue['amount'];
    }
    $taxAmount = $totalAmount * self::TAX_RATE / 100;

    $membershipInstalmentsSchedule = $this->getMembershipInstalmentsSchedule($membershipTypes, MembershipInstalmentsSchedule::MONTHLY);
    if (!empty($nonMembershipPriceFieldValues)) {
      $membershipInstalmentsSchedule->setNonMembershipPriceFieldValues($nonMembershipPriceFieldValues);
    }

    $membershipTypeDates = $this->getMembershipDates($membershipTypes[0]->id);
    $schedule = $membershipInstalmentsSchedule->generate(
      new DateTime($membershipTypeDates['start_date']),
      new DateTime($membershipTypeDates['end_date']),
      new DateTime($membershipTypeDates['join_date'])
    );

    $totalUnitPrice = 0;
    foreach ($nonMembershipPriceFieldValues as $priceFieldValue) {
      $totalUnitPrice = +$priceFieldValue['values']['amount'];
    }
    $totalNonMembershipPriceFieldValueTaxAmount = $totalUnitPrice * self::TAX_RATE / 100;
    $totalNonMembershipPriceFieldValueTaxAmount *= $mockedQuantity;
    $totalNonMembershipPriceFieldValueAmount  = $totalUnitPrice * $mockedQuantity;

    $mockInstalmentNumber = 12;
    $expectedAmount = ($totalAmount + $totalNonMembershipPriceFieldValueAmount) / $mockInstalmentNumber;
    $expectedTaxAmount = ($taxAmount + $totalNonMembershipPriceFieldValueTaxAmount) / $mockInstalmentNumber;

    foreach ($schedule['instalments'] as $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
      //Membership type array size = 1 and none membership price field size = 1
      $this->assertCount(2, $instalment->getInstalmentAmount()->getLineItems());

      //Asserting membership type line item
      $membershipFee = $membershipTypes[0]->minimum_fee;
      $membershipTypeLineItem = $instalment->getInstalmentAmount()->getLineItems()[0];
      $expectedLineItemAmount = $this->calculateExpectedLineItemAmount($membershipFee, $mockInstalmentNumber);
      $expectedLineItemTaxAmount = $this->calculateExpectedTaxAmount($expectedLineItemAmount);
      $expectedLineItemTotalAmount = $expectedLineItemAmount + $expectedLineItemTaxAmount;
      $this->assertEquals(1, $membershipTypeLineItem->getQuantity());
      $this->assertEquals($expectedLineItemAmount, $membershipTypeLineItem->getUnitPrice());
      $this->assertEquals(self::TAX_RATE, $membershipTypeLineItem->getTaxRate());
      $this->assertEquals($expectedLineItemAmount, $membershipTypeLineItem->getSubTotal());
      $this->assertEquals($expectedLineItemTaxAmount, $membershipTypeLineItem->getTaxAmount());
      $this->assertEquals($expectedLineItemTotalAmount, $membershipTypeLineItem->getTotalAmount());

      //Asserting non membership type line item
      $noneMembershipTypeLineItem = $instalment->getInstalmentAmount()->getLineItems()[1];
      $this->assertEquals($mockedQuantity, $noneMembershipTypeLineItem->getQuantity());

      $expectedUnitPrice = $totalUnitPrice / $mockInstalmentNumber;
      $expectedNonMembershipSubTotalLineItem = ($totalUnitPrice * $mockedQuantity) / $mockInstalmentNumber;
      $expectedNonMembershipTaxAmountLineItem = $this->calculateExpectedTaxAmount($expectedNonMembershipSubTotalLineItem);
      $expectedNonMembershipTotalAmountLineItem = $expectedNonMembershipSubTotalLineItem + $expectedNonMembershipTaxAmountLineItem;

      $this->assertEquals($expectedUnitPrice, $noneMembershipTypeLineItem->getUnitPrice());
      $this->assertEquals(self::TAX_RATE, $noneMembershipTypeLineItem->getTaxRate());
      $this->assertEquals($expectedNonMembershipSubTotalLineItem, $noneMembershipTypeLineItem->getSubTotal());
      $this->assertEquals($expectedNonMembershipTaxAmountLineItem, $noneMembershipTypeLineItem->getTaxAmount());
      $this->assertEquals($expectedNonMembershipTotalAmountLineItem, $noneMembershipTypeLineItem->getTotalAmount());

    }
  }

  public function testExceptionIsThrownIfFixedPeriodStartDaysAreNotTheSame() {
    $memType1 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams, [
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'name' => 'xyz',
      'period_type' => 'fixed',
      'fixed_period_start_day' => 1001,
      'fixed_period_rollover_day' => 930,
    ]));
    $memType2 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams, [
      'duration_unit' => 'year',
      'duration_interval' => 1,
      'name' => 'xyz',
      'period_type' => 'fixed',
      'fixed_period_start_day' => 200,
      'fixed_period_rollover_day' => 500,
    ]));
    $membershipType1 = CRM_Member_BAO_MembershipType::findById($memType1['id']);
    $membershipType2 = CRM_Member_BAO_MembershipType::findById($memType2['id']);

    $this->expectException(InvalidMembershipTypeInstalment::class);
    $this->getMembershipInstalmentsSchedule([$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUAL);
  }

  /**
   * Tests exception when duration is not one year for fixed membership type
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfFixedPeriodMembershipTypeDurationUnitIsNotOneYear() {
    $memType1 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 2, 'name' => 'xyz', 'period_type' => 'fixed']
    ));
    $memType2 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 1, 'name' => 'abc', 'period_type' => 'fixed']
    ));

    $membershipType1 = CRM_Member_BAO_MembershipType::findById($memType1['id']);
    $membershipType2 = CRM_Member_BAO_MembershipType::findById($memType2['id']);

    $this->expectException(InvalidMembershipTypeInstalment::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUAL
    );
  }

  /**
   * Tests exception when membership period types are mixed
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfMembershipPeriodTypesAreMixed() {
    $fixedType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 1, 'name' => 'abc', 'period_type' => 'fixed']
    ));
    $rollingType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 1, 'name' => 'xyz', 'period_type' => 'rolling']
    ));
    $membershipType1 = CRM_Member_BAO_MembershipType::findById($fixedType['id']);
    $membershipType2 = CRM_Member_BAO_MembershipType::findById($rollingType['id']);

    $this->expectException(InvalidMembershipTypeInstalment::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUAL
    );
  }

  /**
   * Tests exception when membership duration intervals are mixed
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfMembershipDurationIntervalsAreMixed() {
    $fixedType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 2, 'name' => 'abc', 'period_type' => 'rolling']
    ));
    $rollingType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 1, 'name' => 'xyz', 'period_type' => 'rolling']
    ));
    $membershipType1 = CRM_Member_BAO_MembershipType::findById($fixedType['id']);
    $membershipType2 = CRM_Member_BAO_MembershipType::findById($rollingType['id']);

    $this->expectException(InvalidMembershipTypeInstalment::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUAL
    );
  }

  /**
   * Tests exception when membership type ts a fixed period
   * and schedule is quarterly
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfMembershipTypeIsFixedPeriodAndScheduleIsQuarterly() {
    $fixedType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 1, 'name' => 'abc', 'period_type' => 'fixed']
    ));

    $membershipType = CRM_Member_BAO_MembershipType::findById($fixedType['id']);

    $this->expectException(InvalidMembershipTypeInstalment::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType], MembershipInstalmentsSchedule::QUARTERLY
    );
  }

  /**
   * Tests exception is thrown when membership type duration is day
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsDay() {
    $invalidMembershipType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'day', 'duration_interval' => 1, 'name' => 'xyz', 'period_type' => 'rolling']
    ));
    $membershipType = CRM_Member_BAO_MembershipType::findById($invalidMembershipType['id']);
    $this->expectException(InvalidMembershipTypeInstalment::class);
    $this->getMembershipInstalmentsSchedule([$membershipType], MembershipInstalmentsSchedule::ANNUAL);
  }

  /**
   * Tests that monthly instalments date are calculated properly
   * for membership start_date greater than 28 and months follows a sequential order.
   */
  public function testMonthlyRollingInstalmentDatesAreCalculatedProperlyWhenStartDateIsGreaterThan28() {
    $startDate = new DateTime('2022-01-31');
    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY,
      $startDate
    );

    $expectedInstalmentDates = [
      '2022-01-31',
      '2022-02-28',
      '2022-03-31',
      '2022-04-30',
      '2022-05-31',
      '2022-06-30',
      '2022-07-31',
      '2022-08-31',
      '2022-09-30',
      '2022-10-31',
      '2022-11-30',
      '2022-12-31',
    ];

    foreach ($schedule['instalments'] as $index => $instalment) {
      $instalmentDate = $instalment->getInstalmentDate();
      $this->assertEquals($expectedInstalmentDates[$index], $instalmentDate->format('Y-m-d'));
    }
  }

  /**
   * Tests that quarterly instalments date are calculated properly
   * for membership start_date greater than 28 and months follows a sequential order.
   */
  public function testQuarterlyRollingInstalmentDatesAreCalculatedProperlyWhenStartDateIsGreaterThan28() {
    $startDate = new DateTime('2022-01-31');
    $membershipTypes = $this->mockRollingMembershipTypes();
    $schedule = $this->getMembershipSchedule(
      $membershipTypes,
      MembershipInstalmentsSchedule::QUARTERLY,
      $startDate
    );

    $expectedInstalmentDates = [
      '2022-01-31',
      '2022-04-30',
      '2022-07-31',
      '2022-10-31',
    ];

    foreach ($schedule['instalments'] as $index => $instalment) {
      $instalmentDate = $instalment->getInstalmentDate();
      $this->assertEquals($expectedInstalmentDates[$index], $instalmentDate->format('Y-m-d'));
    }
  }

  /**
   * @param array $priceFieldValue
   * @return CRM_Member_DAO_MembershipType
   * @throws Exception
   */
  private function assignPriceFieldValueToMembershipType(array $priceFieldValue) {
    $membershipType = CRM_Member_BAO_MembershipType::findById($priceFieldValue['membership_type_id']);
    $membershipType->minimum_fee = $priceFieldValue['amount'];
    $membershipType->financial_type_id = $priceFieldValue['financial_type_id'];

    return $membershipType;
  }

  /**
   * @param $membershipTypes
   * @param $schedule
   * @param $startDate
   *
   * @return mixed
   * @throws Exception
   */
  private function getMembershipSchedule($membershipTypes, $schedule, $startDate = NULL) {
    $membershipInstalmentsSchedule = $this->getMembershipInstalmentsSchedule($membershipTypes, $schedule);
    $membershipTypeDates = $this->getMembershipDates($membershipTypes[0]->id, $startDate);

    return $membershipInstalmentsSchedule->generate(
      $this->getPaymentMethodValue(),
      new DateTime($membershipTypeDates['start_date']),
      new DateTime($membershipTypeDates['end_date']),
      new DateTime($membershipTypeDates['join_date'])
    );
  }

  /**
   * Mocks Membership Dates
   *
   * @return array
   */
  private function mockMembershipDates() {
    $startDate = new DateTime();
    $joinDate = $startDate;
    $endDate = new DateTime();
    $endDate->add(new DateInterval('P1Y'));
    $endDate->modify("-1 day");

    return ['start_date' => $startDate, 'join_date' => $joinDate, 'end_date' => $endDate];
  }

  /**
   * Get Membership Dates by Membership Type Id
   *
   * @param $membershipTypeId
   * @param $startDate
   *
   * @return array
   */
  private function getMembershipDates($membershipTypeId, $startDate = NULL) {
    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();

    if (!is_null($startDate)) {
      return $membershipTypeDatesCalculator->getDatesForMembershipType($membershipTypeId, $startDate);
    }
    $mockedDate = $this->mockMembershipDates();
    $dates = $membershipTypeDatesCalculator->getDatesForMembershipType(
        $membershipTypeId,
        $mockedDate['start_date'],
        $mockedDate['end_date'],
        $mockedDate['join_date']
    );

    return $dates;
  }

  /**
   * Mocking Rolling Membership Types
   *
   * @return array
   * @throws Exception
   */
  private function mockRollingMembershipTypes() {
    $memType1 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      [
        'name' => 'Rolling Membership Type 1',
        'minimum_fee' => 120,
        'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      ]
    ));
    $memType2 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      [
        'name' => 'Rolling Membership Type 2',
        'minimum_fee' => 240,
        'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      ]
    ));
    $membershipType1 = CRM_Member_BAO_MembershipType::findById($memType1['id']);
    $membershipType2 = CRM_Member_BAO_MembershipType::findById($memType2['id']);

    return [$membershipType1, $membershipType2];
  }

  private function mockFixedMembershipTypes() {
    $memType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      [
        'name' => 'Fixed Membership Type 1',
        'minimum_fee' => 120,
        'period_type' => 'fixed',
        //01 Oct
        'fixed_period_start_day' => 1001,
        // 30 Sep
        'fixed_period_rollover_day' => 930,
        'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      ]
    ));
    $this->mockSettings($memType['id'], CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator::BY_MONTHS);
    $membershipType = CRM_Member_BAO_MembershipType::findById($memType['id']);

    return [$membershipType];
  }

  /**
   * @param array $membershipTypes
   * @param string $schedule
   * @return CRM_MembershipExtras_Service_MembershipInstalmentsSchedule
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   */
  private function getMembershipInstalmentsSchedule(array $membershipTypes, string $schedule) {
    return new MembershipInstalmentsSchedule(
      $membershipTypes,
      $schedule
    );
  }

  /**
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceFieldValues($includeNonMembershipTypePriceField = FALSE) {
    $priceFieldValues = [];
    $membershipType = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['name' => 'Rolling Membership Type 1', 'minimum_fee' => 120]));

    $priceSetParams = [
      'name' => "test_price_set",
      'extends' => "CiviMember",
      'financial_type_id' => "Member Dues",
      'is_active' => 1,
    ];
    $priceSet = PriceSetFabricator::fabricate($priceSetParams);

    $priceField1 = PriceFieldFabricator::fabricate([
      'price_set_id' => $priceSet['id'],
      'label' => "Price Field 1",
      'name' => "price_field_1",
      'html_type' => "Radio",
    ]);

    $priceFieldValues[] = PriceFieldValueFabricator::fabricate([
      'price_field_id' => $priceField1['id'],
      'label' => "Price Field Value with Membership Type 1",
      'amount' => 240,
      'membership_type_id' => $membershipType['id'],
      'financial_type_id' => "Member Dues",
    ]);

    if ($includeNonMembershipTypePriceField) {
      $priceField2 = PriceFieldFabricator::fabricate([
        'price_set_id' => $priceSet['id'],
        'label' => "Price Field 1",
        'name' => "price_field_2",
        'html_type' => "Text",
      ]);
      $priceFieldValues[] = PriceFieldValueFabricator::fabricate([
        'price_field_id' => $priceField2['id'],
        'label' => "Price Field Value without Membership Type 1",
        'amount' => 120,
        'financial_type_id' => "Member Dues",
      ]);
    }

    return $priceFieldValues;
  }

  private function calculateExpectedAmount($membershipTypes, $divisor, $diff = 1) {
    $amount = 0;
    foreach ($membershipTypes as $membershipType) {
      $amount += $membershipType->minimum_fee;
    }
    return ($amount / $divisor) * $diff;
  }

  private function calculateExpectedLineItemAmount($amount, $divisor, $diff = 1) {
    return ($amount / $divisor) * $diff;
  }

  private function calculateExpectedTaxAmount($amount) {
    return $amount * self::TAX_RATE / 100;
  }

}
