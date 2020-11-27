<?php

use CRM_MembershipExtras_Service_MembershipTypeDurationCalculator as MembershipTypeDurationCalculator;
use CRM_MembershipExtras_Service_MembershipTypeAmountProrater as MembershipTypeAmount;
use CRM_MembershipExtras_Service_MembershipTypeTaxAmountCalculator as MembershipTypeTaxAmountCalculator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeAmountTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipTypeAmountProraterTest extends BaseHeadlessTest {

  private $startDate;

  private $endDate;

  private $defaultMembershipTypeParams = [
    'duration_unit' => 'year',
    'period_type' => 'fixed',
    'duration_interval' => 1,
    'fixed_period_start_day' => 101,
    'fixed_period_rollover_day' => 1231,
    'domain_id' => 1,
    'member_of_contact_id' => 1,
    'financial_type_id' => 1,
    'minimum_fee' => 120,
  ];

  public function setUp() {
    $this->startDate = new DateTime();
    $this->endDate = new DateTime();
  }

  public function testCalculateProRata() {
    $originalDurationDays = 360;
    $calculatedDays = 60;
    $membershipType = MembershipTypeFabricator::fabricateWithBAO($this->defaultMembershipTypeParams);

    // Expected prorata: 60/360 * 120 = 20
    $expectedProrata = 20;

    $membershipTypeDuration = $this->getMembershipTypeDuration($originalDurationDays, $calculatedDays);
    $taxAmount = 0;
    $membershipTypeTaxAmount = $this->getMembershipTypeTaxAmount($membershipType, $expectedProrata, $taxAmount);
    $membershipTypeAmount = new MembershipTypeAmount($membershipTypeDuration, $membershipTypeTaxAmount);

    $proRata = $membershipTypeAmount->calculateProRata($membershipType, $this->startDate, $this->endDate);
    $this->assertEquals($expectedProrata, $proRata);
  }

  public function testCalculateProRataWithTax() {
    $originalDurationDays = 360;
    $calculatedDays = 60;
    $membershipType = MembershipTypeFabricator::fabricateWithBAO($this->defaultMembershipTypeParams, TRUE);

    // Expected Prorata: 60/360 * 120
    $expectedProrata = 20;
    $taxAmount = 30;
    $expectedTotal = $expectedProrata + $taxAmount;

    $membershipTypeDuration = $this->getMembershipTypeDuration($originalDurationDays, $calculatedDays);
    $membershipTypeTaxAmount = $this->getMembershipTypeTaxAmount($membershipType, $expectedProrata, $taxAmount);
    $membershipTypeAmount = new MembershipTypeAmount($membershipTypeDuration, $membershipTypeTaxAmount);

    $proRata = $membershipTypeAmount->calculateProRata($membershipType, $this->startDate, $this->endDate);
    $this->assertEquals($expectedTotal, $proRata);
  }

  private function getMembershipTypeDuration($originalDays, $calculatedDays) {
    $membershipTypeDurationCalculator = $this->prophesize(MembershipTypeDurationCalculator::class);
    $membershipTypeDurationCalculator->calculateOriginalInDays()->willReturn($originalDays);

    $membershipTypeDurationCalculator->calculateDaysBasedOnDates($this->startDate, $this->endDate, NULL)->willReturn($calculatedDays);

    return $membershipTypeDurationCalculator->reveal();
  }

  private function getMembershipTypeTaxAmount($membershipType, $prorata, $amount = 0) {
    $membershipTypeTaxAmount = $this->prophesize(MembershipTypeTaxAmountCalculator::class);
    $membershipTypeTaxAmount->calculateTax($membershipType, $prorata)->willReturn($amount);

    return $membershipTypeTaxAmount->reveal();
  }

}
