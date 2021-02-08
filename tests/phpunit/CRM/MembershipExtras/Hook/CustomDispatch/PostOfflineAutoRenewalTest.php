<?php
use Civi\Test\HookInterface;

/**
 * Class CRM_MembershipExtras_Hook_CustomDispatch_PostOfflineAutoRenewalTest.
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_CustomDispatch_PostOfflineAutoRenewalTest extends BaseHeadlessTest implements HookInterface {

  /**
   * An integer, represents the ID of a membership.
   *
   * @var int
   */
  private $membershipId;

  /**
   * An integer representing the ID of a recurring contribution.
   *
   * @var int
   */
  private $recurringContributionId;

  /**
   * An integer, represents the ID of a recurring conribution.
   *
   * @var int
   */
  private $previousRecurringId;

  public function testHookDispatchInputPassThroughToImplementations() {
    $this->membershipId = 1;
    $this->recurringContributionId = 2;
    $this->previousRecurringId = 3;

    $dispatcher = new CRM_MembershipExtras_Hook_CustomDispatch_PostOfflineAutoRenewal(
      $this->membershipId,
      $this->recurringContributionId,
      $this->previousRecurringId
    );
    $dispatcher->dispatch();
  }

  /**
   * Implements calculateContributionReceiveDate hook for testing.
   *
   * @param int $membershipId
   * @param int $recurringContributionId
   * @param int $previousRecurrId
   */
  public function hook_membershipextras_postOfflineAutoRenewal($membershipId, $recurringContributionId, $previousRecurrId) {
    $this->assertEquals($this->membershipId, $membershipId);
    $this->assertEquals($this->recurringContributionId, $recurringContributionId);
    $this->assertEquals($this->previousRecurringId, $previousRecurrId);
  }

}
