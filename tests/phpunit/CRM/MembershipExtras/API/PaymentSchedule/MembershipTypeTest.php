<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;

/**
 * Class CRM_MembershipExtras_API_PaymentSchedule_MembershipTypeTest
 *
 * @group headless
 */
class CRM_MembershipExtras_API_PaymentSchedule_MembershipTypeTest extends BaseHeadlessTest {

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
   * @throws API_Exception
   */
  public function testGetInstalmentsForRollingMembershipType() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type',
      'period_type' => 'rolling',
      'duration_unit' => 'year',
      'minimum_fee' => 120,
      'duration_interval' => 1,
    ]);

    $params = [
      'schedule' => CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::MONTHLY,
      'membership_type_id' => $membershipType['id'],
    ];

    $paymentSchedule = new CRM_MembershipExtras_API_PaymentSchedule_MembershipType($params);

    $instalments = $paymentSchedule->getPaymentSchedule();

    $this->assertNotEmpty($instalments);

    return ['instalments' => $instalments, 'payment_schedule' => $paymentSchedule];
  }

  /**
   * @depends clone testGetInstalmentsForRollingMembershipType
   * @param array $args
   * @throws CiviCRM_API3_Exception
   */
  public function testFormatInstalments(array $args) {
    $pendingStatusLabel = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => "contribution_status",
      'name' => "pending",
    ])['values'][0]['label'];
    $formattedInstalments = $args['payment_schedule']->formatInstalments($args['instalments']);
    foreach ($formattedInstalments as $formattedInstalment) {
      $this->assertEquals($pendingStatusLabel, $formattedInstalment['instalment_status']);
    }
  }

}
