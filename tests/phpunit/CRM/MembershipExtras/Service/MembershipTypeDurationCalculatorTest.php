<?php

use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeDurationTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipTypeDurationCalculatorTest extends BaseHeadlessTest {

  private $defaultMembershipTypeParams = [
    'name' => 'xyz',
    'duration_unit' => 'year',
    'period_type' => 'fixed',
    'duration_interval' => 1,
    'fixed_period_start_day' => 101,
    'fixed_period_rollover_day' => 1231,
    'domain_id' => 1,
    'member_of_contact_id' => 1,
    'financial_type_id' => 1,
  ];

  public function testCalculateDaysBasedOnDatesWhenStartAndEndDateIsNotNull() {
    $membershipType = $this->fabricateMembeshipType();

    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $startDate = new DateTime('2019-06-01');
    $endDate = new DateTime('2019-06-30');
    $numberOfDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($startDate, $endDate);
    $this->assertEquals(30, $numberOfDays);
  }

  public function testCalculateDaysBasedOnDatesWhenStartDateIsNull() {
    $membershipType = $this->fabricateMembeshipType();

    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $endDate = new DateTime('+5 days');
    $numberOfDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates(NULL, $endDate);

    //start date will be first day in the year by default.
    $expectedStartDate = new DateTime(date('Y-01-01'));
    $expectedInterval = (int) $endDate->diff($expectedStartDate)->format("%a") + 1;
    $this->assertEquals($expectedInterval, $numberOfDays);
  }

  public function testCalculateDaysBasedOnDatesUsesJoinDateAsStartDateWhenStartDateIsNullAndJoinDateIsNot() {
    $membershipType = $this->fabricateMembeshipType();

    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $joinDate = new DateTime('today');
    $endDate = new DateTime('+5 days');
    $numberOfDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates(NULL, $endDate, $joinDate);

    //Days interval will be since days including both start and end dates
    $this->assertEquals(6, $numberOfDays);
  }

  public function testCalculateDaysBasedOnDatesWhenEndDateIsNull() {
    $membershipType = $this->fabricateMembeshipType();

    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $startDate = new DateTime('today');
    $numberOfDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($startDate, NULL);

    //start date will be the last day in the year by default.calculateMonthBasedOnDates
    $expectedEndDate = new DateTime(date('Y-12-31'));
    $expectedInterval = (int) $expectedEndDate->diff($startDate)->format("%a") + 1;
    $this->assertEquals($expectedInterval, $numberOfDays);
  }

  public function testCalculateMonthsForAnnualDurationBasedOnDates() {
    $params = [
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'duration_interval' => 1,
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
    ];

    $membershipType = $this->fabricateMembeshipType($params);
    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);

    $startDate = new DateTime('2021-06-25');
    $noOfMonth = $membershipTypeDurationCalculator->calculateMonthsBasedOnDates($startDate);

    $this->assertEquals(4, $noOfMonth);
  }

  private function fabricateMembeshipType($params = []) {
    $params = array_merge($this->defaultMembershipTypeParams, $params);
    $membershipType = MembershipTypeFabricator::fabricate($params);

    return CRM_Member_BAO_MembershipType::findById($membershipType['id']);
  }

}
