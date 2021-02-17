<?php

use CRM_MembershipExtras_Queue_Builder_OfflineAutoRenewal_MultipleInstalmentPlan as OfflineAutoRenewalMultipleInstalmentPlanQueueBuilder;
use CRM_MembershipExtras_Queue_Builder_OfflineAutoRenewal_SingleInstalmentPlan as OfflineAutoRenewalSingleInstalmentPlanQueueBuilder;
use CRM_MembershipExtras_Queue_OfflineAutoRenewal as OfflineAutoRenewalQueue;

class CRM_MembershipExtras_Job_OfflineAutoRenewal {

  private $queue;

  /**
   * @var int
   */
  private $numberOfQueueItems;

  public function __construct() {
    $this->queue = OfflineAutoRenewalQueue::getQueue();
    $this->numberOfQueueItems = (int) $this->queue->numberOfItems();
  }

  /**
   * Starts the scheduled job for renewing offline
   * auto-renewal memberships.
   *
   * @return True
   */
  public function run() {
    $this->addTasksToQueue();
    $this->runQueue();

    return TRUE;
  }

  private function addTasksToQueue() {
    if ($this->numberOfQueueItems > 0) {
      return;
    }

    $queueBuilders = [
      new OfflineAutoRenewalMultipleInstalmentPlanQueueBuilder($this->queue),
      new OfflineAutoRenewalSingleInstalmentPlanQueueBuilder($this->queue),
    ];
    foreach ($queueBuilders as $queueBuilder) {
      $queueBuilder->run();
    }

    $this->numberOfQueueItems = (int) $this->queue->numberOfItems();
  }

  private function runQueue() {
    if ($this->numberOfQueueItems === 0) {
      return;
    }

    $runner = new CRM_Queue_Runner([
      'title' => ts('Processing membership renewals, this may take a while depending on how many records are processed ..'),
      'queue' => $this->queue,
      'errorMode' => CRM_Queue_Runner::ERROR_CONTINUE,
      'onEnd' => array('CRM_MembershipExtras_Job_OfflineAutoRenewal', 'onEnd'),
      'onEndUrl' => CRM_Utils_System::url('civicrm/admin/job', ['reset' => 1]),
    ]);

    // Only use `runAllViaWeb` if the admin executed the job from CiviCRM UI.
    $currentPath = CRM_Utils_System::currentPath();
    if ($currentPath === 'civicrm/admin/job') {
      $runner->runAllViaWeb();
    }
    else {
      $runner->runAll();
    }
  }

  public static function onEnd(CRM_Queue_TaskContext $ctx) {
    $message = ts('Membership Renewals Processing Completed');
    CRM_Core_Session::setStatus($message, '', 'success');
  }

}
