<?php

class CRM_MembershipExtras_Job_OfflineAutoRenewal {

  /**
   * Starts the scheduled job for renewing offline
   * multiple instalments auto-renewal memberships.
   *
   * @param array|null $contactIds Optional array of contact IDs to filter by
   * @return True
   *
   * @throws \CRM_Core_Exception
   */
  public function runMultiple($contactIds = NULL) {
    $exceptions = [];

    try {
      $multipleInstalmentRenewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_MultipleInstalmentPlan($contactIds);
      $multipleInstalmentRenewal->run();
    }
    catch (CRM_Core_Exception $e) {
      $exceptions[] = $e->getMessage();
    }

    if (count($exceptions)) {
      throw new CRM_Core_Exception("Errors found on auto-renewals: " . implode("\n", $exceptions));
    }

    return TRUE;
  }

  /**
   * Starts the scheduled job for renewing offline
   * single instalments auto-renewal memberships.
   *
   * @param array|null $contactIds Optional array of contact IDs to filter by
   * @return True
   *
   * @throws \CRM_Core_Exception
   */
  public function runSingle($contactIds = NULL) {
    $exceptions = [];

    try {
      $singleInstalmentRenewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstalmentPlan($contactIds);
      $singleInstalmentRenewal->run();
    }
    catch (CRM_Core_Exception $e) {
      $exceptions[] = $e->getMessage();
    }

    if (count($exceptions)) {
      throw new CRM_Core_Exception("Errors found on auto-renewals: " . implode("\n", $exceptions));
    }

    return TRUE;
  }

}
