<?php
// This file declares a managed database record of type "Job".
// The record will be automatically inserted, updated, or deleted from the
// database as appropriate. For more details, see "hook_civicrm_managed" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
return [
  [
    'name' => 'Cron:OfflineAutoRenewalJob.RunSingle',
    'entity' => 'Job',
    'params' => [
      'run_frequency' => 'Daily',
      'name' => 'Renew offline single instalment auto-renewal memberships',
      'description' => ts('Automatically renew single instalment offline/paylater membership that is configured to be auto-renewed'),
      'api_entity' => 'OfflineAutoRenewalJob',
      'api_action' => 'runsingle',
      // inactive by default to prevent it from running at wrong time
      // but should be activated once the site is ready, for example
      // after data migration is done.
      'is_active' => 0,
      'version' => 3,
    ],
  ],
  [
    'name' => 'Cron:OfflineAutoRenewalJob.RunMultiple',
    'entity' => 'Job',
    'params' => [
      'run_frequency' => 'Daily',
      'name' => 'Renew offline multiple instalments auto-renewal memberships',
      'description' => ts('Automatically renew multiple instalments offline/paylater membership that is configured to be auto-renewed'),
      'api_entity' => 'OfflineAutoRenewalJob',
      'api_action' => 'runmultiple',
      // inactive by default to prevent it from running at wrong time
      // but should be activated once the site is ready, for example
      // after data migration is done.
      'is_active' => 0,
      'version' => 3,
    ],
  ],
];
