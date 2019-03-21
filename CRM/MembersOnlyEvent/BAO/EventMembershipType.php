<?php

class CRM_MembersOnlyEvent_BAO_EventMembershipType extends CRM_MembersOnlyEvent_DAO_EventMembershipType {

  /**
   * Stores the allowed membership types for specific
   * members-only event
   *
   * @param int $membersOnlyEventID
   * @param array $allowedMembershipTypeIDs
   */
  public static function updateAllowedMembershipTypes($membersOnlyEventID, $allowedMembershipTypeIDs) {
    $transaction = new CRM_Core_Transaction();

    $removeResponse = self::removeAllowedMembershipTypes($membersOnlyEventID);
    $createResponse = self::createAllowedMembershipTypes($membersOnlyEventID, $allowedMembershipTypeIDs);

    if ($removeResponse === FALSE || $createResponse === FALSE) {
      $transaction->rollback();
    } else {
      $transaction->commit();
    }
  }

  /**
   * Removes all allowed membership types for
   * the provided members-only event.
   *
   * @param int $membersOnlyEventID
   */
  private static function removeAllowedMembershipTypes($membersOnlyEventID) {
    $membership_type = new self();
    $membership_type->members_only_event_id = $membersOnlyEventID;
    $membership_type->delete();
  }

  /**
   * Sets the allowed membership types
   * for the provided members-only event.
   *
   * @param int $membersOnlyEventID
   * @param array $allowedMembershipTypeIDs
   *
   * @return boolean
   *   True if the creation of all allowed membership
   *   types succeed or False otherwise.
   */
  private static function createAllowedMembershipTypes($membersOnlyEventID, $allowedMembershipTypeIDs) {
    $createdRecordsCount = 0;
    foreach ($allowedMembershipTypeIDs as $allowedMembershipTypeID) {
      $eventMembershipType = new self();
      $eventMembershipType->members_only_event_id = $membersOnlyEventID;
      $eventMembershipType->membership_type_id = $allowedMembershipTypeID;
      $eventMembershipType->save();
      $createdRecordsCount++;
    }

    return $createdRecordsCount == count($allowedMembershipTypeIDs);
  }

  /**
   * Gets the allowed membership types for specific
   * members-only event.
   *
   * @param int $membersOnlyEventID
   *
   * @return array
   *   The IDs of allowed membership types
   */
  public static function getAllowedMembershipTypesIDs($membersOnlyEventID) {
    $eventMembershipType = new self();
    $eventMembershipType->members_only_event_id = $membersOnlyEventID;
    $eventMembershipType->find();

    $allowedMembershipTypeIDs = array();
    while ($eventMembershipType->fetch()) {
      $allowedMembershipTypeIDs[] = $eventMembershipType->membership_type_id;
    }

    return $allowedMembershipTypeIDs;
  }
}
