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
 * These entry points set the mark, and each clears it when it is done, so it
 * never outlives the order it belongs to:
 * - the civi.order.complete event (CiviCRM 6.x), around core's own handler,
 *   which covers every route, e.g. a contribution page's Pay Online;
 * - the Contribution.completetransaction and Payment.create APIs, which work
 *   on every CiviCRM version (e.g. 5.x, where core updates memberships
 *   without the event).
 *
 * The marks are kept as a stack, because the entry points nest: an API call
 * completes its order through the event on 6.x. The innermost mark is the
 * order being completed.
 *
 * The mark is what tells the pre-hook which contribution is being paid. The
 * pre-hook's other source, the last contribution edited in this PHP process,
 * can still hold the previous payment's contribution when several payments
 * are recorded in one run, e.g. a GoCardless webhook queue run.
 */
class CRM_MembershipExtras_Hook_Config_OrderCompletion {

  /**
   * Static key holding the stack of contribution IDs being completed.
   */
  private const KEY = 'completingOrderContributionID';

  /**
   * API calls that complete an order, with the parameter holding the
   * contribution ID.
   */
  private const API_CONTRIBUTION_ID_PARAMS = [
    '3.contribution.completetransaction' => 'id',
    '3.payment.create' => 'contribution_id',
  ];

  /**
   * Gets the contribution whose order is being completed, if any.
   *
   * @return int|null
   */
  public static function getCompletingContributionID() {
    $marks = Civi::$statics[ExtensionUtil::LONG_NAME][self::KEY] ?? [];
    $contributionID = empty($marks) ? NULL : end($marks);

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
    $contributionIDParam = self::API_CONTRIBUTION_ID_PARAMS[$event->getApiRequestSig()] ?? NULL;
    if ($contributionIDParam === NULL) {
      return;
    }

    $request = $event->getApiRequest();
    $contributionID = $request['params'][$contributionIDParam] ?? NULL;
    self::mark(empty($contributionID) ? 0 : (int) $contributionID);
  }

  /**
   * Clears the mark after Contribution.completetransaction or Payment.create
   * (on success or failure).
   *
   * @param \Civi\API\Event\RespondEvent|\Civi\API\Event\ExceptionEvent $event
   */
  public static function onApiDone($event) {
    if (isset(self::API_CONTRIBUTION_ID_PARAMS[$event->getApiRequestSig()])) {
      self::clear();
    }
  }

  /**
   * Pushes a contribution being completed. 0 marks an API call without a
   * contribution ID, so its clear still pairs with it.
   *
   * @param int $contributionID
   */
  private static function mark(int $contributionID) {
    Civi::$statics[ExtensionUtil::LONG_NAME][self::KEY][] = $contributionID;
  }

  private static function clear() {
    if (!empty(Civi::$statics[ExtensionUtil::LONG_NAME][self::KEY])) {
      array_pop(Civi::$statics[ExtensionUtil::LONG_NAME][self::KEY]);
    }
  }

}
