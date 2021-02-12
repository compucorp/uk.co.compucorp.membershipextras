<?php

class CRM_MembershipExtras_Queue_OfflineAutoRenewal {

  const QUEUE_NAME = 'uk.co.compucorp.membershipextras.queue.offlineautorenewal';

  private static $queue;

  public static function getQueue() {
    if (!self::$queue) {
      self::$queue = CRM_Queue_Service::singleton()->create([
        'type' => 'Sql',
        'name' => self::QUEUE_NAME,
        'reset' => FALSE,
      ]);
    }

    return self::$queue;
  }

}
