<?php

/**
 * Allow creating the recur line items for webform
 * payment plans
 */
class CRM_MembershipExtras_WebformAPI_RecurringContributionLineItemCreator {

  /**
   * Creates the recur line items for the
   * payment plan.
   *
   * @param $contributionRecurId
   */
  public static function create($contributionRecurId) {
    $lineItemCreator = new CRM_MembershipExtras_Hook_PostProcess_RecurringContributionLineItemCreator($contributionRecurId);
    $lineItemCreator->forceAutorenewalFlagCalculation();
    $lineItemCreator->create();
  }
}
