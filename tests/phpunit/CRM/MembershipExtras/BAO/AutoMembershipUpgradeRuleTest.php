<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_Group as GroupFabricator;
use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit as PeriodUnitSelectValues;
use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType as TriggerDateTypeSelectValues;
use CRM_MembershipExtras_BAO_AutoMembershipUpgradeRule as AutoMembershipUpgradeRule;

/**
 * Class CRM_MembershipExtras_BAO_AutoMembershipUpgradeRuleTest
 *
 * @group headless
 */
class CRM_MembershipExtras_BAO_AutoMembershipUpgradeRuleTest extends BaseHeadlessTest {

  private $fromMembershipType;

  private $toMembershipType;

  private $group;

  public function setUp() {
    $this->fromMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test From Membership Type',
        'period_type' => 'rolling',
        'minimum_fee' => 100,
      ],
      TRUE
    );

    $this->toMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test To  Membership Type',
        'period_type' => 'rolling',
        'minimum_fee' => 100,
      ],
      TRUE
    );

    $this->group = GroupFabricator::fabricate();
  }

  public function testCreate() {
    $params['name'] = 'test_1';
    $params['label'] = 'Test 1';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['upgrade_trigger_date_type'] = TriggerDateTypeSelectValues::MEMBER_SINCE;
    $params['period_length'] = PeriodUnitSelectValues::YEARS;
    $params['period_length_unit'] = 1;
    $params['filter_group'] = $this->group['id'];
    $params['is_active'] = 1;

    $newUpgradeRule = AutoMembershipUpgradeRule::create($params);

    $this->assertTrue(!empty($newUpgradeRule->id));
    $this->assertEquals('Test 1', $newUpgradeRule->label);
  }

  public function testCreateWillGenerateNameAutomatically() {
    $params['label'] = 'Test 2';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['upgrade_trigger_date_type'] = TriggerDateTypeSelectValues::MEMBER_SINCE;
    $params['period_length'] = PeriodUnitSelectValues::YEARS;
    $params['period_length_unit'] = 1;
    $params['filter_group'] = $this->group['id'];
    $params['is_active'] = 1;

    $newUpgradeRule = AutoMembershipUpgradeRule::create($params);

    $this->assertEquals('test_2', $newUpgradeRule->name);
  }

  public function testCreatingFirstRuleWillSetWeightToOne() {
    $params['label'] = 'Test 3';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['upgrade_trigger_date_type'] = TriggerDateTypeSelectValues::MEMBER_SINCE;
    $params['period_length'] = PeriodUnitSelectValues::YEARS;
    $params['period_length_unit'] = 1;
    $params['filter_group'] = $this->group['id'];
    $params['is_active'] = 1;

    $newUpgradeRule = AutoMembershipUpgradeRule::create($params);

    $this->assertEquals(1, $newUpgradeRule->weight);
  }

  public function testCreatingRuleWillSetWeightToMaxOfPreviousRulePlusOne() {
    $params['label'] = 'Test 4';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['upgrade_trigger_date_type'] = TriggerDateTypeSelectValues::MEMBER_SINCE;
    $params['period_length'] = PeriodUnitSelectValues::YEARS;
    $params['period_length_unit'] = 1;
    $params['filter_group'] = $this->group['id'];
    $params['is_active'] = 1;
    AutoMembershipUpgradeRule::create($params);

    $params['label'] = 'Test 5';
    AutoMembershipUpgradeRule::create($params);

    $params['label'] = 'Test 6';
    $newUpgradeRuleThree = AutoMembershipUpgradeRule::create($params);

    $this->assertEquals(3, $newUpgradeRuleThree->weight);
  }

  public function testCreateWillSetPeriodFieldsToOneYearIfNotSet() {
    $params['label'] = 'Test 7';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['upgrade_trigger_date_type'] = TriggerDateTypeSelectValues::MEMBER_SINCE;
    $params['is_active'] = 1;

    $newUpgradeRule = AutoMembershipUpgradeRule::create($params);
    $newUpgradeRule->find(TRUE);

    $this->assertEquals(1, $newUpgradeRule->period_length);
    $this->assertEquals(PeriodUnitSelectValues::YEARS, $newUpgradeRule->period_length_unit);
  }

  public function testCreateWillSetUpgradeDateTypeTriggerToMemberSinceDateIfNotSet() {
    $params['label'] = 'Test 8';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['is_active'] = 1;
    $newUpgradeRule = AutoMembershipUpgradeRule::create($params);
    $newUpgradeRule->find(TRUE);

    $this->assertEquals(TriggerDateTypeSelectValues::MEMBER_SINCE, $newUpgradeRule->upgrade_trigger_date_type);
  }

  public function testCreateWillThrowExceptionIfPeriodLengthIsNegativeInteger() {
    $this->expectException(PEAR_Exception::class);
    $this->expectExceptionMessage('DB Error: unknown error');

    $params['label'] = 'Test 9';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['period_length_unit'] = -2;
    $params['is_active'] = 1;
    AutoMembershipUpgradeRule::create($params);
  }

  public function testGetById() {
    $params['name'] = 'test_1';
    $params['label'] = 'Test 1';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['upgrade_trigger_date_type'] = TriggerDateTypeSelectValues::MEMBER_SINCE;
    $params['period_length'] = PeriodUnitSelectValues::YEARS;
    $params['period_length_unit'] = 1;
    $params['filter_group'] = $this->group['id'];
    $params['is_active'] = 1;
    $params = [
      'name' => 'test_10',
      'label' => 'Test 10',
      'from_membership_type_id' => $this->fromMembershipType['id'],
      'to_membership_type_id' => $this->toMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateTypeSelectValues::MEMBER_SINCE,
      'period_length' => PeriodUnitSelectValues::YEARS,
      'period_length_unit' => 1,
      'filter_group' => $this->group['id'],
      'is_active' => 1,
    ];
    $newUpgradeRule = AutoMembershipUpgradeRule::create($params);

    $upgradeRule = AutoMembershipUpgradeRule::getById($newUpgradeRule->id);
    foreach ($params as $paramKey => $paramValue) {
      $this->assertEquals($params[$paramKey], $upgradeRule->{$paramKey});
    }
  }

  public function testDeleteById() {
    $params['name'] = 'test_1';
    $params['label'] = 'Test 1';
    $params['from_membership_type_id'] = $this->fromMembershipType['id'];
    $params['to_membership_type_id'] = $this->toMembershipType['id'];
    $params['upgrade_trigger_date_type'] = TriggerDateTypeSelectValues::MEMBER_SINCE;
    $params['period_length'] = PeriodUnitSelectValues::YEARS;
    $params['period_length_unit'] = 1;
    $params['filter_group'] = $this->group['id'];
    $params['is_active'] = 1;
    $params = [
      'name' => 'test_10',
      'label' => 'Test 10',
      'from_membership_type_id' => $this->fromMembershipType['id'],
      'to_membership_type_id' => $this->toMembershipType['id'],
      'upgrade_trigger_date_type' => TriggerDateTypeSelectValues::MEMBER_SINCE,
      'period_length' => PeriodUnitSelectValues::YEARS,
      'period_length_unit' => 1,
      'filter_group' => $this->group['id'],
      'is_active' => 1,
    ];
    $newUpgradeRule = AutoMembershipUpgradeRule::create($params);

    AutoMembershipUpgradeRule::deleteById($newUpgradeRule->id);

    $deletedUpgradeRule = AutoMembershipUpgradeRule::getById($newUpgradeRule->id);
    $this->assertEquals(0, $deletedUpgradeRule->N);
  }

}
