<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as MembershipInstalmentsSchedule;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator as InvalidMembershipTypeInstalmentCalculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeInstalmentsScheduleTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipTypeInstalmentsScheduleTest extends BaseHeadlessTest {

  /**
   * Tests Rolling Monthly Instalment Schedule Amounts
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentAmounts() {
    $membershipTypes = $this->getRollingMembershipTypes();
    $instalments = $this->getRollingMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );

    $this->assertCount(MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT, $instalments);

    $expectedAmount = MoneyUtilities::roundToPrecision(
      ($membershipTypes[0]->minimum_fee + $membershipTypes[1]->minimum_fee) / MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT,
      2
    );

    $instalment = $instalments[0];
    $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
  }

  /**
   * Tests Rolling Monthly Instalment Schedule Dates
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentDates() {
    $membershipTypes = $this->getRollingMembershipTypes();
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
   * Tests Exception when providing incorrect duration unit
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotYearly() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'month',
    ], TRUE);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
    ], TRUE);

    $membershipTypes = [$membershipType1, $membershipType2];
    $this->expectException(InvalidMembershipTypeInstalmentCalculator::class);
    $this->getMembershipTypeInstalmentAmount($membershipTypes, MembershipInstalmentsSchedule::ANNUALLY);
  }

  /**
   * Tests exception when providing incorrect duration interval
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotOneYear() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 2,
    ], TRUE);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1,
    ], TRUE);

    $membershipTypes = [$membershipType1, $membershipType2];
    $this->expectException(InvalidMembershipTypeInstalmentCalculator::class);
    $this->getMembershipTypeInstalmentAmount($membershipTypes, MembershipInstalmentsSchedule::ANNUALLY);
  }

  /**
   * @param $membershipTypes
   * @param $schedule
   * @return mixed
   * @throws Exception
   */
  private function getRollingMembershipInstalments($membershipTypes, $schedule) {
    $membershipTypeInstalmentCalculator = $this->getMembershipTypeInstalmentAmount($membershipTypes, $schedule);

    $mockedDate = $this->mockMembershipDates();

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $membershipTypes[0]->id,
      $mockedDate['start_date'],
      $mockedDate['end_date'],
      $mockedDate['join_date']
    );

    return $membershipTypeInstalmentCalculator->generate(
      new DateTime($membershipTypeDates['start_date']),
      new DateTime($membershipTypeDates['end_date']),
      new DateTime($membershipTypeDates['join_date'])
    );

  }

  /**
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
   * @return array
   */
  private function getRollingMembershipTypes() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type 1',
      'minimum_fee' => 120,
      'period_type' => 'rolling',
    ], TRUE);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type 2',
      'minimum_fee' => 240,
      'period_type' => 'rolling',
    ], TRUE);
    return [$membershipType1, $membershipType2];

  }

  /**
   * @param array $membershipTypes
   * @param string $schedule
   * @return CRM_MembershipExtras_Service_MembershipInstalmentsSchedule
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  private function getMembershipTypeInstalmentAmount(array $membershipTypes, string $schedule) {
    return new MembershipInstalmentsSchedule(
      $membershipTypes,
      $schedule
    );
  }

}
