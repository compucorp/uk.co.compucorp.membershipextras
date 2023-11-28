<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing the 'Renew payment scheme memberships' scheduled job.
 */
class CRM_MembershipExtras_Setup_Manage_PaymentSchemeAutoRenewalScheduledJob extends AbstractManager {

  const JOB_NAME = 'Renew payment scheme memberships';

  /**
   * @inheritDoc
   */
  public function create() {
    $result = civicrm_api3('Job', 'get', [
      'name' => self::JOB_NAME,
    ]);
    if (!empty($result['id'])) {
      return;
    }

    civicrm_api3('Job', 'create', [
      'run_frequency' => 'Daily',
      'name' => self::JOB_NAME,
      'description' => ts('Automatically renew any membership that is paid with a payment plan linked to a payment scheme'),
      'api_entity' => 'PaymentSchemeAutoRenewalJob',
      'api_action' => 'run',
      'is_active' => 0,
    ]);
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    civicrm_api3('Job', 'get', [
      'name' => self::JOB_NAME,
      'api.Job.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('Job', 'get', [
      'name' => self::JOB_NAME,
      'api.Job.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
