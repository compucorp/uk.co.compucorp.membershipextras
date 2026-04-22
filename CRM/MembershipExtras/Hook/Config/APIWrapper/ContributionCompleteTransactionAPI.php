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
   * prevention of membership date extension.
   */
  public static function preApiCall($event) {
    $apiRequestSig = $event->getApiRequestSig();
    if ($apiRequestSig !== '3.contribution.completetransaction') {
      return;
    }

    Civi::$statics[ExtensionUti::LONG_NAME]['resetContributionID'] = TRUE;
  }

}
