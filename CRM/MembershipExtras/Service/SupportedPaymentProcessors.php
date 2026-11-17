<?php

class CRM_MembershipExtras_Service_SupportedPaymentProcessors {

  /**
   * Checks if the payment processor is
   * a supported payment processor.
   *
   * Payment processors that implements
   * Payment_Manual class are considered
   * supported, also other extensions can opt in to get
   * Membershipextra support by implementing:
   * membershipextras_updateSupportedPaymentProcessors
   * hook.
   * @param $processorID
   *
   * @return bool
   */
  public static function isSupportedPaymentProcessor($processorID) {
    $supportedPaymentProcessorIDs = self::getIDs();

    if (empty($processorID) || in_array($processorID, $supportedPaymentProcessorIDs)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the list of the supported
   * Payment Processors.
   *
   * @return array
   */
  public static function getIDs() {
    $supportedPaymentProcessorIDs = [];
    foreach (self::getSupportedPaymentProcessors() as $paymentProcessor) {
      $supportedPaymentProcessorIDs[] = $paymentProcessor['id'];
    }

    return $supportedPaymentProcessorIDs;
  }

  /**
   * Gets the supported processors that can only collect a payment plan's
   * instalments with a saved payment method (payment_token_id on the plan).
   *
   * An extension marks its processors this way by setting
   * 'requires_payment_token' => TRUE on the rows it adds in
   * hook_membershipextras_updateSupportedPaymentProcessors. A plan on such
   * a processor without a saved payment method can't be charged, so it is
   * not renewed.
   *
   * @return int[]
   */
  public static function getIDsRequiringPaymentToken(): array {
    $ids = [];
    foreach (self::getSupportedPaymentProcessors() as $paymentProcessor) {
      if (!empty($paymentProcessor['requires_payment_token']) && is_numeric($paymentProcessor['id'] ?? NULL)) {
        $ids[] = (int) $paymentProcessor['id'];
      }
    }

    return $ids;
  }

  /**
   * Builds an array mapping manual payment processor id's to processor name.
   *
   * @return array
   */
  public static function getIDNameMap() {
    $supportedPaymentProcessorOptions = [];
    foreach (self::getSupportedPaymentProcessors() as $paymentProcessor) {
      $testOrLive = $paymentProcessor['is_test'] ? 'Test - ' : 'Live - ';
      $supportedPaymentProcessorOptions[$paymentProcessor['id']] = $testOrLive . $paymentProcessor['name'];
    }

    return $supportedPaymentProcessorOptions;
  }

  private static function getSupportedPaymentProcessors() {
    $supportedPaymentProcessors = self::getManualPaymentProcessors();

    // Allow extensions to opt in for Membershipextras Support.
    $null = CRM_Utils_Hook::$_nullObject;
    CRM_Utils_Hook::singleton()->invoke(
      ['supportedPaymentProcessor'],
      $supportedPaymentProcessors, $null, $null, $null, $null, $null,
      'membershipextras_updateSupportedPaymentProcessors'
    );

    return $supportedPaymentProcessors;
  }

  /**
   * Gets payment processors that implement
   * the core Payment_Manual class. Which
   * are all and by default considered supported payment
   * processors by this extension.
   *
   * @return array
   */
  private static function getManualPaymentProcessors() {
    return civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'class_name' => 'Payment_Manual',
      'options' => ['limit' => 0],
    ])['values'];
  }

}
