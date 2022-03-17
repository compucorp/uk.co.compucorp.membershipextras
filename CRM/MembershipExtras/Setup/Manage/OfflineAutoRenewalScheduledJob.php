<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing the 'Memberships Offline auto-renewal' scheduled job.
 */
class CRM_MembershipExtras_Setup_Manage_OfflineAutoRenewalScheduledJob extends AbstractManager {

  /**
   * @inheritDoc
   */
  public function create() {
    $result = civicrm_api3('Job', 'get', [
      'name' => 'Renew offline auto-renewal memberships',
    ]);
    if (!empty($result['id'])) {
      return;
    }

    civicrm_api3('Job', 'create', [
      'run_frequency' => 'Daily',
      'name' => 'Renew offline auto-renewal memberships',
      'description' => ts('Automatically renew any offline/paylater membership that is configured to be auto-renewed'),
      'api_entity' => 'OfflineAutoRenewalJob',
      'api_action' => 'run',
      // inactive by default to prevent it from running at wrong time
      // but should be activated once the site is ready, for example
      // after data migration is done.
      'is_active' => 0,
    ]);
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    civicrm_api3('Job', 'get', [
      'name' => 'Renew offline auto-renewal memberships',
      'api.Job.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('Job', 'get', [
      'name' => 'Renew offline auto-renewal memberships',
      'api.Job.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
