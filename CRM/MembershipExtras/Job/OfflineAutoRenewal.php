<?php

class CRM_MembershipExtras_Job_OfflineAutoRenewal {

  /**
   * Starts the scheduled job for renewing offline
   * auto-renewal memberships.
   *
   * @return True
   *
   * @throws \CRM_Core_Exception
   */
  public function run() {
    $exceptions = [];

    try {
      $multipleInstallmentRenewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstallmentPlan();
      $multipleInstallmentRenewal->run();
    } catch (Exception $e) {
      $exceptions[] = $e->getMessage();
    }

    try {
      $singleInstallmentRenewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlan();
      $singleInstallmentRenewal->run();
    } catch (Exception $e) {
      $exceptions[] = $e->getMessage();
    }

    if (count($exceptions)) {
      throw new CRM_Core_Exception("Errors found on auto-renewals: " . implode("\n", $exceptions));
    }

    return TRUE;
  }

}
