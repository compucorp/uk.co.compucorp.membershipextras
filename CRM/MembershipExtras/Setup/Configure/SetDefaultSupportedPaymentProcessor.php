<?php

use CRM_MembershipExtras_Setup_Configure_ConfigurerInterface as ConfigurerInterface;

/**
 * Sets the 'Offline Recurring Contribution' payment
 * processor as supported payment processor.
 */
class CRM_MembershipExtras_Setup_Configure_SetDefaultSupportedPaymentProcessor implements ConfigurerInterface {

  public function apply() {
    try {
      $paymentProcessors = \Civi\Api4\PaymentProcessor::get()
        ->addSelect('id')
        ->addWhere('name', '=', 'Offline Recurring Contribution')
        ->addWhere('is_test', 'IN', [FALSE, TRUE])
        ->execute()
        ->getArrayCopy();
      if (empty($paymentProcessors)) {
        return;
      }

      $paymentProcessorIds = array_column($paymentProcessors, 'id');
      civicrm_api3('setting', 'create', [
        'membershipextras_paymentplan_supported_payment_processors' => $paymentProcessorIds,
      ]);
    }
    catch (Exception $exception) {
    }
  }

}
