<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0013 {

  public function apply() {
    $oldOfflineJob = \Civi\Api4\Job::get(FALSE)
      ->addWhere('name', '=', 'Renew offline auto-renewal memberships')
      ->execute()
      ->first();

    if (!empty($oldOfflineJob)) {
      //get rid of old job if exists
      civicrm_api3('Job', 'get', [
        'name' => 'Renew offline auto-renewal memberships',
        'api.Job.delete' => ['id' => '$value.id'],
      ]);
    }

    $offlineAutoRenewalScheduledJob = new CRM_MembershipExtras_Setup_Manage_OfflineAutoRenewalScheduledJob();
    $offlineAutoRenewalScheduledJob->create();
    //activate the new jobs if the old one was active
    if (!empty($oldOfflineJob) && $oldOfflineJob['is_active']) {
      $offlineAutoRenewalScheduledJob->activate();
    }

    return TRUE;
  }

}
