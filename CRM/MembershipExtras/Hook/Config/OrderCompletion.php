<?php

use CRM_MembershipExtras_ExtensionUtil as ExtensionUtil;

/**
 * Marks which contribution's order is being completed, so the membership
 * pre-hook knows a membership edit comes from order completion.
 *
 * When an order completes, core renews each related membership by a term
 * unless the membership is still Pending. Membership Extras moves a payment
 * plan membership out of Pending at signup, so without this core adds a term
 * on the first payment and on every later instalment
 * (CRM_MembershipExtras_Hook_Pre_MembershipEdit decides whether to allow it).
 *
 * Two entry points set the mark, and each clears it when it is done, so it
 * never outlives the order it belongs to:
 * - the civi.order.complete event (CiviCRM 6.x), around core's own handler,
 *   which covers every route, e.g. a contribution page's Pay Online;
 * - the Contribution.completetransaction API, which works on every CiviCRM
 *   version (e.g. 5.x, where core updates memberships without the event).
 */
class CRM_MembershipExtras_Hook_Config_OrderCompletion {

  /**
   * Static key holding the contribution ID being completed.
   */
  private const KEY = 'completingOrderContributionID';

  /**
   * Gets the contribution whose order is being completed, if any.
   *
   * @return int|null
   */
  public static function getCompletingContributionID() {
    $contributionID = Civi::$statics[ExtensionUtil::LONG_NAME][self::KEY] ?? NULL;

    return empty($contributionID) ? NULL : (int) $contributionID;
  }

  /**
   * Runs before core's civi.order.complete handler.
   *
   * @param \Civi\Order\Event\OrderCompleteEvent $event
   */
  public static function onOrderCompleteStart($event) {
    if (!empty($event->contributionID)) {
      self::mark((int) $event->contributionID);
    }
  }

  /**
   * Runs after core's civi.order.complete handler.
   *
   * @param \Civi\Order\Event\OrderCompleteEvent $event
   */
  public static function onOrderCompleteEnd($event) {
    self::clear();
  }

  /**
   * Runs before an API call: marks a Contribution.completetransaction.
   *
   * @param \Civi\API\Event\PrepareEvent $event
   */
  public static function onApiPrepare($event) {
    if ($event->getApiRequestSig() !== '3.contribution.completetransaction') {
      return;
    }

    $request = $event->getApiRequest();
    $contributionID = $request['params']['id'] ?? NULL;
    if (!empty($contributionID)) {
      self::mark((int) $contributionID);
    }
  }

  /**
   * Runs after an API call, or when it fails: clears the mark set for a
   * Contribution.completetransaction.
   *
   * @param \Civi\API\Event\Event $event
   */
  public static function onApiDone($event) {
    if ($event->getApiRequestSig() === '3.contribution.completetransaction') {
      self::clear();
    }
  }

  /**
   * @param int $contributionID
   */
  private static function mark(int $contributionID) {
    Civi::$statics[ExtensionUtil::LONG_NAME][self::KEY] = $contributionID;
  }

  private static function clear() {
    unset(Civi::$statics[ExtensionUtil::LONG_NAME][self::KEY]);
  }

}
