<?php

use CRM_MembershipExtras_Service_MembershipTypeDuration as MembershipTypeDuration;
use CRM_MembershipExtras_Service_MembershipTypeAmount as MembershipTypeAmount;
use CRM_MembershipExtras_Service_MembershipTypeTaxAmount as MembershipTypeTaxAmount;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;

/**
 * Class CRM_MembershipExtras_Service_MembershipTypeAmountTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipTypeAmountTest extends BaseHeadlessTest {

  public function testCalculateProRata() {
    $originalDurationDays = 360;
    $calculatedDays = 60;
    $membershipType = MembershipTypeFabricator::fabricate(['minimum_fee' => 120]);
    $expectedProrata = 20; //i.e 60/360 *120
    $membershipTypeDuration = $this->getMembershipTypeDuration($originalDurationDays, $calculatedDays);
    $membershipTypeTaxAmount = $this->getMembershipTypeTaxAmount($membershipType);
    $membershipTypeAmount = new MembershipTypeAmount($membershipTypeDuration, $membershipTypeTaxAmount);
    $proRata = $membershipTypeAmount->calculateProRata($membershipType, new DateTime(), new DateTime());
    $this->assertEquals($expectedProrata, $proRata);
  }

  public function testCalculateProRataWithTax() {
    $originalDurationDays = 360;
    $calculatedDays = 60;
    $membershipType = MembershipTypeFabricator::fabricate(['minimum_fee' => 120]);
    $expectedProrata = 30; //i.e 60/360 *120 + 10tax
    $membershipTypeDuration = $this->getMembershipTypeDuration($originalDurationDays, $calculatedDays);
    $membershipTypeTaxAmount = $this->getMembershipTypeTaxAmount($membershipType, 10);
    $membershipTypeAmount = new MembershipTypeAmount($membershipTypeDuration, $membershipTypeTaxAmount);
    $proRata = $membershipTypeAmount->calculateProRata($membershipType, new DateTime(), new DateTime());
    $this->assertEquals($expectedProrata, $proRata);
  }

  private function getMembershipTypeDuration($originalDays, $calculatedDays) {
    $membershipTypeDuration = $this->prophesize(MembershipTypeDuration::class);
    $membershipTypeDuration->calculateOriginalInDays()->willReturn($originalDays);
    $membershipTypeDuration->calculateDaysBasedOnDates(new DateTime(), new DateTime())->willReturn($calculatedDays);

    return $membershipTypeDuration->reveal();
  }

  private function getMembershipTypeTaxAmount($membershipType, $amount = 0) {
    $membershipTypeTaxAmount = $this->prophesize(MembershipTypeTaxAmount::class);
    $membershipTypeTaxAmount->calculateTax($membershipType)->willReturn($amount);

    return $membershipTypeTaxAmount->reveal();
  }
}

