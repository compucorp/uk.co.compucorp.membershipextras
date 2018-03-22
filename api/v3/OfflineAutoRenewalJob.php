<?php

function civicrm_api3_offline_auto_renewal_job_run($params) {
  $OfflineAutoRenewalJob = new CRM_MembershipExtras_Job_OfflineAutoRenewal();

  return civicrm_api3_create_success(
    $OfflineAutoRenewalJob->run(),
    $params
  );
}
