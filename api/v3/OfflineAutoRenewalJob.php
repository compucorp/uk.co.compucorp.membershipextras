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
    // Extract contact_ids from params if provided
    $contactIds = NULL;
    if (!empty($params['contact_ids'])) {
      if (is_string($params['contact_ids'])) {
        $contactIds = array_map('intval', explode(',', $params['contact_ids']));
      }
      elseif (is_array($params['contact_ids'])) {
        $contactIds = array_map('intval', $params['contact_ids']);
      }
    }

    $offlineAutoRenewalJob = new CRM_MembershipExtras_Job_OfflineAutoRenewal();
    $result = $offlineAutoRenewalJob->runSingle($contactIds);
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
    // Extract contact_ids from params if provided
    $contactIds = NULL;
    if (!empty($params['contact_ids'])) {
      if (is_string($params['contact_ids'])) {
        $contactIds = array_map('intval', explode(',', $params['contact_ids']));
      }
      elseif (is_array($params['contact_ids'])) {
        $contactIds = array_map('intval', $params['contact_ids']);
      }
    }

    $offlineAutoRenewalJob = new CRM_MembershipExtras_Job_OfflineAutoRenewal();
    $result = $offlineAutoRenewalJob->runMultiple($contactIds);
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
 * API spec for offline_auto_renewal_job.runsingle
 *
 * @param array $spec
 */
function _civicrm_api3_offline_auto_renewal_job_runsingle_spec(&$spec) {
  $spec['contact_ids'] = [
    'title' => 'Contact IDs',
    'description' => 'Optional comma-separated list of contact IDs to filter renewals by. If not provided, all eligible contacts will be processed.',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}

/**
 * API spec for offline_auto_renewal_job.runmultiple
 *
 * @param array $spec
 */
function _civicrm_api3_offline_auto_renewal_job_runmultiple_spec(&$spec) {
  $spec['contact_ids'] = [
    'title' => 'Contact IDs',
    'description' => 'Optional comma-separated list of contact IDs to filter renewals by. If not provided, all eligible contacts will be processed.',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}
