<?php

/**
 * Membership offline auto-renewal scheduled job API
 *
 * @param $params
 * @return array
 */
function civicrm_api3_offline_auto_renewal_job_run($params) {
  $lock = Civi::lockManager()->acquire('worker.membershipextras.offlineautorenewal');
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error('Could not acquire lock, another Offline Autorenewal process is running');
  }

  try {
    $offlineAutoRenewalJob = new CRM_MembershipExtras_Job_OfflineAutoRenewal();
    $result = $offlineAutoRenewalJob->run();
    $lock->release();
  }
  catch (Exception $error) {
    $lock->release();
    throw $error;
  }

  return civicrm_api3_create_success(
    $result,
    $params
  );
}
