<?php

use CRM_MembershipExtras_ExtensionUtil as ExtensionUti;

class CRM_MembershipExtras_Hook_Config_APIWrapper_PaymentAPI {

  /**
   * Callback precedes Payment.Create API call.
   *
   * If the Payment.create API is getting called, then we set `paymentApiCalled`
   * to True, which we use later in Membership Pre Edit hook to prevent extending
   * the membership end date.
   *
   * See: https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_apiWrappers/#migrating-away-from-this-hook
   */
  public static function preApiCall($event) {
    $apiRequestSig = $event->getApiRequestSig();
    if (!in_array($apiRequestSig, ['3.payment.create', '4.payment.create'])) {
      return;
    }

    Civi::$statics[ExtensionUti::LONG_NAME]['paymentApiCalled'] = TRUE;
  }

}
