<?php

use CRM_MembershipExtras_Service_MembershipTypeTaxAmount as MembershipTypeTaxAmount;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipTypeInstalmentAmount as MembershipTypeInstalmentAmount;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalmentAmount as InvalidMembershipTypeInstalmentAmount;


/**
 * Class CRM_MembershipExtras_Service_MembershipTypeInstalmentAmountTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipTypeInstalmentAmountTest extends BaseHeadlessTest {

  public function testExceptionIsThrownIfMembershipTypeIsNotFixed() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'period_type' => 'rolling'
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'period' => 'fixed'
    ]);

    $membershipTypes = [$membershipType1, $membershipType2];
    $membershipTaxAmount = $this->getMembershipTypeTaxAmount($membershipTypes, 6);
    $this->setExpectedException(InvalidMembershipTypeInstalmentAmount::class, InvalidMembershipTypeInstalmentAmount::FIXED_PERIOD_TYPE);
    $this->getMembershipTypeInstalmentAmount(
      $membershipTypes,
      $membershipTaxAmount
    );
  }

  public function testExceptionIsThrownIfMembershipTypeDoesNotHaveSamePeriodStartDay() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'fixed_period_start_day' => '101'
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'fixed_period_start_day' => '201'
    ]);

    $membershipTypes = [$membershipType1, $membershipType2];
    $membershipTaxAmount = $this->getMembershipTypeTaxAmount($membershipTypes, 6);
    $this->setExpectedException(InvalidMembershipTypeInstalmentAmount::class, InvalidMembershipTypeInstalmentAmount::SAME_PERIOD_START_DAY);
    $this->getMembershipTypeInstalmentAmount(
      $membershipTypes,
      $membershipTaxAmount
    );
  }

  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotYearly() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'month'
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year'
    ]);

    $membershipTypes = [$membershipType1, $membershipType2];
    $membershipTaxAmount = $this->getMembershipTypeTaxAmount($membershipTypes, 6);
    $this->setExpectedException(InvalidMembershipTypeInstalmentAmount::class, InvalidMembershipTypeInstalmentAmount::ONE_YEAR_DURATION);
    $this->getMembershipTypeInstalmentAmount(
      $membershipTypes,
      $membershipTaxAmount
    );
  }

  public function testExceptionIsThrownIfMembershipTypeDurationUnitIsNotOneYear() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 2,
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'duration_unit' => 'year',
      'duration_interval' => 1
    ]);

    $membershipTypes = [$membershipType1, $membershipType2];
    $membershipTaxAmount = $this->getMembershipTypeTaxAmount($membershipTypes, 6);
    $this->setExpectedException(InvalidMembershipTypeInstalmentAmount::class, InvalidMembershipTypeInstalmentAmount::ONE_YEAR_DURATION);
    $this->getMembershipTypeInstalmentAmount(
      $membershipTypes,
      $membershipTaxAmount
    );
  }

  public function testCalculateFollowingInstalmentAmount() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'minimum_fee' => 120,
      'fixed_period_start_day' => '101'
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'minimum_fee' => 240,
      'fixed_period_start_day' => '101'
    ]);
    $membershipTypes = [$membershipType1, $membershipType2];
    $membershipTaxAmount = $this->getMembershipTypeTaxAmount($membershipTypes, 6);
    $membershipInstalment = $this->getMembershipTypeInstalmentAmount(
      $membershipTypes,
      $membershipTaxAmount
    );

    //FOI =  120 + 240 + (6 + 6) TAX / 12 = 31
    $this->assertEquals(31, $membershipInstalment->calculateFollowingInstalmentAmount());
  }

  public function testCalculateFirstInstalmentAmount() {
    $membershipType1 = MembershipTypeFabricator::fabricate([
      'minimum_fee' => 120,
      'fixed_period_start_day' => '101'
    ]);
    $membershipType2 = MembershipTypeFabricator::fabricate([
      'minimum_fee' => 240,
      'fixed_period_start_day' => '101'
    ]);
    $membershipTypes = [$membershipType1, $membershipType2];
    $membershipTaxAmount = $this->getMembershipTypeTaxAmount($membershipTypes, 6);
    $membershipInstalment = $this->getMembershipTypeInstalmentAmount(
      $membershipTypes,
      $membershipTaxAmount
    );

    $startDate = new DateTime('2019-06-15');
    $endDate = new DateTime('2019-06-30');
    //FIA = 15(days remaining in month)/30 (number of days in mth) * 31(FOI)
    $this->assertEquals(15.5, $membershipInstalment->calculateFirstInstalmentAmount($startDate, $endDate));
  }

  private function getMembershipTypeTaxAmount($membershipTypes, $amount = 0) {
    $membershipTypeTaxAmount = $this->prophesize(MembershipTypeTaxAmount::class);
    foreach ($membershipTypes as $membershipType) {
      $membershipTypeTaxAmount->calculateTax($membershipType)->willReturn($amount);
    }

    return $membershipTypeTaxAmount->reveal();
  }

  private function getMembershipTypeInstalmentAmount(array $membershipTypes, MembershipTypeTaxAmount $membershipTypeTaxAmount) {
    $membershipTypeDatesCalculator = new MembershipTypeDatesCalculator();
    return new MembershipTypeInstalmentAmount($membershipTypes, $membershipTypeTaxAmount, $membershipTypeDatesCalculator);
  }
}
