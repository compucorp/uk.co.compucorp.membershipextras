<?php

/**
 * Updates the payment plan next contribution date/
 */
class CRM_MembershipExtras_WebformAPI_PaymentPlanNextContributionDateUpdater {

  public static function update($contributionRecurId) {
    $nextContributionDateService = new CRM_MembershipExtras_Service_PaymentPlanNextContributionDate($contributionRecurId);
    $nextContributionDateService->calculateAndUpdate();
  }

}
