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
    $paymentSchemeAutoRenewalJob = new CRM_MembershipExtras_Job_PaymentSchemeAutoRenewal();
    $result = $paymentSchemeAutoRenewalJob->run();
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
