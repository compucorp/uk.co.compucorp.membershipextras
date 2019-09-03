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

  public function testCalculateDaysBasedOnDatesWhenStartAndEndDateIsNotNull() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1,
    ], TRUE);

    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $startDate = new DateTime('2019-06-01');
    $endDate = new DateTime('2019-06-30');
    $numberOfDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($startDate, $endDate);
    $this->assertEquals(30, $numberOfDays);
  }

  public function testCalculateDaysBasedOnDatesWhenStartDateIsNull() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1
    ], TRUE);

    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $endDate = new DateTime('+5 days');
    $numberOfDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates(NULL, $endDate);

    //start date will be first day in the year by default.
    $expectedStartDate = new DateTime(date('Y-01-01'));
    $expectedInterval = $endDate->diff($expectedStartDate)->format("%a") + 1;
    $this->assertEquals($expectedInterval, $numberOfDays);
  }

  public function testCalculateDaysBasedOnDatesUsesJoinDateAsStartDateWhenStartDateIsNullAndJoinDateIsNot() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1
    ], TRUE);

    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $joinDate = new DateTime('today');
    $endDate = new DateTime('+5 days');
    $numberOfDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates(NULL, $endDate, $joinDate);

    //Days interval will be since days including both start and end dates
    $this->assertEquals(6, $numberOfDays);
  }

  public function testCalculateDaysBasedOnDatesWhenEndDateIsNull() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1
    ], TRUE);

    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    $membershipTypeDurationCalculator = new MembershipTypeDurationCalculator($membershipType, $membershipTypeDatesCalculator);
    $startDate = new DateTime('today');
    $numberOfDays = $membershipTypeDurationCalculator->calculateDaysBasedOnDates($startDate, NULL);

    //start date will be the last day in the year by default.
    $expectedEndDate = new DateTime(date('Y-12-31'));
    $expectedInterval = $expectedEndDate->diff($startDate)->format("%a") + 1;
    $this->assertEquals($expectedInterval, $numberOfDays);
  }
}
