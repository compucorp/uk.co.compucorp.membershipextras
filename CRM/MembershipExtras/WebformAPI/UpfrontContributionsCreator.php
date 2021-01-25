<?php

/**
 * Allow creating the upfront contributions for webform
 * payment plans
 */
class CRM_MembershipExtras_WebformAPI_UpfrontContributionsCreator {

  /**
   * Creates the upfront contributions for the
   * payment plan.
   *
   * @param $contributionRecurId
   */
  public static function create($contributionRecurId) {
    $installmentsHandler = new CRM_MembershipExtras_Service_MembershipInstalmentsHandler($contributionRecurId);
    $installmentsHandler->createRemainingInstalmentContributionsUpfront();
  }

}
