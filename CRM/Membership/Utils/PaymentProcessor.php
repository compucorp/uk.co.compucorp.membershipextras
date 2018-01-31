<?php

/**
 * Utils and helper methods to handle offline Payment Processor.
 */
class CRM_Membership_Utils_PaymentProcessor {
  public static $name = 'Offline_Recurring_Contribution';

  /**
   * Returns an ID of offline payment processor.
   *
   * @return int|NULL
   */
  public static function getOfflineRecurringContributionProcessorId() {
    $processor = civicrm_api3('PaymentProcessor', 'get', array(
        'name' => self::$name,
    ));

    if (empty($processor['id'])) {
      return NULL;
    }

    return $processor['id'];
  }

  /**
   * Creates Offline Recurring Contribution payment processor.
   *
   * @return array
   */
  public static function createOfflineRecurringContributionProcessor() {
    $domain = CRM_Core_BAO_Domain::getDomain();
    $processorTypeId = CRM_Membership_Utils_PaymentProcessorType::getManualRecurringPaymentProcessorTypeId();

    if (empty($processorTypeId)) {
      throw new Exception(ts('Cannot create "Offline Recurring Contribution" payment processor because "Manual Recurring Payment" processor type is missing.'));
    }

    $offlineRecurringContributionProcessorParams = array(
      'domain_id' => $domain->id,
      'name' => self::$name,
      'payment_processor_type_id' => $processorTypeId,
      'is_active' => '1',
      'is_default' => '0',
      'is_test' => '0',
      'class_name' => 'Payment_ManualRecurringPayment',
      'is_recur' => '1',
      'payment_instrument_id' => CRM_Membership_Utils_PaymentProcessorType::getPaymentInstrumentValue('EFT'),
    );

    return civicrm_api3('PaymentProcessor', 'create', $offlineRecurringContributionProcessorParams);
  }
}
