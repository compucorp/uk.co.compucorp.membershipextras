<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing the 'Memberships Offline auto-renewal' scheduled job.
 */
class CRM_MembershipExtras_Setup_Manage_OfflineAutoRenewalScheduledJob extends AbstractManager {

  /**
   * @var array
   */
  protected $jobs = [
    'Renew offline single instalment auto-renewal memberships' => [
      'name' => 'Renew offline single instalment auto-renewal memberships',
      'description' => 'Automatically renew single instalment offline/paylater',
      'method' => 'runsingle',
    ],
    'Renew offline multiple instalments auto-renewal memberships' => [
      'name' => 'Renew offline multiple instalments auto-renewal memberships',
      'description' => 'Automatically renew multiple instalments offline/paylater',
      'method' => 'runmultiple',
    ],
  ];

  /**
   * @inheritDoc
   */
  public function create() {

    foreach ($this->jobs as $job) {
      $result = civicrm_api3('Job', 'get', [
        'name' => $job['name'],
      ]);
      if (empty($result['id'])) {
        civicrm_api3('Job', 'create', [
          'run_frequency' => 'Daily',
          'name' => $job['name'],
          'description' => ts($job['description'] . ' membership that is configured to be auto-renewed'),
          'api_entity' => 'OfflineAutoRenewalJob',
          'api_action' => $job['method'],
          // inactive by default to prevent it from running at wrong time
          // but should be activated once the site is ready, for example
          // after data migration is done.
          'is_active' => 0,
        ]);
      }
    }

    return;
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    \Civi\Api4\Job::delete(FALSE)
      ->addWhere('name', 'IN', array_column($this->jobs, 'name'))
      ->execute();
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    \Civi\Api4\Job::update(FALSE)
      ->addValue('is_active', $status)
      ->addWhere('name', 'IN', array_column($this->jobs, 'name'))
      ->execute();
  }

}
