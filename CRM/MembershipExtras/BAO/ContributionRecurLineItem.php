<?php
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_BAO_ContributionRecurLineItem extends CRM_MembershipExtras_DAO_ContributionRecurLineItem {

  /**
   * Create a new ContributionRecurLineItem based on array-data
   *
   * @param array $params key-value pairs
   *
   * @return CRM_MembershipExtras_DAO_ContributionRecurLineItem|NULL
   */
  public static function create($params) {
    $className = 'CRM_MembershipExtras_DAO_ContributionRecurLineItem';
    $entityName = 'ContributionRecurLineItem';
    $hook = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hook, $entityName, $params['id'] ?? NULL, $params);
    $instance = new $className();
    $instance->copyValues($params);
    $instance->save();
    CRM_Utils_Hook::post($hook, $entityName, $instance->id, $instance);

    return $instance;
  }

  /**
   * Gets the payment plan period end date,
   * which is the maximum end date among all
   * membership recurring line items.
   *
   * @param int $recurContributionId
   * @return string
   */
  public static function getPeriodEndDate($recurContributionId) {
    $query = "
      SELECT MAX(m.end_date) FROM civicrm_membership m
      INNER JOIN civicrm_line_item li ON m.id = li.entity_id and li.entity_table = 'civicrm_membership'
      INNER JOIN membershipextras_subscription_line msl ON li.id = msl.line_item_id
      WHERE msl.contribution_recur_id = %1
        AND msl.is_removed = FALSE
        AND msl.auto_renew = 1
        AND msl.end_date IS NULL;
    ";

    return CRM_Core_DAO::singleValueQuery($query, [
      1 => [$recurContributionId, 'Integer'],
    ]);
  }

}
