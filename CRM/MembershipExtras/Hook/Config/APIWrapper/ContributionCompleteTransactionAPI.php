<?php

use CRM_MembershipExtras_ExtensionUtil as ExtensionUti;

class CRM_MembershipExtras_Hook_Config_APIWrapper_ContributionCompleteTransactionAPI {

  /**
   * Callback precedes Contribution.completetransaction API call.
   *
   * Sets `completeTransactionCalled` flag so that the Membership Pre Edit
   * hook can detect we're in a payment completion context (e.g. GoCardless
   * webhook) and prevent per-installment membership date extensions.
   *
   * This follows the same pattern as PaymentAPI wrapper.
   */
  public static function preApiCall($event) {
    $apiRequestSig = $event->getApiRequestSig();
    if ($apiRequestSig !== '3.contribution.completetransaction') {
      return;
    }

    Civi::$statics[ExtensionUti::LONG_NAME]['completeTransactionCalled'] = TRUE;
  }

}
