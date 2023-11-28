<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0012 {

  /**
   * Creates 'Renew payment scheme memberships'
   * scheduled job.
   *
   * @return void
   */
  public function apply() {
    $paymentSchemeScheduledJobManager = new CRM_MembershipExtras_Setup_Manage_PaymentSchemeAutoRenewalScheduledJob();
    $paymentSchemeScheduledJobManager->create();
  }

}
