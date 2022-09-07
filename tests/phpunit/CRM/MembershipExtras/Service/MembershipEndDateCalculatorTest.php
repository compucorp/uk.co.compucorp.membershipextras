<?php
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Service_MembershipEndDateCalculator as MembershipEndDateCalculator;

/**
 * Class CRM_MembershipExtras_Service_MembershipEndDateCalculatorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipEndDateCalculatorTest extends BaseHeadlessTest {

  /**
   * List of scenarios with rolling membership to be tested.
   *
   * @var array
   */
  private $rollingMembershipScenarios = [];

  /**
   * List of scenarios with fixed membership to be tested.
   *
   * @var array
   */
  private $fixedMembershipScenarios = [];

  public function setUp() {
    $this->rollingMembershipScenarios = [
      'normal year 01/01 => 12/31' => [
        'duration_interval' => 12,
        'duration_unit' => 'month',
        'start_date' => '2001-01-01',
        'expected_next_end_date' => '20021231',
        'expected_previous_end_date' => '20001231',
      ],
      'leap year 01/01 => 12/31' => [
        'duration_interval' => 12,
        'duration_unit' => 'month',
        'start_date' => '2020-01-01',
        'expected_next_end_date' => '20211231',
        'expected_previous_end_date' => '20191231',
      ],
      'normal year 01/06 => 05/30' => [
        'duration_interval' => 12,
        'duration_unit' => 'month',
        'start_date' => '2001-06-01',
        'expected_next_end_date' => '20030531',
        'expected_previous_end_date' => '20010531',
      ],
      'normal year 0/01 => 12/30' => [
        'duration_interval' => 6,
        'duration_unit' => 'month',
        'start_date' => '2020-01-01',
        'expected_next_end_date' => '20201230',
        'expected_previous_end_date' => '20191230',
      ],
      'normal year 0/01 => 12/30' => [
        'duration_interval' => 3,
        'duration_unit' => 'year',
        'start_date' => '2020-01-01',
        'expected_next_end_date' => '20251231',
        'expected_previous_end_date' => '20191231',
      ],
      'leap year 02/29 => 02/28' => [
        'duration_interval' => 2,
        'duration_unit' => 'year',
        'start_date' => '2020-02-29',
        'expected_next_end_date' => '20240228',
        'expected_previous_end_date' => '20200228',
      ],
      'current date' => [
        'duration_interval' => 12,
        'duration_unit' => 'month',
        'start_date' => date('Ymd'),
        'expected_next_end_date' => date('Ymd', strtotime('+2 years -1 day')),
        'expected_previous_end_date' => date('Ymd', strtotime('-1 day')),
      ],
    ];

    $this->fixedMembershipScenarios = [
      'fixed 01/01 => 12/31' => [
        'fixed_period_start_day' => '0101',
        'fixed_period_rollover_day' => '1231',
        'start_date' => '2001-01-01',
        'expected_next_end_date' => '20021231',
        'expected_previous_end_date' => '20001231',
      ],
      'fixed 12/01 => 11/30' => [
        'fixed_period_start_day' => '1201',
        'fixed_period_rollover_day' => '1130',
        'start_date' => '2001-01-01',
        'expected_next_end_date' => '20021130',
        'expected_previous_end_date' => '20001130',
      ],
      'current date 01/01 => 12/31' => [
        'fixed_period_start_day' => '0101',
        'fixed_period_rollover_day' => '1231',
        'start_date' => date('Y-m-d'),
        'expected_next_end_date' => date('Y1231', strtotime('+1 year')),
        'expected_previous_end_date' => date('Y1231', strtotime('-1 year')),
      ],
    ];
  }

  public function testRollingMembershipsEndDateCalculation() {
    $contact = ContactFabricator::fabricate();

    foreach ($this->rollingMembershipScenarios as $scenarioName => $scenario) {
      $membershiptType = MembershipTypeFabricator::fabricate([
        'name' => 'Main Rolling Membership - ' . $scenarioName,
        'period_type' => 'rolling',
        'minimum_fee' => 60,
        'duration_interval' => $scenario['duration_interval'],
        'duration_unit' => $scenario['duration_unit'],
      ]);
      $membership = MembershipFabricator::fabricate([
        'contact_id' => $contact['id'],
        'membership_type_id' => $membershiptType['id'],
        'join_date' => $scenario['start_date'],
        'start_date' => $scenario['start_date'],
        'financial_type_id' => 'Member Dues',
        'skipLineItem' => 1,
      ]);

      $nextEndDate = MembershipEndDateCalculator::calculate($membership['id']);
      $this->assertEquals($scenario['expected_next_end_date'], $nextEndDate, "Failed calculating next end date for scenario $scenarioName!");

      $previousEndDate = MembershipEndDateCalculator::calculatePreviousEndDate($membership['id']);
      $this->assertEquals($scenario['expected_previous_end_date'], $previousEndDate, "Failed calculating previous end date for scenario $scenarioName!");
    }
  }

  public function testFixedMembershipsEndDateCalculation() {
    $contact = ContactFabricator::fabricate();

    foreach ($this->fixedMembershipScenarios as $scenarioName => $scenario) {
      $membershiptType = MembershipTypeFabricator::fabricate([
        'name' => 'Test Fixed Membership',
        'period_type' => 'fixed',
        'minimum_fee' => 0,
        'duration_unit' => 'year',
        'duration_interval' => 1,
        'fixed_period_start_day' => $scenario['fixed_period_start_day'],
        'fixed_period_rollover_day' => $scenario['fixed_period_rollover_day'],
      ]);
      $membership = MembershipFabricator::fabricate([
        'contact_id' => $contact['id'],
        'membership_type_id' => $membershiptType['id'],
        'join_date' => $scenario['start_date'],
        'start_date' => $scenario['start_date'],
        'financial_type_id' => 'Member Dues',
        'skipLineItem' => 1,
      ]);

      $nextEndDate = MembershipEndDateCalculator::calculate($membership['id']);
      $this->assertEquals($scenario['expected_next_end_date'], $nextEndDate, "Failed calculating next end date for $scenarioName!");

      $previousEndDate = MembershipEndDateCalculator::calculatePreviousEndDate($membership['id']);
      $this->assertEquals($scenario['expected_previous_end_date'], $previousEndDate, "Failed calculating previous end date for $scenarioName!");
    }
  }

}
