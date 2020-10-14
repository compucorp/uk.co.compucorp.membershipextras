<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_Group as GroupFabricator;
use CRM_MembershipExtras_Test_Fabricator_AutoMembershipUpgradeRule as AutoMembershipUpgradeRuleFabricator;
use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType as TriggerDateType;
use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit as PeriodUnit;
use CRM_MembershipExtras_Service_AutoUpgradableMembershipChecker as AutoUpgradeService;

/**
 * Class CRM_MembershipExtras_Service_AutoUpgradableMembershipCheckerTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_AutoUpgradableMembershipCheckerTest extends BaseHeadlessTest {

  private $testContactId;

  private $testYearlyFromMembershipType;

  private $testYearlyToMembershipType;

  private $testMonthlyFromMembershipType;

  private $testMonthlyToMembershipType;

  public function setUp() {
    $this->createTestContacT();
    $this->createTestMembershipTypes();
  }

  private function createTestContact() {
    $this->testContactId = CRM_MembershipExtras_Test_Fabricator_Contact::fabricate()['id'];
  }

  private function createTestMembershipTypes() {
    $this->testYearlyFromMembershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Test From Membership Yearly',
      'period_type' => 'rolling',
      'minimum_fee' => 100,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $this->testYearlyToMembershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Test To Membership Yearly',
      'period_type' => 'rolling',
      'minimum_fee' => 100,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $this->testMonthlyFromMembershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Test From Membership Monthly',
      'period_type' => 'rolling',
      'minimum_fee' => 100,
      'duration_interval' => 1,
      'duration_unit' => 'month',
    ]);

    $this->testMonthlyToMembershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Test To Membership Monthly',
      'period_type' => 'rolling',
      'minimum_fee' => 100,
      'duration_interval' => 1,
      'duration_unit' => 'month',
    ]);
  }

  public function testThatYearlyMembershipUpgradableIfItsTypeConfiguredInAnyRuleFromFieldAndLengthMatch() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year')),
      'start_date' => date('Y-m-d', strtotime('-1 year')),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testYearlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

  public function testThatMonthlyMembershipUpgradableIfItsTypeConfiguredInAnyRuleFromFieldAndLengthMatch() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testMonthlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testMonthlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::MONTHS,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testMonthlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 month')),
      'start_date' => date('Y-m-d', strtotime('-1 month')),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testMonthlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

  public function testThatMembershipUpgradableIfTheMemberPartOfFilterGroup() {
    $filterGroup = GroupFabricator::fabricate();
    civicrm_api3('GroupContact', 'create', [
      'group_id' => $filterGroup['id'],
      'contact_id' => $this->testContactId,
    ]);

    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
      'filter_group' => $filterGroup['id'],
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year')),
      'start_date' => date('Y-m-d', strtotime('-1 year')),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testYearlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

  public function testThatNotUpgradableMembershipIfTheMemberNotParOfTheConfiguredFilterGroup() {
    $filterGroup = GroupFabricator::fabricate();

    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
      'filter_group' => $filterGroup['id'],
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year')),
      'start_date' => date('Y-m-d', strtotime('-1 year')),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertNull($upgradeMembershipTypeId);
  }

  public function testThatNotUpgradableMembershipIfItsTypeNotConfiguredInAnyRuleFromField() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyToMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year')),
      'start_date' => date('Y-m-d', strtotime('-1 year')),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertNull($upgradeMembershipTypeId);
  }

  public function testThatYearlyMembershipNotUpgradableIfItsLengthLessThanTheConfiguredBasedOnTheMembershipJoinDate() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_SINCE,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year +1 month')),
      'start_date' => date('Y-m-d', strtotime('-1 year -1 month')),
      'end_date' => date('Y-m-d'),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertNull($upgradeMembershipTypeId);
  }

  public function testThatYearlyMembershipNotUpgradableIfItsLengthLessThanTheConfiguredBasedOnTheMembershipStartDate() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year -1 month')),
      'start_date' => date('Y-m-d', strtotime('-1 year +1 month')),
      'end_date' => date('Y-m-d'),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertNull($upgradeMembershipTypeId);
  }

  public function testThatMonthlyMembershipNotUpgradableIfItsLengthLessThanTheConfiguredBasedOnTheMembershipJoinDate() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testMonthlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testMonthlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_SINCE,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::MONTHS,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testMonthlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 month +5 days')),
      'start_date' => date('Y-m-d', strtotime('-1 month')),
      'end_date' => date('Y-m-d'),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertNull($upgradeMembershipTypeId);
  }

  public function testThatMonthlyMembershipNotUpgradableIfItsLengthLessThanTheConfiguredBasedOnTheMembershipStartDate() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testMonthlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testMonthlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::MONTHS,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testMonthlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 month')),
      'start_date' => date('Y-m-d', strtotime('-1 month +5 days')),
      'end_date' => date('Y-m-d'),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertNull($upgradeMembershipTypeId);
  }

  public function testThatOnlyTheFirstMatchingRuleWillUsedInCalculation() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
    ]);

    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'test 2',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testMonthlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year')),
      'start_date' => date('Y-m-d', strtotime('-1 year')),
    ]);

    $AutoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $AutoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testYearlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

}
