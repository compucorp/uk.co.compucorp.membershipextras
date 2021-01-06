<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as MembershipInstalmentsSchedule;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator as InvalidMembershipTypeInstalmentCalculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as TaxAmountCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipInstalmentsScheduleTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipInstalmentsScheduleTest extends BaseHeadlessTest {

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
    'financial_type_id' => 1,
  ];

  /**
   * Tests Rolling Monthly Instalment Schedule Amounts
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentAmounts() {
    $monthlyInstalmentCount = MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
    $membershipTypes = $this->mockRollingMembershipTypes();
    $instalments = $this->getRollingMembershipInstalments($membershipTypes, MembershipInstalmentsSchedule::MONTHLY);

    $this->assertCount($monthlyInstalmentCount, $instalments);

    $amount = ($membershipTypes[0]->minimum_fee + $membershipTypes[1]->minimum_fee) / $monthlyInstalmentCount;
    $expectedAmount = MoneyUtilities::roundToPrecision($amount, 2);

    foreach ($instalments as $instalment) {
      $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
    }
  }

  /**
   * Tests Rolling Monthly Instalment Schedule Amounts with Tax
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentAmountsWithTax() {
    $taxCalculator = $this->getMembershipInstalmentTaxAmountCalculator();
    $this->mockSalesTaxFinancialAccount();
    $membershipTypes = $this->mockRollingMembershipTypes();
    $instalments = $this->getRollingMembershipInstalments($membershipTypes, MembershipInstalmentsSchedule::ANNUAL);
    $taxAmount = 0;
    foreach ($membershipTypes as $membershipType) {
      $taxAmount += $taxCalculator->calculateByMembershipType($membershipType, $membershipType->minimum_fee);
    }
    $this->assertTrue($taxAmount !== (float) 0);
    $this->assertEquals($taxAmount, $instalments[0]->getInstalmentAmount()->getTaxAmount());
  }

  /**
   * Tests Rolling Monthly Instalment Schedule Dates
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentDates() {
    $membershipTypes = $this->mockRollingMembershipTypes();
    $instalments = $this->getRollingMembershipInstalments(
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
   * @throws CiviCRM_API3_Exception
   */
  public function testPriceFieldValuesWithoutTax() {
    $priceFieldValues = $this->mockPriceFieldValues();
    $membershipTypes = [];
    $totalAmount = 0;
    foreach ($priceFieldValues as $priceFieldValue) {
      $membershipTypes[] = $this->assignPriceFieldValueToMembershipType($priceFieldValue);
      $totalAmount = +$priceFieldValue['amount'];
    }
    $instalments = $this->getRollingMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );
    $expectedAmount = ($totalAmount) / MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
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
    $taxCalculator = $this->getMembershipInstalmentTaxAmountCalculator();
    $priceFieldValues = $this->mockPriceFieldValues();
    $membershipTypes = [];
    $taxAmount = 0;
    foreach ($priceFieldValues as $priceFieldValue) {
      $membershipTypes[] = $this->assignPriceFieldValueToMembershipType($priceFieldValue);
      $taxAmount += $taxCalculator->calculateByPriceFieldValue($priceFieldValue);
    }
    $instalments = $this->getRollingMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );
    foreach ($instalments as $index => $instalment) {
      $this->assertEquals($taxAmount / MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT, $instalment->getInstalmentAmount()->getTaxAmount());
    }
  }

  /**
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  public function testPriceFieldValueWithNonMembershipTypeAndTax() {
    $this->mockSalesTaxFinancialAccount();
    $taxCalculator = $this->getMembershipInstalmentTaxAmountCalculator();
    $priceFieldValues = $this->mockPriceFieldValues(TRUE);
    $membershipTypes = [];
    $taxAmount = 0;
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
      $taxAmount += $taxCalculator->calculateByPriceFieldValue($priceFieldValue);
    }

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
    $totalNonMembershipPriceFieldValueTaxAmount = 0;
    foreach ($nonMembershipPriceFieldValues as $priceFieldValue) {
      $totalNonMembershipPriceFieldValueAmount = +$priceFieldValue['values']['amount'];
      $totalNonMembershipPriceFieldValueTaxAmount += $taxCalculator->calculateByPriceFieldValue($priceFieldValue['values']);
    }
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
  private function getRollingMembershipInstalments($membershipTypes, $schedule) {
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
      ['name' => 'Rolling Membership Type 1', 'minimum_fee' => 120])
    );

    $memType2 = MembershipTypeFabricator::fabricate(array_merge($this->defaultRollingMembershipTypeParams,
      ['name' => 'Rolling Membership Type 2', 'minimum_fee' => 240])
    );

    $membershipType1 = CRM_Member_BAO_MembershipType::findById($memType1['id']);
    $membershipType2 = CRM_Member_BAO_MembershipType::findById($memType2['id']);

    return [$membershipType1, $membershipType2];
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

  /**
   * @throws CiviCRM_API3_Exception
   */
  private function mockSalesTaxFinancialAccount() {
    $existingRecordResponse = civicrm_api3('FinancialAccount', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 1],
      'name' => 'Sales Tax',
    ]);

    if (empty($existingRecordResponse['id'])) {
      $financialAccount = CRM_MembershipExtras_Test_Fabricator_FinancialAccount::fabricate([
        'name' => 'Sales Tax',
        'contact_id' => 1,
        'financial_account_type_id' => 'Liability',
        'accounting_code' => 5500,
        'is_header_account' => 0,
        'is_deductible' => 1,
        'is_tax' => 1,
        'tax_rate' => 20,
        'is_active' => 1,
        'is_default' => 0,
      ]);
      $memberDuesFinancialType = CRM_MembershipExtras_Test_Fabricator_FinancialType::fabricate([
        'sequential' => 1,
        'name' => "Member Dues",
      ]);
      CRM_MembershipExtras_Test_Fabricator_EntityFinancialAccount::fabricate([
        'entity_table' => 'civicrm_financial_type',
        'account_relationship' => 'Sales Tax Account is',
        'financial_account_id' => $financialAccount['id'],
        'entity_id' => $memberDuesFinancialType['id'],
      ]);
    }
  }

  /**
   * @return CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator
   */
  public function getMembershipInstalmentTaxAmountCalculator(): TaxAmountCalculator {
    return new TaxAmountCalculator();
  }

}
