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
   * Tests Generate Rolling Monthly Instalments
   *
   * @throws Exception
   */
  public function testGenerateRollingMonthlyInstalments() {
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
   * Test Generate Rolling Quarterly Instalment
   *
   * @throws Exception
   */
  public function testGenerateRollingQuarterlyInstalments() {
    $membershipTypes = $this->getRollingMembershipTypes();
    $instalments = $this->getRollingMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::QUARTERLY
    );

    $this->assertCount(MembershipInstalmentsSchedule::QUARTERLY_INSTALMENT_COUNT, $instalments);

    $expectedAmount = MoneyUtilities::roundToPrecision(
      ($membershipTypes[0]->minimum_fee + $membershipTypes[1]->minimum_fee) / MembershipInstalmentsSchedule::QUARTERLY_INSTALMENT_COUNT,
      2
    );

    $instalment = $instalments[0];
    $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
  }

  /**
   * Test Generate Rolling Annually Instalment
   *
   * @throws Exception
   */
  public function testCalculateRollingAnnuallyInstalments() {
    $membershipTypes = $this->getRollingMembershipTypes();
    $instalments = $this->getRollingMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::ANNUALLY
    );

    $this->assertCount(MembershipInstalmentsSchedule::ANNUALLY_INSTALMENT_COUNT, $instalments);

    $expectedAmount = MoneyUtilities::roundToPrecision(
      ($membershipTypes[0]->minimum_fee + $membershipTypes[1]->minimum_fee) / MembershipInstalmentsSchedule::ANNUALLY_INSTALMENT_COUNT,
      2
    );

    $instalment = $instalments[0];
    $this->assertEquals($expectedAmount, $instalment->getInstalmentAmount()->getAmount());
  }

  /**
   * Tests Exception when providing incorrect duration unit
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotYearly() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'month',
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
    ]);

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
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1,
    ]);

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

    $startDate = new DateTime();
    $joinDate = $startDate;
    $endDate = new DateTime();
    $endDate->add(new DateInterval('P1Y'));

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $membershipTypes[0]->id,
      $startDate,
      $endDate,
      $joinDate
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
  public function getRollingMembershipTypes() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type 1',
      'minimum_fee' => 120,
      'period_type' => 'rolling',
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type 2',
      'minimum_fee' => 240,
      'period_type' => 'rolling',
    ]);
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
