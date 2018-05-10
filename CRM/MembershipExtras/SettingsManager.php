<?php

/**
 * Helps manage settings for the extension.
 */
class CRM_MembershipExtras_SettingsManager {

  /**
   * Returns the details of the default payment processor as per payment plan
   * settings, or NULL if it does not exist.
   *
   * @return array
   */
  public static function getDefaultProcessor() {
    $defaultPaymentProcessorID = civicrm_api3('Setting', 'get', array(
      'sequential' => 1,
      'return' => array('membershipextras_paymentplan_default_processor'),
    ))['values'][0]['membershipextras_paymentplan_default_processor'];

    $processor = civicrm_api3('PaymentProcessor', 'get', [
      'id' => $defaultPaymentProcessorID,
      'sequential' => 1,
    ]);

    if (empty($processor['id'])) {
      return NULL;
    }

    return $processor['values'][0];
  }

}
