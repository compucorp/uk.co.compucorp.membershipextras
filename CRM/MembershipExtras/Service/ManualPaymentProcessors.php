<?php

class CRM_MembershipExtras_Service_ManualPaymentProcessors {

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

  /**
   * Builds an array mapping manual payment processor id's to processor name.
   *
   * @return array
   */
  public static function getProcessorsIDNameMap() {
    $offlineRecPaymentProcessors = civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'class_name' => 'Payment_Manual',
      'options' => ['limit' => 0],
    ]);

    $recPaymentProcessors = [];
    if (!empty($offlineRecPaymentProcessors['values'])) {
      foreach ($offlineRecPaymentProcessors['values'] as $paymentProcessor) {
        $recPaymentProcessors[$paymentProcessor['id']] = $paymentProcessor['name'];
      }
    }

    return $recPaymentProcessors;
  }

}
