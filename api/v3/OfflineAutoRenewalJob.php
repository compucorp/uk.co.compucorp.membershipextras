<?php

/**
 * Membership offline single instalment auto-renewal scheduled job API
 *
 * @param $params
 * @return array
 */
function civicrm_api3_offline_auto_renewal_job_runsingle($params) {
  $lock = Civi::lockManager()->acquire('worker.membershipextras.offlinemultipleautorenewal');
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error('Could not acquire lock, another Offline Autorenewal process is running');
  }

  try {
    $offlineAutoRenewalJob = new CRM_MembershipExtras_Job_OfflineAutoRenewal();
    $result = $offlineAutoRenewalJob->runSingle();
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

/**
 * Membership offline multiple instalments auto-renewal scheduled job API
 *
 * @param $params
 * @return array
 */
function civicrm_api3_offline_auto_renewal_job_runmultiple($params) {
  $lock = Civi::lockManager()->acquire('worker.membershipextras.offlinesingleautorenewal');
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error('Could not acquire lock, another Offline Autorenewal process is running');
  }

  try {
    $offlineAutoRenewalJob = new CRM_MembershipExtras_Job_OfflineAutoRenewal();
    $result = $offlineAutoRenewalJob->runMultiple();
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
