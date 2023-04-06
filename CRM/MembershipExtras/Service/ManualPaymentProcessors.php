<?php

class CRM_MembershipExtras_Service_ManualPaymentProcessors {

  /**
   * Checks if the payment processor is a
   * Manual payment processor. (a Processor that
   * Implements Payment_Manual class)
   *
   * @param $processorID
   *
   * @return bool
   */
  public static function isManualPaymentProcessor($processorID) {
    $manualPaymentProcessors = self::getIDs();

    if (empty($processorID) || in_array($processorID, $manualPaymentProcessors)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the list of Payment Processors
   * that use 'Payment_Manual' class.
   *
   * @return array
   */
  public static function getIDs() {
    $manualPaymentProcessors = civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'class_name' => 'Payment_Manual',
      'options' => ['limit' => 0],
    ])['values'];

    $manualPaymentProcessorsList = [];
    foreach ($manualPaymentProcessors as $paymentProcessor) {
      $manualPaymentProcessorsList[] = $paymentProcessor['id'];
    }

    return $manualPaymentProcessorsList;
  }

}
