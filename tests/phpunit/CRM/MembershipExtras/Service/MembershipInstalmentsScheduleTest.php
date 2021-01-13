<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as MembershipInstalmentsSchedule;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator as InvalidMembershipTypeInstalmentCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipInstalmentsScheduleTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipInstalmentsScheduleTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;
  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;

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
   * Tests Rolling Monthly Instalment Schedule Amounts
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentAmounts() {
    $this->mockSalesTaxFinancialAccount();

    $monthlyInstalmentCount = MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
    $membershipTypes = $this->mockRollingMembershipTypes();
    $instalments = $this->getMembershipInstalments($membershipTypes, MembershipInstalmentsSchedule::MONTHLY);

    $this->assertCount($monthlyInstalmentCount, $instalments);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, $monthlyInstalmentCount);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);

    foreach ($instalments as $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
    }
  }

  /**
   * Tests fixed monthly instalment amounts
   *
   * @throws Exception
   */
  public function testFixedMonthlyInstalmentAmounts() {
    $this->mockSalesTaxFinancialAccount();
    $monthlyInstalmentCount = MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
    $membershipTypes = $this->mockFixedMembershipTypes();
    $instalments = $this->getMembershipInstalments($membershipTypes, MembershipInstalmentsSchedule::MONTHLY);

    $this->assertCount($monthlyInstalmentCount, $instalments);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, $monthlyInstalmentCount);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);

    foreach ($instalments as $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
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
    $instalments = $this->getMembershipInstalments($membershipTypes, MembershipInstalmentsSchedule::ANNUAL);

    $this->assertCount(MembershipInstalmentsSchedule::ANNUAL_INSTALMENT_COUNT, $instalments);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, MembershipInstalmentsSchedule::ANNUAL_INSTALMENT_COUNT);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);

    $this->assertEquals($expectedAmount, $instalments[0]->getInstalmentAmount()->getAmount());
    $this->assertEquals($expectedTaxAmount, $instalments[0]->getInstalmentAmount()->getTaxAmount());
  }

  /**
   * Tests Rolling Annual Instalment Schedule Amounts
   *
   * @throws Exception
   */
  public function testFixedAnnualInstalmentAmounts() {
    $this->mockSalesTaxFinancialAccount();
    $membershipTypes = $this->mockFixedMembershipTypes();
    $instalments = $this->getMembershipInstalments($membershipTypes, MembershipInstalmentsSchedule::ANNUAL);
    $this->assertCount(MembershipInstalmentsSchedule::ANNUAL_INSTALMENT_COUNT, $instalments);

    $expectedAmount = $this->calculateExpectedAmount($membershipTypes, MembershipInstalmentsSchedule::ANNUAL_INSTALMENT_COUNT);
    $expectedTaxAmount = $this->calculateExpectedTaxAmount($expectedAmount);

    $this->assertEquals($expectedAmount, $instalments[0]->getInstalmentAmount()->getAmount());
    $this->assertEquals($expectedTaxAmount, $instalments[0]->getInstalmentAmount()->getTaxAmount());
  }

  /**
   * Tests Rolling Monthly Instalment Schedule Dates
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentDates() {
    $membershipTypes = $this->mockRollingMembershipTypes();
    $instalments = $this->getMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );

    $mockedDate = $this->mockMembershipDates();
    $expectedDate = $mockedDate['start_date'];
    foreach ($instalments as $index => $instalment) {
      if ($index != 0) {
        $expectedDate->add(new DateInterval('P1M'));
      }
      $this->assertEquals(
        $expectedDate->format('Y-m-d'),
        $instalment->getInstalmentDate()->format('Y-m-d')
      );
    }
  }

  /**
   * Tests Rolling Annual Instalment Schedule Dates
   *
   * @throws Exception
   */
  public function testRollingAnnualInstalmentDates() {
    $membershipTypes = $this->mockRollingMembershipTypes();
    $instalments = $this->getMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::ANNUAL
    );
    $mockedDate = $this->mockMembershipDates();
    $expectedDate = $mockedDate['start_date'];
    $this->assertEquals($expectedDate->format('Y-m-d'), $instalments[0]->getInstalmentDate()->format('Y-m-d'));
  }

  /**
   * @throws CiviCRM_API3_Exception
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  public function testRollingMembershipTypePriceFieldValuesWithoutTax() {
    $priceFieldValues = $this->mockPriceFieldValues();
    $membershipTypes = [];
    $totalAmount = 0;
    foreach ($priceFieldValues as $priceFieldValue) {
      $membershipTypes[] = $this->assignPriceFieldValueToMembershipType($priceFieldValue);
      $totalAmount += $priceFieldValue['amount'];
    }
    $instalments = $this->getMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );
    $expectedAmount = $totalAmount / MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
    foreach ($instalments as $index => $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals(0, $instalment->getInstalmentAmount()->getTaxAmount());
    }
  }

  /**
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
    $instalments = $this->getMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );
    $expectedTaxAmount = ($totalAmount * self::TAX_RATE / 100) / MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
    foreach ($instalments as $index => $instalment) {
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
    }
  }

  /**
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
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
    $instalments = $membershipInstalmentsSchedule->generate(
      new DateTime($membershipTypeDates['start_date']),
      new DateTime($membershipTypeDates['end_date']),
      new DateTime($membershipTypeDates['join_date'])
    );

    $totalNonMembershipPriceFieldValueAmount = 0;
    foreach ($nonMembershipPriceFieldValues as $priceFieldValue) {
      $totalNonMembershipPriceFieldValueAmount = +$priceFieldValue['values']['amount'];
    }
    $totalNonMembershipPriceFieldValueTaxAmount = $totalNonMembershipPriceFieldValueAmount * self::TAX_RATE / 100;
    $totalNonMembershipPriceFieldValueAmount *= $mockedQuantity;
    $totalNonMembershipPriceFieldValueTaxAmount *= $mockedQuantity;

    $expectedAmount = ($totalAmount + $totalNonMembershipPriceFieldValueAmount) / MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
    $expectedTaxAmount = ($taxAmount + $totalNonMembershipPriceFieldValueTaxAmount) / MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;

    foreach ($instalments as $index => $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
      $this->assertEquals($expectedTaxAmount, $instalment->getInstalmentAmount()->getTaxAmount());
    }
  }

  /**
   * Tests Exception when providing incorrect duration unit
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotYearly() {
    $memType1 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'month', 'name' => 'xyz']
    ));

    $memType2 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'name' => 'xyz']
    ));

    $membershipType1 = CRM_Member_BAO_MembershipType::findById($memType1['id']);
    $membershipType2 = CRM_Member_BAO_MembershipType::findById($memType2['id']);

    $this->expectException(InvalidMembershipTypeInstalmentCalculator::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUAL
    );
  }

  /**
   * Tests exception when providing incorrect duration interval
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotOneYear() {
    $memType1 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 2, 'name' => 'xyz']
    ));
    $memType2 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 1, 'name' => 'xyz']
    ));

    $membershipType1 = CRM_Member_BAO_MembershipType::findById($memType1['id']);
    $membershipType2 = CRM_Member_BAO_MembershipType::findById($memType2['id']);

    $this->expectException(InvalidMembershipTypeInstalmentCalculator::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUAL
    );
  }

  /**
   * Tests exception when membership period types are mixed
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
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

    $this->expectException(InvalidMembershipTypeInstalmentCalculator::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUAL
    );
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
   * @return mixed
   * @throws Exception
   */
  private function getMembershipInstalments($membershipTypes, $schedule) {
    $membershipInstalmentsSchedule = $this->getMembershipInstalmentsSchedule($membershipTypes, $schedule);

    $membershipTypeDates = $this->getMembershipDates($membershipTypes[0]->id);

    return $membershipInstalmentsSchedule->generate(
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
   * @return array
   */
  private function getMembershipDates($membershipTypeId) {
    $mockedDate = $this->mockMembershipDates();

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $membershipTypeId,
      $mockedDate['start_date'],
      $mockedDate['end_date'],
      $mockedDate['join_date']
    );

    return $membershipTypeDates;
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
    $this->mockSettings($memType['id'], CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator::BY_DAYS);
    $membershipType = CRM_Member_BAO_MembershipType::findById($memType['id']);

    return [$membershipType];
  }

  /**
   * @param array $membershipTypes
   * @param string $schedule
   * @return CRM_MembershipExtras_Service_MembershipInstalmentsSchedule
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
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

  private function calculateExpectedAmount($membershipTypes, $divisor) {
    $amount = 0;
    foreach ($membershipTypes as $membershipType) {
      $amount += $membershipType->minimum_fee;
    }
    return $amount / $divisor;

  }

  private function calculateExpectedTaxAmount($amount) {
    return $amount * self::TAX_RATE / 100;
  }

}
