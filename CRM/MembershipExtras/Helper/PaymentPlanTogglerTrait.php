<?php

/**
 * Trait CRM_MembershipExtras_Helper_PaymentPlanTogglerTrait
 */
trait CRM_MembershipExtras_Helper_PaymentPlanTogglerTrait {

  /**
   * @param $region
   */
  private function addResources($region) {
    Civi::resources()->add([
      'scriptFile' => ['uk.co.compucorp.membershipextras', 'js/paymentPlanToggler.js'],
      'region' => $region,
    ]);
  }

}
