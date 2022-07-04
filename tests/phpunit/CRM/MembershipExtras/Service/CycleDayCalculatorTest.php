<?php

/**
 * Class CRM_MembershipExtras_Service_CycleDayCalculatorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_CycleDayCalculatorTest extends BaseHeadlessTest {

  /**
   * Tests Cycle day defaults to 1 for non monthly memberships.
   */
  public function testCycleDayDefaultsToOneForAnnualMembership() {
    $nextScheduledDate = '2022-01-01';
    $nextScheduledDateCycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($nextScheduledDate, 'year');

    $this->assertEquals(1, $nextScheduledDateCycleDay);
  }

  /**
   * Tests Cycle Day is next contribution scheduled date day for monthly memberships.
   */
  public function testCycleDayIsNextScheduledDateDayForMonthlySubscription() {
    $nextScheduledDate = '2022-01-25';
    $nextScheduledDateCycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($nextScheduledDate, 'month');

    $this->assertEquals(25, $nextScheduledDateCycleDay);
  }

  /**
   * Tests Cycle Day for monthly membership doesn't exceed 28.
   */
  public function testCycleDayForMonthlySubscriptionDoesntExceed28() {
    $nextScheduledDate = '2022-01-31';
    $nextScheduledDateCycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($nextScheduledDate, 'month');

    $this->assertEquals(28, $nextScheduledDateCycleDay);
  }

}
