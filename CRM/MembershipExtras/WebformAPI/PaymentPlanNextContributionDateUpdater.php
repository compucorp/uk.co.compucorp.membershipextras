<?php

/**
 * Updates the payment plan next contribution date/
 */
class CRM_MembershipExtras_WebformAPI_PaymentPlanNextContributionDateUpdater {

  public static function update($contributionRecurId) {
    // For offline payment plans memberships, we only offer create new ones through webforms, manual renewal is not supported.
    $operation = 'Creation';
    $nextContributionDateService = new CRM_MembershipExtras_Service_PaymentPlanNextContributionDate($contributionRecurId, $operation);
    $nextContributionDateService->calculateAndUpdate();
  }

}
