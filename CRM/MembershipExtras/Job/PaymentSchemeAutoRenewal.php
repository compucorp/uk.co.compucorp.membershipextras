<?php

class CRM_MembershipExtras_Job_PaymentSchemeAutoRenewal {

  /**
   * Starts the scheduled job for renewing payment
   * plans that are linked to a payment scheme.
   *
   * @param array|null $contactIds Optional array of contact IDs to filter by
   * @return True
   *
   * @throws \CRM_Core_Exception
   */
  public function run($contactIds = NULL) {
    $exceptions = [];

    try {
      $paymentSchemeRenewal = new CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentSchemePlan($contactIds);
      $paymentSchemeRenewal->run();
    }
    catch (CRM_Core_Exception $e) {
      $exceptions[] = $e->getMessage();
    }

    if (count($exceptions)) {
      throw new CRM_Core_Exception("Errors found on payment scheme auto-renewals: " . implode("\n", $exceptions));
    }

    return TRUE;
  }

}
