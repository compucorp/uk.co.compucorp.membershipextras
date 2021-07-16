<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;

/**
 * Class PaymentScheduleTest
 *
 * @group headless
 */
class api_v3_PaymentSchedule_GetPaymentScheduleTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;
  use CRM_MembershipExtras_Test_Helper_PaymentMethodTrait;

  /**
   * Test ExceptionIsThrownIfScheduleIsNotValid
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfScheduleIsNotValid() {
    $this->expectException(CiviCRM_API3_Exception::class);
    civicrm_api3('PaymentSchedule', 'getByMembershipType', [
      'membership_type_id' => 1,
      'schedule' => 'xyz',
      'payment_method' => $this->getPaymentMethodValue(),
    ])['values'];
  }

  /**
   * Test Get Monthly Rolling MembershipType Schedule
   *
   * @throws CiviCRM_API3_Exception|Exception
   */
  public function testGetByMonthlyRollingMembershipType() {
    $schedule = 'monthly';
    $membershipType = $this->mockRollingMembershipType();
    $scheduleInstalment = $this->getMembershipTypeSchedule($membershipType['id'], $schedule);
    $this->assertCount(12, $scheduleInstalment['instalments']);

    $expectedAmount = 10;
    $expectedTaxAmount = 0;
    $expectedInstalmentDate = new DateTime($this->getMembershipStartDate($membershipType['id']));
    $this->assertInstalments($scheduleInstalment, $expectedAmount, $expectedTaxAmount, $expectedInstalmentDate, 'P1M');
  }

  /**
   * Test Get Quarterly Rolling MembershipType Schedule
   *
   * @throws Exception
   */
  public function testGetByQuarterlyRollingMembershipType() {
    $schedule = 'quarterly';
    $membershipType = $this->mockRollingMembershipType();
    $scheduleInstalment = $this->getMembershipTypeSchedule($membershipType['id'], $schedule);
    $this->assertCount(4, $scheduleInstalment['instalments']);

    $expectedAmount = 30;
    $expectedTaxAmount = 0;
    $expectedInstalmentDate = new DateTime($this->getMembershipStartDate($membershipType['id']));
    $this->assertInstalments($scheduleInstalment, $expectedAmount, $expectedTaxAmount, $expectedInstalmentDate, 'P3M');
  }

  /**
   * Test Get Annual Rolling MembershipType Schedule
   * @throws Exception
   */
  public function testGetByAnnualRollingMembershipType() {
    $schedule = 'annual';
    $membershipType = $this->mockRollingMembershipType();
    $scheduleInstalment = $this->getMembershipTypeSchedule($membershipType['id'], $schedule);
    $this->assertCount(1, $scheduleInstalment['instalments']);
    $expectedAmount = 120;
    $expectedTaxAmount = 0;
    $expectedInstalmentDate = new DateTime($this->getMembershipStartDate($membershipType['id']));
    $this->assertInstalments($scheduleInstalment, $expectedAmount, $expectedTaxAmount, $expectedInstalmentDate);
  }

  /**
   * Tests get monthly fixed membership type
   */
  public function testGetMonthlyFixedMembershipTypeCalculatedByDays() {
    $membershipType = $this->mockFixedMembeshipType();
    $this->mockSettings($membershipType['id'], CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator::BY_DAYS);
    $startDate = new DateTime(date('2021-01-01'));
    $formattedStartDate = $startDate->format('Y-m-d');

    //Mock end date as per membership type rollover day
    $endDate = new DateTime(date('2021-09-30'));
    $interval = $endDate->diff($startDate);
    $durationInDays = (int) $interval->format("%a") + 1;
    //Calculate expected amount by days.
    //Membership fee is 120
    //Membership roll over day is 30 Sep
    //No of days between start date 01 Jan 2021 to membership rollover day is 274 days
    //No of months between start date 01 Jan to membership rollover day is 9 months
    //2021 has 365 days
    $expectedAmount = round(((120 / 365) * $durationInDays) / 9, 2);
    $expectedTaxAmount = 0;
    $instalments = $this->getMembershipTypeSchedule($membershipType['id'], 'monthly', $formattedStartDate);
    $expectedInstalmentDate = new DateTime($this->getMembershipStartDate($membershipType['id'], $formattedStartDate));
    $this->assertInstalments($instalments, $expectedAmount, $expectedTaxAmount, $expectedInstalmentDate, 'P1M');
  }

  /**
   * Tests get annual fixed membership type
   */
  public function testGetAnnualFixedMembershipTypeCalculateByMonths() {
    $membershipType = $this->mockFixedMembeshipType();
    $this->mockSettings($membershipType['id'], CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator::BY_MONTHS);
    $startDate = new DateTime(date('Y-01-01'));
    $formattedStartDate = $startDate->format('Y-m-d');
    //Calculate expected amount by months
    //Membership fee is 120
    //Membership rollover day is 30 Sep
    //No of months between start date 01 Jan to membership rollover day is 9 months
    $expectedAmount = (120 / 12) * 9;
    $instalments = $this->getMembershipTypeSchedule($membershipType['id'], 'annual', $formattedStartDate);
    $this->assertCount(1, $instalments['instalments']);
    $expectedTaxAmount = 0;
    $expectedInstalmentDate = new DateTime($this->getMembershipStartDate($membershipType['id'], $formattedStartDate));
    $this->assertInstalments($instalments, $expectedAmount, $expectedTaxAmount, $expectedInstalmentDate);

  }

  /**
   * Tests exception is thrown if membership type is fixed and schedule is quarterly
   */
  public function testExceptionIsThrownIfMembershipTypeIsFixedAndScheduleIsQuarterly() {
    $this->expectException(CiviCRM_API3_Exception::class);
    $membershipType = $this->mockFixedMembeshipType();
    $today = new DateTime('today');
    $startDate = $today->format('Y-m-d');
    $this->getMembershipTypeSchedule($membershipType['id'], 'quarterly', $startDate);
  }

  /**
   * Tests exception is thrown if operator is not IN
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfOperatorIsNotIN() {
    $this->expectException(CiviCRM_API3_Exception::class);
    $priceFieldValues = $this->mockPriceFieldValues();
    $params = [
      'sequential' => 1,
      'schedule' => 'monthly',
    ];
    $selectedPriceFieldValues = [];
    foreach ($priceFieldValues as $priceFieldValue) {
      if (empty($priceFieldValue['membership_type_id'])) {
        $selectedPriceFieldValues[$priceFieldValue['id']] = 10;
      }
      else {
        $selectedPriceFieldValues[$priceFieldValue['id']] = 1;
      }
    }

    $params['price_field_values'] = ['=' => $selectedPriceFieldValues];
    civicrm_api3('PaymentSchedule', 'getByPriceFieldValues', $params);
  }

  /**
   * Tests in instalments by price field values.
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testGetByPriceFieldValues() {
    $mockNonMembershipPriceFieldInputQuantity = 10;
    $priceFieldValues = $this->mockPriceFieldValues();
    $params = [
      'schedule' => 'monthly',
      'payment_method' => $this->getPaymentMethodValue(),
    ];
    $selectedPriceFieldValues = [];
    foreach ($priceFieldValues as $priceFieldValue) {
      if (empty($priceFieldValue['membership_type_id'])) {
        $selectedPriceFieldValues[$priceFieldValue['id']] = $mockNonMembershipPriceFieldInputQuantity;
      }
      else {
        $selectedPriceFieldValues[$priceFieldValue['id']] = 1;
      }
    }
    $params['price_field_values'] = ['IN' => $selectedPriceFieldValues];

    $scheduleInstalments = civicrm_api3('PaymentSchedule', 'getByPriceFieldValues', $params);

    $this->assertCount(12, $scheduleInstalments['values']['instalments']);
  }

  /**
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceFieldValues() {
    $priceFieldValues = [];
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type',
      'period_type' => 'rolling',
      'duration_unit' => 'year',
      'minimum_fee' => 120,
      'duration_interval' => 1,
    ]);

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

    return $priceFieldValues;
  }

  private function getMembershipTypeSchedule($membershipID, $schedule, $startDate = NULL) {
    $params = [
      'membership_type_id' => $membershipID,
      'schedule' => $schedule,
      'payment_method' => $this->getPaymentMethodValue(),
    ];
    if (!is_null($startDate)) {
      $params['start_date'] = $startDate;
    }
    return civicrm_api3('PaymentSchedule', 'getByMembershipType', $params)['values'];
  }

  private function getMembershipStartDate($membershipID, $startDate = NULL) {
    return CRM_Member_BAO_MembershipType::getDatesForMembershipType($membershipID, NULL, $startDate, NULL)['start_date'];
  }

  private function mockRollingMembershipType() {
    return MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type',
      'period_type' => 'rolling',
      'duration_unit' => 'year',
      'minimum_fee' => 120,
      'duration_interval' => 1,
    ]);
  }

  private function mockFixedMembeshipType() {
    return MembershipTypeFabricator::fabricate([
      'name' => 'Fixed Membership Type',
      'period_type' => 'fixed',
      'duration_unit' => 'year',
      'duration_interval' => 1,
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
      'minimum_fee' => 120,
    ]);
  }

  /**
   * Generic functions for asserting instalments
   *
   * @param array $instalments
   * @param float $expectedAmount
   * @param float $expectedTaxAmount
   * @param DateTime $expectedDate
   * @param string|null $dateInterval
   * @throws Exception
   */
  private function assertInstalments(array $instalments, float $expectedAmount, float $expectedTaxAmount, DateTime $expectedDate, string $dateInterval = NULL) {
    foreach ($instalments['instalments'] as $instalment) {
      $this->assertEquals(round($expectedAmount, 2), round($instalment['instalment_amount'], 2));
      $this->assertEquals(round($expectedTaxAmount, 2), round($instalment['instalment_tax_amount'], 2));
      $this->assertEquals($expectedDate->format('Y-m-d'), $instalment['instalment_date']);
      if (!is_null($dateInterval)) {
        $expectedDate->add(new DateInterval($dateInterval));
      }
    }
  }

}
