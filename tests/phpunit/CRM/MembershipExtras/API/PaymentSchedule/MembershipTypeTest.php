<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentsSchedule as Schedule;

/**
 * Class CRM_MembershipExtras_API_PaymentSchedule_MembershipTypeTest
 *
 * @group headless
 */
class CRM_MembershipExtras_API_PaymentSchedule_MembershipTypeTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;

  /**
   * @throws API_Exception
   */
  public function testInvalidScheduleWillThrowAnException() {
    $this->expectException(API_Exception::class);
    $params['schedule'] = 'xyz';
    new CRM_MembershipExtras_API_PaymentSchedule_MembershipType($params);
  }

  /**
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   * @throws CiviCRM_API3_Exception
   */
  public function testGetMonthlyInstalmentsForRollingMembershipType() {
    $paymentSchedule = $this->mockRollingMembershipTypeSchedule(Schedule::MONTHLY);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $this->assertNotEmpty($instalments);
    $this->assertCount(Schedule::MONTHLY_INSTALMENT_COUNT, $instalments);
  }

  /**
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   * @throws CiviCRM_API3_Exception
   */
  public function testGetQuarterlyInstalmentsForRollingMembershipType() {
    $paymentSchedule = $this->mockRollingMembershipTypeSchedule(Schedule::QUARTERLY);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $this->assertNotEmpty($instalments);
    $this->assertCount(Schedule::QUARTERLY_INSTALMENT_COUNT, $instalments);
  }

  /**
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   * @throws CiviCRM_API3_Exception
   */
  public function testGetAnnualInstalmentsForRollingMembershipType() {
    $paymentSchedule = $this->mockRollingMembershipTypeSchedule(Schedule::ANNUAL);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $this->assertNotEmpty($instalments);
    $this->assertCount(Schedule::ANNUAL_INSTALMENT_COUNT, $instalments);
  }

  /**
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
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
   * @throws CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentCalculator
   * @throws CiviCRM_API3_Exception
   */
  public function testGetAnnualInstalmentsForFixedMembershipType() {
    $paymentSchedule = $this->mockFixedMembershipTypeSchedule(Schedule::ANNUAL);
    $instalments = $paymentSchedule->getPaymentSchedule();
    $this->assertNotEmpty($instalments);
    $this->assertCount(Schedule::ANNUAL_INSTALMENT_COUNT, $instalments);
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
    $this->mockSettings($membershipType['id'], CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator::BY_DAYS);

    $params = [
      'schedule' => $schedule,
      'membership_type_id' => $membershipType['id'],
    ];

    return new CRM_MembershipExtras_API_PaymentSchedule_MembershipType($params);
  }

}
