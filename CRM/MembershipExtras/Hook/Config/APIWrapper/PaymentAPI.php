<?php

use CRM_MembershipExtras_ExtensionUtil as ExtensionUti;

class CRM_MembershipExtras_Hook_Config_APIWrapper_PaymentAPI {

  /**
   * Callback precedes Payment.Create API call.
   *
   * If the Payment.create API is getting called, then we set `paymentApiCalled`
   * to True and record the contribution being paid, which the Membership Pre
   * Edit hook uses to decide whether to prevent extending the membership end
   * date.
   *
   * See: https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_apiWrappers/#migrating-away-from-this-hook
   */
  public static function preApiCall($event) {
    if (!self::isPaymentCreate($event)) {
      return;
    }

    Civi::$statics[ExtensionUti::LONG_NAME]['paymentApiCalled'] = TRUE;
    Civi::$statics[ExtensionUti::LONG_NAME]['resetContributionID'] = TRUE;
    CRM_MembershipExtras_Hook_Config_APIWrapper_PaymentContext::start($event, 'contribution_id');
  }

  /**
   * Callback follows Payment.Create API call, or its failure.
   */
  public static function postApiCall($event) {
    if (self::isPaymentCreate($event)) {
      CRM_MembershipExtras_Hook_Config_APIWrapper_PaymentContext::end();
    }
  }

  private static function isPaymentCreate($event) {
    return in_array($event->getApiRequestSig(), ['3.payment.create', '4.payment.create']);
  }

}
