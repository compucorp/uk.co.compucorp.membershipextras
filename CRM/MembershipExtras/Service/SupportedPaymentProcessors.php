<?php

class CRM_MembershipExtras_Service_SupportedPaymentProcessors {

  /**
   * Checks if the payment processor is
   * a supported payment processor.
   *
   * Payment processors that implements
   * Payment_Manual class are considered
   * supported.
   *
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
    foreach (self::getManualPaymentProcessors() as $paymentProcessor) {
      $supportedPaymentProcessorIDs[] = $paymentProcessor['id'];
    }

    return $supportedPaymentProcessorIDs;
  }

  /**
   * Builds an array mapping manual payment processor id's to processor name.
   *
   * @return array
   */
  public static function getIDNameMap() {
    $supportedPaymentProcessorOptions = [];
    foreach (self::getManualPaymentProcessors() as $paymentProcessor) {
      $testOrLive = $paymentProcessor['is_test'] ? 'Test - ': 'Live - ';
      $supportedPaymentProcessorOptions[$paymentProcessor['id']] = $testOrLive . $paymentProcessor['name'];
    }

    return $supportedPaymentProcessorOptions;
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
