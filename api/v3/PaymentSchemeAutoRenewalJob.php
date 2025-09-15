<?php

/**
 * Membership auto-renewal scheduled job API for payment plans with payment scheme.
 *
 * @param $params
 * @return array
 */
function civicrm_api3_payment_scheme_auto_renewal_job_run($params) {
  $lock = Civi::lockManager()->acquire('worker.membershipextras.paymentschemeautorenewal');
  if (!$lock->isAcquired()) {
    return civicrm_api3_create_error('Could not acquire lock, another Payment Scheme Autorenewal process is running');
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

    $paymentSchemeAutoRenewalJob = new CRM_MembershipExtras_Job_PaymentSchemeAutoRenewal();
    $result = $paymentSchemeAutoRenewalJob->run($contactIds);
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
 * API spec for payment_scheme_auto_renewal_job.run
 *
 * @param array $spec
 */
function _civicrm_api3_payment_scheme_auto_renewal_job_run_spec(&$spec) {
  $spec['contact_ids'] = [
    'title' => 'Contact IDs',
    'description' => 'Optional comma-separated list of contact IDs to filter renewals by. If not provided, all eligible contacts will be processed.',
    'type' => CRM_Utils_Type::T_STRING,
    'api.required' => 0,
  ];
}
