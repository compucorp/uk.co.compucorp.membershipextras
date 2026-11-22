<?php

/**
 * Keeps payment plan subscription line items when CiviCRM core cleans up
 * a membership's line items.
 *
 * A subscription line (membershipextras_subscription_line) points to a
 * civicrm_line_item row that belongs to the membership and has no
 * contribution (contribution_id IS NULL).
 *
 * Since CiviCRM 6.20 the APIv3 Membership.create call deletes, on every
 * update of an existing membership, all the line items of that membership
 * that have no contribution (api/v3/Membership.php: "do cleanup line items
 * if membership edit the Membership type"). The foreign key from
 * membershipextras_subscription_line to civicrm_line_item is ON DELETE
 * CASCADE, so the subscription line goes with it and the payment plan can
 * no longer be renewed or managed.
 *
 * Earlier core versions only did that cleanup for memberships without any
 * contribution, so on them this listener never finds anything to protect.
 */
class CRM_MembershipExtras_Hook_Config_APIWrapper_SubscriptionLineItemProtector {

  /**
   * Callback that precedes the APIv4 LineItem.delete call.
   *
   * Only acts on the delete core issues for its membership cleanup: the one
   * that filters by "contribution_id IS NULL" and by the membership entity,
   * with no explicit line item ID. Deletes that name the line items to remove
   * are left alone.
   *
   * @param \Civi\API\Event\PrepareEvent $event
   */
  public static function preApiCall($event) {
    if ($event->getApiRequestSig() !== '4.lineitem.delete') {
      return;
    }

    $apiRequest = $event->getApiRequest();
    // getWhere()/addWhere() are magic methods on APIv4 actions, so check the type.
    if (!$apiRequest instanceof \Civi\Api4\Generic\AbstractQueryAction) {
      return;
    }

    $membershipIds = self::getCleanedUpMembershipIds($apiRequest->getWhere());
    if (empty($membershipIds)) {
      return;
    }

    $protectedLineItemIds = self::getSubscriptionLineItemIds($membershipIds);
    if (empty($protectedLineItemIds)) {
      return;
    }

    $apiRequest->addWhere('id', 'NOT IN', $protectedLineItemIds);
  }

  /**
   * Gets the memberships targeted by a "line items without contribution"
   * cleanup, or an empty array if the delete is anything else.
   *
   * @param array $where
   *
   * @return int[]
   */
  private static function getCleanedUpMembershipIds($where) {
    $hasNoContributionClause = FALSE;
    $isMembershipEntity = FALSE;
    $membershipIds = [];

    foreach ($where as $clause) {
      if (!is_array($clause) || !isset($clause[0]) || !is_string($clause[0])) {
        // Nested AND/OR/NOT clauses are not what core sends: leave untouched.
        return [];
      }

      $field = $clause[0];
      $operator = strtoupper((string) ($clause[1] ?? ''));
      $value = $clause[2] ?? NULL;

      if ($field === 'id') {
        return [];
      }
      if ($field === 'contribution_id' && $operator === 'IS NULL') {
        $hasNoContributionClause = TRUE;
      }
      if ($field === 'entity_table' && $operator === '=' && $value === 'civicrm_membership') {
        $isMembershipEntity = TRUE;
      }
      if ($field === 'entity_id' && in_array($operator, ['=', 'IN'], TRUE)) {
        $membershipIds = array_filter(array_map('intval', (array) $value));
      }
    }

    if (!$hasNoContributionClause || !$isMembershipEntity) {
      return [];
    }

    return $membershipIds;
  }

  /**
   * Gets the IDs of the line items, belonging to the given memberships,
   * that are used by a payment plan subscription line.
   *
   * @param int[] $membershipIds
   *
   * @return int[]
   */
  private static function getSubscriptionLineItemIds($membershipIds) {
    $query = '
      SELECT msl.line_item_id
      FROM membershipextras_subscription_line msl
      INNER JOIN civicrm_line_item cli ON cli.id = msl.line_item_id
      WHERE cli.entity_table = "civicrm_membership"
      AND cli.entity_id IN (' . implode(',', $membershipIds) . ')
    ';
    $result = CRM_Core_DAO::executeQuery($query);

    $lineItemIds = [];
    while ($result->fetch()) {
      $lineItemIds[] = (int) $result->line_item_id;
    }

    return $lineItemIds;
  }

}
