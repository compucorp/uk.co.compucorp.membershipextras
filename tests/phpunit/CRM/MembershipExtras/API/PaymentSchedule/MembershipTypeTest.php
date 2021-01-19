<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as Schedule;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeAnnualCalculator as FixedPeriodTypeCalculator;

/**
 * Class CRM_MembershipExtras_API_PaymentSchedule_MembershipTypeTest
 *
 * @group headless
 */
class CRM_MembershipExtras_API_PaymentSchedule_MembershipTypeTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;

  /**
   * Tests exception is thrown if invalid schedule is passed
   *
   * @throws API_Exception
   */
  public function testInvalidScheduleWillThrowAnException() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type',
      'period_type' => 'rolling',
      'duration_unit' => 'year',
      'minimum_fee' => 120,
      'duration_interval' => 1,

    ]);
    $this->expectException(API_Exception::class);
    $params['schedule'] = 'xyz';
    $params['membership_type_id'] = $membershipType['id'];
    $schedule = new CRM_MembershipExtras_API_PaymentSchedule_MembershipType($params);
    $schedule->getPaymentSchedule();
  }

  /**
   * Test get monthly instalments for rolling membership type
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   * @throws CiviCRM_API3_Exception
   */
  public function testGetMonthlyInstalmentsForRollingMembershipType() {
    $paymentSchedule = $this->mockRollingMembershipTypeSchedule(Schedule::MONTHLY);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $this->assertNotEmpty($instalments);
    $this->assertCount(12, $instalments);
  }

  /**
   * Test get quarterly instalments for rolling membership type
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   * @throws CiviCRM_API3_Exception
   */
  public function testGetQuarterlyInstalmentsForRollingMembershipType() {
    $paymentSchedule = $this->mockRollingMembershipTypeSchedule(Schedule::QUARTERLY);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $this->assertNotEmpty($instalments);
    $this->assertCount(4, $instalments);
  }

  /**
   * Tests get annual instalments for rolling membership type
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   * @throws CiviCRM_API3_Exception
   */
  public function testGetAnnualInstalmentsForRollingMembershipType() {
    $paymentSchedule = $this->mockRollingMembershipTypeSchedule(Schedule::ANNUAL);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $this->assertNotEmpty($instalments);
    $this->assertCount(1, $instalments);
  }

  /**
   * Tests format instalments
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   * @throws CiviCRM_API3_Exception
   */
  public function testFormatInstalments() {
    $paymentSchedule = $this->mockRollingMembershipTypeSchedule(Schedule::ANNUAL);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $pendingStatusLabel = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => "contribution_status",
      'name' => "pending",
    ])['values'][0]['label'];
    $formattedInstalments = $paymentSchedule->formatInstalments($instalments);
    foreach ($formattedInstalments as $formattedInstalment) {
      $this->assertEquals($pendingStatusLabel, $formattedInstalment['instalment_status']);
    }
  }

  /**
   * Tests get annual instalments for fixed membership type
   *
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment
   * @throws CiviCRM_API3_Exception
   */
  public function testGetAnnualInstalmentsForFixedMembershipType() {
    $paymentSchedule = $this->mockFixedMembershipTypeSchedule(Schedule::ANNUAL);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $this->assertNotEmpty($instalments);
    $this->assertCount(1, $instalments);
  }

  private function mockRollingMembershipTypeSchedule($schedule) {
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type',
      'period_type' => 'rolling',
      'duration_unit' => 'year',
      'minimum_fee' => 120,
      'duration_interval' => 1,
    ]);

    $params = [
      'schedule' => $schedule,
      'membership_type_id' => $membershipType['id'],
    ];

    return new CRM_MembershipExtras_API_PaymentSchedule_MembershipType($params);

  }

  private function mockFixedMembershipTypeSchedule($schedule) {
    $membershipType = MembershipTypeFabricator::fabricate([
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
    $this->mockSettings($membershipType['id'], FixedPeriodTypeCalculator::BY_DAYS);

    $params = [
      'schedule' => $schedule,
      'membership_type_id' => $membershipType['id'],
      //make sure we pass start date in params,
      //so the calculators can calculate correct dates and amounts for
      // fixed period membership type.
      'star_date' => new DateTime('today'),
    ];

    return new CRM_MembershipExtras_API_PaymentSchedule_MembershipType($params);
  }

}
