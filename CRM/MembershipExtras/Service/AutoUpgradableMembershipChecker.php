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

  public function __construct() {
    $this->membershipUpgradeRules = $this->getMembershipUpgradeRulesSortedByWeightAsc();
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
        $isInFilterGroup = $this->checkIfMemberInFilterGroup($membership['contact_id'], $membershipUpgradeRule['filter_group']);
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
  private function checkIfMemberInFilterGroup($contactId, $filterGroupId) {
    // smart group contacts are stored in civicrm_group_contact_cache table
    // which has no API, so we are forced to use an SQL query here.
    $query = "SELECT id FROM civicrm_group_contact WHERE contact_id = %1 AND group_id = %2 UNION 
                  SELECT id FROM civicrm_group_contact_cache WHERE contact_id = %1 AND group_id = %2";
    $result = CRM_Core_DAO::executeQuery($query, [
      1 => [$contactId, 'Integer'],
      2 => [$filterGroupId, 'Integer'],
    ]);

    if (!$result->fetch()) {
      return FALSE;
    }

    return TRUE;
  }

}
