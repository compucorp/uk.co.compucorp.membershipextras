<?php

use Civi\Core\Event\GenericHookEvent;

/**
 * Tests CRM_MembershipExtras_SettingsManager::getCustomFieldsIdsToExcludeForAutoRenew()
 * for both the existing setting-based exclusion and the new hook-based exclusion.
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFieldsTest extends BaseHeadlessTest {

  const SETTING_NAME = 'membershipextras_customgroups_to_exclude_for_autorenew';

  private function createCustomGroupWithField($groupName, $fieldName) {
    $group = \Civi\Api4\CustomGroup::create(FALSE)
      ->addValue('name', $groupName)
      ->addValue('title', $groupName)
      ->addValue('extends', 'Contribution')
      ->execute()
      ->first();

    $field = \Civi\Api4\CustomField::create(FALSE)
      ->addValue('custom_group_id', $group['id'])
      ->addValue('name', $fieldName)
      ->addValue('label', $fieldName)
      ->addValue('html_type', 'Text')
      ->addValue('data_type', 'String')
      ->execute()
      ->first();

    return [(int) $group['id'], (int) $field['id']];
  }

  public function testReturnsEmptyWhenNothingExcluded() {
    Civi::settings()->set(self::SETTING_NAME, []);

    $this->assertSame([], CRM_MembershipExtras_SettingsManager::getCustomFieldsIdsToExcludeForAutoRenew());
  }

  public function testSettingBasedExclusionReturnsGroupFields() {
    [$groupId, $fieldId] = $this->createCustomGroupWithField('test_exclude_group', 'test_excluded_field');
    Civi::settings()->set(self::SETTING_NAME, [(string) $groupId]);

    $excluded = array_map('intval', CRM_MembershipExtras_SettingsManager::getCustomFieldsIdsToExcludeForAutoRenew());

    $this->assertContains($fieldId, $excluded);
  }

  public function testListenersCanAddExcludedCustomFields() {
    Civi::settings()->set(self::SETTING_NAME, []);
    $listener = function (GenericHookEvent $event) {
      $customFieldIds =& $event->customFieldIds;
      $customFieldIds[] = 99999;
    };
    Civi::dispatcher()->addListener(
      CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields::NAME,
      $listener
    );

    try {
      $excluded = CRM_MembershipExtras_SettingsManager::getCustomFieldsIdsToExcludeForAutoRenew();
    }
    finally {
      Civi::dispatcher()->removeListener(
        CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields::NAME,
        $listener
      );
    }

    $this->assertContains(99999, $excluded);
  }

  public function testSettingAndHookExclusionsAreCombinedAndDeduped() {
    [$groupId, $fieldId] = $this->createCustomGroupWithField('test_exclude_group2', 'test_excluded_field2');
    Civi::settings()->set(self::SETTING_NAME, [(string) $groupId]);

    // Listener adds the same field plus a new one.
    $listener = function (GenericHookEvent $event) use ($fieldId) {
      $customFieldIds =& $event->customFieldIds;
      $customFieldIds[] = $fieldId;
      $customFieldIds[] = 88888;
    };
    Civi::dispatcher()->addListener(
      CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields::NAME,
      $listener
    );

    try {
      $excluded = array_map('intval', CRM_MembershipExtras_SettingsManager::getCustomFieldsIdsToExcludeForAutoRenew());
    }
    finally {
      Civi::dispatcher()->removeListener(
        CRM_MembershipExtras_Hook_CustomDispatch_AutoRenewExcludedCustomFields::NAME,
        $listener
      );
    }

    $this->assertContains($fieldId, $excluded);
    $this->assertContains(88888, $excluded);
    // The field declared by both the setting and the listener appears once.
    $this->assertCount(1, array_keys($excluded, $fieldId));
  }

}
