<?php

use CRM_MembershipExtras_ExtensionUtil as ExtensionUti;

class CRM_MembershipExtras_Hook_Config_APIWrapper_ContributionCompleteTransactionAPI {

  /**
   * Callback precedes Contribution.completetransaction API call.
   *
   * Sets `resetContributionID` flag so that the static $contributionID
   * in membershipextras_civicrm_pre() is reset to NULL before each
   * completeTransaction's Membership edit hook fires. This prevents
   * stale contribution IDs from a previous completeTransaction call
   * (e.g. multi-event GoCardless webhook) from incorrectly triggering
   * prevention of membership date extension. The contribution being
   * completed is recorded instead, for the Membership edit hook to decide
   * from.
   */
  public static function preApiCall($event) {
    if (!self::isCompleteTransaction($event)) {
      return;
    }

    Civi::$statics[ExtensionUti::LONG_NAME]['resetContributionID'] = TRUE;
    CRM_MembershipExtras_Hook_Config_APIWrapper_PaymentContext::start($event, 'id');
  }

  /**
   * Callback follows Contribution.completetransaction API call, or its failure.
   */
  public static function postApiCall($event) {
    if (self::isCompleteTransaction($event)) {
      CRM_MembershipExtras_Hook_Config_APIWrapper_PaymentContext::end();
    }
  }

  private static function isCompleteTransaction($event) {
    return $event->getApiRequestSig() === '3.contribution.completetransaction';
  }

}
