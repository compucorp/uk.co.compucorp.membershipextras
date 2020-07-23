<?php

/**
 * Membership offline auto-renewal scheduled job API
 *
 * @param $params
 * @return array
 */
function civicrm_api3_offline_auto_renewal_job_run($params) {
  $offlineAutoRenewalJob = new CRM_MembershipExtras_Job_OfflineAutoRenewal();

  return civicrm_api3_create_success(
    $offlineAutoRenewalJob->run(),
    $params
  );
}
