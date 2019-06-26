<?php

use CRM_MembershipExtras_Service_MembershipTypeDuration as MembershipTypeDuration;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeDurationTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipTypeDurationTest extends BaseHeadlessTest {

  public function testCalculateDaysBasedOnDatesWhenStartAndEndDateIsNotNull() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1
    ]);
    $membershipTypeDuration = new MembershipTypeDuration($membershipType);
    $startDate = new DateTime('2019-06-01');
    $endDate = new DateTime('2019-06-30');
    $numberOfDays = $membershipTypeDuration->calculateDaysBasedOnDates($startDate, $endDate);
    $this->assertEquals(30, $numberOfDays);
  }

  public function testCalculateDaysBasedOnDatesWhenStartDateIsNull() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1
    ]);
    $membershipTypeDuration = new MembershipTypeDuration($membershipType);
    $endDate = new DateTime('+5 days');
    $numberOfDays = $membershipTypeDuration->calculateDaysBasedOnDates(NULL, $endDate);

    //start date will be today by default
    //6 days is expected because the start date is also included in the count
    $this->assertEquals(6, $numberOfDays);
  }

  public function testCalculateDaysBasedOnDatesWhenEndDateIsNull() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1
    ]);
    $membershipTypeDuration = new MembershipTypeDuration($membershipType);
    $startDate = new DateTime();
    $membershipTypeDurationDays = date('L') ? 366 : 365;
    $numberOfDays = $membershipTypeDuration->calculateDaysBasedOnDates($startDate, NULL);

    $this->assertEquals($membershipTypeDurationDays, $numberOfDays);
  }
}
