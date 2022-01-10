<?php

use CRM_MembershipExtras_Setup_Configure_ConfigurerInterface as ConfigurerInterface;

/**
 * Making the 'Offline Recurring Contribution' payment processor the default
 * payment processor for payment plan payments done through CiviCRM membership form.
 * It is just to set the config to a default value, but it can be changed later by
 * the admin if needed.
 */
class CRM_MembershipExtras_Setup_Configure_SetManualPaymentProcessorAsDefaultProcessor implements ConfigurerInterface {

  public function apply() {
    try {
      $paymentProcessorId = civicrm_api3('PaymentProcessor', 'getvalue', [
        'return' => 'id',
        'name' => 'Offline Recurring Contribution',
        'is_test' => 0,
      ]);

      civicrm_api3('setting', 'create', [
        'membershipextras_paymentplan_default_processor' => $paymentProcessorId,
      ]);
    }
    catch (Exception $exception) {
    }
  }

}
