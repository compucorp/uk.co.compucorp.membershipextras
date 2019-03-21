<?php

class CRM_MembersOnlyEvent_BAO_MembersOnlyEvent extends CRM_MembersOnlyEvent_DAO_MembersOnlyEvent {

  /**
   * Contribution page link type for 'purchase membership button'.
   *
   * @const int
   */
  const LINK_TYPE_CONTRIBUTION_PAGE = 0;

  /**
   * URL link type for 'purchase membership button'.
   *
   * @const int
   */
  const LINK_TYPE_URL = 1;

  /**
   * Creates a new Members-Only Event record
   * based on array-data
   *
   * @param array $params
   *
   * @return CRM_MembersOnlyEvent_BAO_MembersOnlyEvent
   */
  public static function create($params) {
    $entityName = 'MembersOnlyEvent';
    $hookOperation = empty($params['id']) ? 'create' : 'edit';

    CRM_Utils_Hook::pre($hookOperation, $entityName, CRM_Utils_Array::value('id', $params), $params);

    $membersOnlyEvent = new self();
    $membersOnlyEvent->copyValues($params);
    $membersOnlyEvent->save();

    CRM_Utils_Hook::post($hookOperation, $entityName, $membersOnlyEvent->id, $membersOnlyEvent);

    return $membersOnlyEvent;
  }

  /**
   * Gets the members-Only Event data
   * given the event ID, or return false if
   * the event is not a members-only event.
   *
   * @param int $eventID
   *
   * @return CRM_MembersOnlyEvent_DAO_MembersOnlyEvent|FALSE
   */
  public static function getMembersOnlyEvent($eventID) {
    $membersOnlyEvent = new self();
    $membersOnlyEvent->event_id = $eventID;
    $membersOnlyEvent->find(TRUE);

    if ($membersOnlyEvent->N) {
      return $membersOnlyEvent;
    }

    return FALSE;
  }
}
