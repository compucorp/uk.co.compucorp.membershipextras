<?php

use Civi\Core\Event\GenericHookEvent;

/**
 * Class CRM_MembershipExtras_Hook_CustomDispatch_CalculateMembershipMinimumFee
 */
class CRM_MembershipExtras_Hook_CustomDispatch_CalculateMembershipMinimumFee {

  const NAME = 'me.membership.calculate_minimum_fee';

  /**
   * Membership Types.
   *
   * @var array
   */
  private array $membershipTypes;

  /**
   * Contact ID.
   *
   * @var int
   */
  private $contactID;

  /**
   * CRM_MembershipExtras_Hook_CustomDispatch_CalculateMembershipMinimumFee constructor.
   *
   * @param array $membershipTypes
   * @param int $contactID
   */
  public function __construct(&$membershipTypes, $contactID) {
    $this->membershipTypes =& $membershipTypes;
    $this->contactID = $contactID;
  }

  /**
   * Dispatches event.
   */
  public function dispatch() {
    $event = GenericHookEvent::create(['contactID' => $this->contactID, 'membershipTypes' => &$this->membershipTypes]);
    Civi::dispatcher()->dispatch(self::NAME, $event);
  }

}
