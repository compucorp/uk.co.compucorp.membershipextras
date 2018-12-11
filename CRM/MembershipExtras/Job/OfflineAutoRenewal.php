<?php

class CRM_MembershipExtras_Job_OfflineAutoRenewal {

  /**
   * Starts the scheduled job for renewing offline
   * auto-renewal memberships.
   *
   * @return True
   *
   * @throws \Exception
   */
  public function run() {
    $multipleInstallmentRenewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstallmentPlan();
    $multipleInstallmentRenewal->run();

    $singleInstallmentRenewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstallmentPlan();
    $singleInstallmentRenewal->run();

    return TRUE;
  }

}
