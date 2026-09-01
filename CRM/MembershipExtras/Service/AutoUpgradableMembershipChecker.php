<?php

use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType as TriggerDateType;
use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit as PeriodUnit;

/**
 * Class CRM_MembershipExtras_Service_AutoUpgradableMembershipChecker
 *
 * This class checks if a given membership is going to be upgraded
 * during offline auto-renewal and determine to which kind of
 * membership it is going to be upgraded to.
 */
class CRM_MembershipExtras_Service_AutoUpgradableMembershipChecker {

  private $membershipUpgradeRules;

  /**
   * In-memory snapshot of contacts belonging to each filter group.
   *
   * @var array
   */
  private array $filterGroupMembers = [];

  public function __construct() {
    $this->membershipUpgradeRules = $this->getMembershipUpgradeRulesSortedByWeightAsc();
    $this->preloadFilterGroupMembers();
  }

  private function getMembershipUpgradeRulesSortedByWeightAsc() {
    $membershipUpgradeRules = new CRM_MembershipExtras_BAO_AutoMembershipUpgradeRule();
    $membershipUpgradeRules->is_active = TRUE;
    $membershipUpgradeRules->orderBy('weight ASC');
    $membershipUpgradeRules->find();

    $membershipUpgradeRulesList = [];
    while ($membershipUpgradeRules->fetch()) {
      $membershipUpgradeRulesList[] = $membershipUpgradeRules->toArray();
    }

    return $membershipUpgradeRulesList;
  }

  /**
   * Builds an in-memory snapshot of the contacts that belong to each filter group.
   *
   * We have to use raw sql query here instead of api4 as Civi\Api4\GroupContact::get()
   * only covers regular groups and not smart groups.
   */
  private function preloadFilterGroupMembers(): void {
    $filterGroupIds = [];
    foreach ($this->membershipUpgradeRules as $rule) {
      if (!empty($rule['filter_group'])) {
        $filterGroupIds[(int) $rule['filter_group']] = TRUE;
      }
    }
    $filterGroupIds = array_keys($filterGroupIds);

    if (empty($filterGroupIds)) {
      return;
    }

    foreach ($filterGroupIds as $filterGroupId) {
      $this->filterGroupMembers[$filterGroupId] = [];

      $group = new CRM_Contact_DAO_Group();
      $group->id = $filterGroupId;
      if (!$group->find(TRUE)) {
        continue;
      }

      if (!empty($group->saved_search_id) || !empty($group->children)) {
        try {
          CRM_Contact_BAO_GroupContactCache::invalidateGroupContactCache($filterGroupId);
          CRM_Contact_BAO_GroupContactCache::load($group);
        }
        catch (\Throwable $e) {
          // If the rebuild fails (e.g. another process holds the lock) we fall through and read whatever rows
          // are currently in the cache table. That preserves the previous behaviour rather than aborting the
          // whole renewal process.
          Civi::log()->error('Group cache reload failed for group id ' . $filterGroupId . ' with error: ' . $e->getMessage());
        }
      }

      $query = "
        SELECT contact_id FROM civicrm_group_contact
         WHERE group_id = %1 AND status = 'Added'
        UNION
        SELECT contact_id FROM civicrm_group_contact_cache
         WHERE group_id = %1
      ";
      $result = CRM_Core_DAO::executeQuery($query, [
        1 => [$filterGroupId, 'Integer'],
      ]);
      while ($result->fetch()) {
        $this->filterGroupMembers[$filterGroupId][(int) $result->contact_id] = TRUE;
      }
    }
  }

  /**
   * Checks if given membership going to
   * be upgraded during autorenewal and
   * to which type.
   *
   * @param int $membershipId
   *
   * @return int|NULL
   *   The membership type id that the membership
   *   will be upgraded to.
   */
  public function calculateMembershipTypeToUpgradeTo($membershipId) {
    $membership = civicrm_api3('Membership', 'getsingle', [
      'id' => $membershipId,
    ]);

    foreach ($this->membershipUpgradeRules as $membershipUpgradeRule) {
      if ($membershipUpgradeRule['from_membership_type_id'] != $membership['membership_type_id']) {
        continue;
      }

      switch ($membershipUpgradeRule['upgrade_trigger_date_type']) {
        case TriggerDateType::MEMBER_SINCE:
          $calculationStartDate = $membership['join_date'];
          break;

        case TriggerDateType::MEMBER_START:
          $calculationStartDate = $membership['start_date'];
          break;
      }

      $calculationStartDate = new DateTime($calculationStartDate);
      $calculationEndDate = new DateTime($membership['end_date']);
      $calculationEndDate->add(new DateInterval('P1D'));
      $difference = $calculationStartDate->diff($calculationEndDate);

      switch ($membershipUpgradeRule['period_length_unit']) {
        case PeriodUnit::YEARS:
          $membershipLengthInRelativeToRulePeriodUnit = $difference->format('%y');
          break;

        case PeriodUnit::MONTHS:
          $membershipLengthInRelativeToRulePeriodUnit = ($difference->y * 12) + $difference->m;
          break;

        case PeriodUnit::DAYS:
          $membershipLengthInRelativeToRulePeriodUnit = $difference->format('%a');
          break;
      }

      if ($membershipLengthInRelativeToRulePeriodUnit < $membershipUpgradeRule['period_length']) {
        continue;
      }

      if (!empty($membershipUpgradeRule['filter_group'])) {
        $isInFilterGroup = $this->checkIfMemberInFilterGroup((int) $membership['contact_id'], (int) $membershipUpgradeRule['filter_group']);
        if (!$isInFilterGroup) {
          continue;
        }
      }

      return $membershipUpgradeRule['to_membership_type_id'];
    }

    return NULL;
  }

  /**
   * Checks if the member is part of
   * the filter group, whither its
   * smart or normal group.
   *
   * @param int $contactId
   * @param int $filterGroupId
   *
   * @return bool
   */
  private function checkIfMemberInFilterGroup(int $contactId, int $filterGroupId): bool {
    return isset($this->filterGroupMembers[$filterGroupId][$contactId]);
  }

}
