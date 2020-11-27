<?php

class CRM_MembershipExtras_BAO_AutoMembershipUpgradeRule extends CRM_MembershipExtras_DAO_AutoMembershipUpgradeRule {

  public static function create($params) {
    $className = 'CRM_MembershipExtras_DAO_AutoMembershipUpgradeRule';
    $entityName = 'AutoMembershipUpgradeRule';

    $hook = empty($params['id']) ? 'create' : 'edit';
    CRM_Utils_Hook::pre($hook, $entityName, CRM_Utils_Array::value('id', $params), $params);

    $params['name'] = strtolower(str_replace(' ', '_', $params['label']));
    if ($hook == 'create') {
      $params['weight'] = self::calculateUpgradeRuleWeight();
    }

    $newUpgradeRule = new $className();
    $newUpgradeRule->copyValues($params);
    $newUpgradeRule->save();

    CRM_Utils_Hook::post($hook, $entityName, $newUpgradeRule->id, $newUpgradeRule);

    return $newUpgradeRule;
  }

  /**
   * Calculates the new upgrade
   * rule weight based on the maximum
   * existing weight.
   *
   * @return int
   */
  private static function calculateUpgradeRuleWeight() {
    $upgradeRule = new self();
    $upgradeRule->orderBy('weight DESC');
    $upgradeRule->limit(1);
    $upgradeRule->find(TRUE);

    if (!empty($upgradeRule->weight)) {
      return $upgradeRule->weight + 1;
    }

    return 1;
  }

  public static function getById($id) {
    $upgradeRule = new self();
    $upgradeRule->id = $id;
    $upgradeRule->find(TRUE);

    return $upgradeRule;
  }

  public static function deleteById($id) {
    $obj = new self();
    $obj->id = $id;
    $obj->delete();
  }

}
