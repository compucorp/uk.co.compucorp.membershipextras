<?php

/**
 * Post processes membership payments after cretion or update.
 */
class CRM_MembershipExtras_Hook_Post_MembershipPayment {

  /**
   * Operation being done on the line item
   *
   * @var string
   */
  private $operation;

  /**
   * ID of the record.
   *
   * @var int
   */
  private $id;

  /**
   * Reference to BAO.
   *
   * @var \CRM_Member_DAO_MembershipPayment
   */
  private $membershipPayment;

  public function __construct($operation, $objectId, CRM_Member_DAO_MembershipPayment $objectRef) {
    $this->operation = $operation;
    $this->id = $objectId;
    $this->membershipPayment = $objectRef;
  }

  /**
   * Post-processes a membership payment on creation and update.
   */
  public function postProcess() {
    if ($this->operation == 'create') {
      $this->fixRecurringLineItemMembershipReferences();
    }
  }

  /**
   * Ugh... There is a bug/feature of CiviCRM where line items for memberships
   * are created with the first membership in a price set where several
   * memberships are used, and then the real membership is set via direct SQL
   * query... So we need to calculate real membership ID for recurring
   * line items, otherwise they will all reference one membership.
   *
   * Bug seen as late as v5.4 of CiviCRM.
   *
   * See: https://github.com/civicrm/civicrm-core/blob/5.4.0/CRM/Member/BAO/MembershipPayment.php#L72-L95
   */
  private function fixRecurringLineItemMembershipReferences() {
    $lineItem = $this->getRelatedRecurringLineItem();
    $entityTable = CRM_Utils_Array::value('entity_table', $lineItem, '');
    $entityID = CRM_Utils_Array::value('entity_id', $lineItem, 0);

    if ($entityID && $entityTable == 'civicrm_membership' && $entityID != $this->membershipPayment->membership_id) {
      $sql = "
        UPDATE civicrm_line_item 
        SET entity_table = 'civicrm_membership', entity_id = %1
        WHERE id = %2
      ";
      CRM_Core_DAO::executeQuery($sql, [
        1 => [$this->membershipPayment->membership_id, 'Integer'],
        2 => [$lineItem['id'], 'Integer'],
      ]);
    }
  }

  /**
   * Obtains recurring line item that matches the membership type of the current
   * payment, by looking at the membership type in the line item's price field
   * value.
   *
   * @return array
   */
  private function getRelatedRecurringLineItem() {
    $membershipTypeID = civicrm_api3('Membership', 'getvalue', [
      'id' => $this->membershipPayment->membership_id,
      'return' => 'membership_type_id',
    ]);

    $recurringContributionID = civicrm_api3('Contribution', 'getvalue', [
      'id' => $this->membershipPayment->contribution_id,
      'return' => 'contribution_recur_id',
    ]);

    $recurringLineItems = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1]
      ],
    ]);

    if ($recurringLineItems['count'] > 0) {
      foreach ($recurringLineItems['values'] as $lineItem) {
        $priceFieldValueID = CRM_Utils_Array::value('price_field_value_id', $lineItem['api.LineItem.getsingle'], 0);
        if (!$priceFieldValueID) {
          continue;
        }

        $priceFieldValueData = civicrm_api3('PriceFieldValue', 'getsingle', [
          'id' => $lineItem['api.LineItem.getsingle']['price_field_value_id'],
        ]);

        if (CRM_Utils_Array::value('membership_type_id', $priceFieldValueData, 0) == $membershipTypeID) {
          return $lineItem['api.LineItem.getsingle'];
        }
      }
    }

    return [];
  }

}
