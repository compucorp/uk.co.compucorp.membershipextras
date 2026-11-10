<?php

use CRM_MembershipExtras_ExtensionUtil as ExtensionUti;

/**
 * Tracks the contribution being paid by the Payment.create or
 * Contribution.completetransaction API call currently running.
 */
class CRM_MembershipExtras_Hook_Config_APIWrapper_PaymentContext {

  const STATICS_KEY = 'paymentApiContributionIDs';

  public static function start($event, $contributionIdParam) {
    $apiRequest = $event->getApiRequest();
    $params = is_array($apiRequest) ? ($apiRequest['params'] ?? []) : [];
    Civi::$statics[ExtensionUti::LONG_NAME][self::STATICS_KEY][] = (int) ($params[$contributionIdParam] ?? 0);
  }

  public static function end() {
    if (!empty(Civi::$statics[ExtensionUti::LONG_NAME][self::STATICS_KEY])) {
      array_pop(Civi::$statics[ExtensionUti::LONG_NAME][self::STATICS_KEY]);
    }
  }

  /**
   * @return int|null
   *   The contribution being paid, or NULL outside these API calls.
   */
  public static function getContributionID() {
    $contributionIDs = Civi::$statics[ExtensionUti::LONG_NAME][self::STATICS_KEY] ?? [];
    $contributionID = end($contributionIDs);

    return $contributionID ?: NULL;
  }

}
