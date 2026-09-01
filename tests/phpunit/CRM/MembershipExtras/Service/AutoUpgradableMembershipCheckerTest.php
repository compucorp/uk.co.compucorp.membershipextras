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

  public function setUp(): void {
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testYearlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

  public function testThatRemovedContactFromRegularFilterGroupIsExcluded() {
    $filterGroup = GroupFabricator::fabricate();

    civicrm_api3('GroupContact', 'create', [
      'group_id' => $filterGroup['id'],
      'contact_id' => $this->testContactId,
    ]);
    civicrm_api3('GroupContact', 'create', [
      'group_id' => $filterGroup['id'],
      'contact_id' => $this->testContactId,
      'status' => 'Removed',
    ]);

    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'removed status test',
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertNull($upgradeMembershipTypeId);
  }

  public function testThatFilterGroupSnapshotIsTakenAtConstructionTime() {
    $filterGroup = GroupFabricator::fabricate();

    civicrm_api3('GroupContact', 'create', [
      'group_id' => $filterGroup['id'],
      'contact_id' => $this->testContactId,
    ]);

    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'snapshot test',
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

    $autoUpgradeService = new AutoUpgradeService();

    civicrm_api3('GroupContact', 'create', [
      'group_id' => $filterGroup['id'],
      'contact_id' => $this->testContactId,
      'status' => 'Removed',
    ]);

    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testYearlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

  public function testThatDeletedFilterGroupDoesNotCauseFailure() {
    $filterGroup = GroupFabricator::fabricate();
    $orphanedGroupId = $filterGroup['id'];

    $rule = AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'deleted group test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
      'filter_group' => $orphanedGroupId,
    ]);

    civicrm_api3('Group', 'delete', ['id' => $orphanedGroupId]);

    try {
      CRM_Core_DAO::executeQuery('SET FOREIGN_KEY_CHECKS = 0');
      CRM_Core_DAO::executeQuery(
        'UPDATE membershipextras_auto_membership_upgrade_rule
            SET filter_group = %1
          WHERE id = %2',
        [
          1 => [$orphanedGroupId, 'Integer'],
          2 => [$rule['id'], 'Integer'],
        ]
      );
    }
    finally {
      CRM_Core_DAO::executeQuery('SET FOREIGN_KEY_CHECKS = 1');
    }

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year')),
      'start_date' => date('Y-m-d', strtotime('-1 year')),
    ]);

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertNull($upgradeMembershipTypeId);
  }

  public function testThatMultipleDistinctFilterGroupsAreAllPreloaded() {
    $filterGroupA = GroupFabricator::fabricate(['title' => 'Filter Group A']);
    $filterGroupB = GroupFabricator::fabricate(['title' => 'Filter Group B']);

    civicrm_api3('GroupContact', 'create', [
      'group_id' => $filterGroupB['id'],
      'contact_id' => $this->testContactId,
    ]);

    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'group A rule',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testMonthlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
      'filter_group' => $filterGroupA['id'],
      'weight' => 1,
    ]);

    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'group B rule',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
      'filter_group' => $filterGroupB['id'],
      'weight' => 2,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year')),
      'start_date' => date('Y-m-d', strtotime('-1 year')),
    ]);

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testYearlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

  public function testThatRulesWithoutFilterGroupStillUpgrade() {
    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'no filter group',
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

    $autoUpgradeService = new AutoUpgradeService();
    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testYearlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

  public function testThatSmartGroupCacheIsRebuiltDuringPreload() {
    $uniqueSource = 'autoupgrade_test_' . uniqid();
    civicrm_api3('Contact', 'create', [
      'id' => $this->testContactId,
      'source' => $uniqueSource,
    ]);

    $savedSearch = civicrm_api3('SavedSearch', 'create', [
      'form_values' => [
        ['contact_type', '=', 'Individual', 0, 0],
        ['sort_name', 'LIKE', '%', 0, 1],
        ['source', '=', $uniqueSource, 0, 0],
      ],
    ]);

    $smartGroup = civicrm_api3('Group', 'create', [
      'title' => 'Smart Filter Group ' . $uniqueSource,
      'saved_search_id' => $savedSearch['id'],
      'is_active' => 1,
    ]);
    $smartGroupId = $smartGroup['id'];

    CRM_Core_DAO::executeQuery(
      "DELETE FROM civicrm_group_contact_cache WHERE group_id = %1",
      [1 => [$smartGroupId, 'Integer']]
    );
    CRM_Core_DAO::executeQuery(
      "UPDATE civicrm_group SET cache_date = NULL WHERE id = %1",
      [1 => [$smartGroupId, 'Integer']]
    );

    // Sanity check: the cache really is empty before construction.
    $rowsBefore = CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_group_contact_cache WHERE group_id = %1",
      [1 => [$smartGroupId, 'Integer']]
    );
    $this->assertEquals(0, (int) $rowsBefore);

    AutoMembershipUpgradeRuleFabricator::fabricate([
      'label' => 'smart group rebuild test',
      'from_membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'to_membership_type_id' => $this->testYearlyToMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateType::MEMBER_START,
      'period_length' => 1,
      'period_length_unit' => PeriodUnit::YEARS,
      'filter_group' => $smartGroupId,
    ]);

    $testMembership = MembershipFabricator::fabricate([
      'contact_id' => $this->testContactId,
      'membership_type_id' => $this->testYearlyFromMembershipType['id'],
      'join_date' => date('Y-m-d', strtotime('-1 year')),
      'start_date' => date('Y-m-d', strtotime('-1 year')),
    ]);

    $autoUpgradeService = new AutoUpgradeService();

    $rowsAfter = CRM_Core_DAO::singleValueQuery(
      "SELECT COUNT(*) FROM civicrm_group_contact_cache WHERE group_id = %1 AND contact_id = %2",
      [
        1 => [$smartGroupId, 'Integer'],
        2 => [$this->testContactId, 'Integer'],
      ]
    );
    $this->assertGreaterThan(
      0,
      (int) $rowsAfter,
      'Smart group cache should be rebuilt for the test contact during checker construction.'
    );

    $upgradeMembershipTypeId = $autoUpgradeService->calculateMembershipTypeToUpgradeTo($testMembership['id']);
    $this->assertEquals($this->testYearlyToMembershipType['id'], $upgradeMembershipTypeId);
  }

}
