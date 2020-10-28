<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;

/**
 * Class PaymentScheduleTest
 */
class PaymentScheduleTest extends BaseHeadlessTest {

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function testGetMonthlyRollingMembershipTypeSchedule() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type',
      'period_type' => 'rolling',
      'duration_unit' => 'year',
      'minimum_fee' => 120,
      'duration_interval' => 1,
    ], TRUE);

    $scheduleInstalment = civicrm_api3('PaymentSchedule', 'get', [
      'sequential' => 1,
      'membership_type_id' => $membershipType->id,
      'schedule' => 'monthly',
    ])['values'];

    $this->assertCount(12, $scheduleInstalment);

    $currencySymbol = CRM_Core_BAO_Country::defaultCurrencySymbol();
    $expectedAmount = $currencySymbol . 10;
    $expectedTaxAmount = $currencySymbol . 0;

    $startDate = CRM_Member_BAO_MembershipType::getDatesForMembershipType(
      $membershipType->id,
      NULL,
      NULL,
      NULL
    )['start_date'];

    $instalmentDate = new DateTime($startDate);

    foreach ($scheduleInstalment as $instalment) {
      $this->assertEquals($expectedAmount, $instalment['instalment_amount']);
      $this->assertEquals($expectedTaxAmount, $instalment['instalment_tax_amount']);
      $this->assertEquals(
        CRM_Utils_Date::customFormat($instalmentDate->format('Y-m-d')),
        $instalment['instalment_date']
      );
      $instalmentDate->add(new DateInterval('P1M'));
    }
  }

}
