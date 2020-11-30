<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as MembershipInstalmentsSchedule;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator as InvalidMembershipTypeInstalmentCalculator;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;

/**
 * Class CRM_MembershipExtras_Service_MembershipInstalmentsScheduleTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipInstalmentsScheduleTest extends BaseHeadlessTest {

  /**
   * Tests Rolling Monthly Instalment Schedule Amounts
   *
   * @throws Exception
   */
  public function testRollingMonthlyInstalmentAmounts() {
    $monthlyInstalmentCount = MembershipInstalmentsSchedule::MONTHLY_INSTALMENT_COUNT;
    $membershipTypes = $this->getRollingMembershipTypes();
    $instalments = $this->getRollingMembershipInstalments(
      $membershipTypes,
      MembershipInstalmentsSchedule::MONTHLY
    );

    $this->assertCount($monthlyInstalmentCount, $instalments);

    $expectedAmount = MoneyUtilities::roundToPrecision(
      ($membershipTypes[0]->minimum_fee + $membershipTypes[1]->minimum_fee) / $monthlyInstalmentCount,
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
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotYearly() {
    $membershipType1 = MembershipTypeFabricator::fabricateWithBAO(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'month', 'name' => 'xyz']
    ), TRUE);

    $membershipType2 = MembershipTypeFabricator::fabricateWithBAO(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'name' => 'xyz']
    ), TRUE);

    $this->expectException(InvalidMembershipTypeInstalmentCalculator::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUALLY
    );
  }

  /**
   * Tests exception when providing incorrect duration interval
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   * @throws CiviCRM_API3_Exception
   */
  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotOneYear() {
    $membershipType1 = MembershipTypeFabricator::fabricateWithBAO(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 2, 'name' => 'xyz']
    ), TRUE);
    $membershipType2 = MembershipTypeFabricator::fabricateWithBAO(array_merge($this->defaultRollingMembershipTypeParams,
      ['duration_unit' => 'year', 'duration_interval' => 1, 'name' => 'xyz']
    ), TRUE);

    $this->expectException(InvalidMembershipTypeInstalmentCalculator::class);
    $this->getMembershipInstalmentsSchedule(
      [$membershipType1, $membershipType2], MembershipInstalmentsSchedule::ANNUALLY
    );
  }

  /**
   * @param $membershipTypes
   * @param $schedule
   * @return mixed
   * @throws Exception
   */
  private function getRollingMembershipInstalments($membershipTypes, $schedule) {
    $membershipInstalmentsSchedule = $this->getMembershipInstalmentsSchedule($membershipTypes, $schedule);

    $mockedDate = $this->mockMembershipDates();

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $membershipTypes[0]->id,
      $mockedDate['start_date'],
      $mockedDate['end_date'],
      $mockedDate['join_date']
    );

    return $membershipInstalmentsSchedule->generate(
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
   * @throws Exception
   */
  private function getRollingMembershipTypes() {
    $membershipType1 = MembershipTypeFabricator::fabricateWithBAO(array_merge($this->defaultRollingMembershipTypeParams,
      ['name' => 'Rolling Membership Type 1', 'minimum_fee' => 120]),
      TRUE
    );

    $membershipType2 = MembershipTypeFabricator::fabricateWithBAO(array_merge($this->defaultRollingMembershipTypeParams,
      ['name' => 'Rolling Membership Type 2', 'minimum_fee' => 240]),
      TRUE
    );

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

}
