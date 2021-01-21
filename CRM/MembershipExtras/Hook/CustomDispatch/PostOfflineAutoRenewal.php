<?php

/**
 * Class CRM_MembershipExtras_Hook_CustomDispatch_PostOfflineAutoRenewal
 */
class CRM_MembershipExtras_Hook_CustomDispatch_PostOfflineAutoRenewal {

  /**
   * Membership ID.
   *
   * @var int
   */
  private $membershipID;

  /**
   * New recurring contribution ID.
   *
   * @var int
   */
  private $recurringContributionID;

  /**
   * Previous recurring contribution.
   *
   * @var int
   */
  private $previousRecurringContributionID;

  /**
   * CRM_MembershipExtras_Hook_CustomDispatch_PostOfflineAutoRenewal constructor.
   *
   * @param int $membershipID
   * @param int $recurContributionId
   * @param int $previousRecurContributionId
   */
  public function __construct($membershipID, &$recurContributionId, &$previousRecurContributionId) {
    $this->membershipID = $membershipID ?: CRM_Utils_Hook::$_nullObject;
    $this->recurringContributionID =& $recurContributionId;
    $this->previousRecurringContributionID =& $previousRecurContributionId;
  }

  /**
   * Dispatches hook.
   */
  public function dispatch() {
    $nullObject = CRM_Utils_Hook::$_nullObject;
    CRM_Utils_Hook::singleton()->invoke(
      ['membershipId', 'recurContributionId', 'previousRecurContributionId'],
      $this->membershipID,
      $this->recurringContributionID,
      $this->previousRecurringContributionID,
      $nullObject, $nullObject, $nullObject,
      'membershipextras_postOfflineAutoRenewal'
    );
  }

}
