<?php

/**
 * Utils and helper methods to handle offline Payment Processor Type.
 */
class CRM_Membership_Utils_PaymentProcessorType {
  public static $name = 'Manual_Recurring_Payment';

  /**
   * Returns an ID of offline payment processor type.
   *
   * @return int|NULL
   */
  public static function getManualRecurringPaymentProcessorTypeId() {
    return CRM_Core_DAO::getFieldValue(
      'CRM_Financial_DAO_PaymentProcessorType',
      self::$name,
      'id',
      'name'
    );
  }

  /**
   * Creates Manual Recurring Payment payment type with EFT payment instrument.
   * This payment type is used for our Offline Recurring Contribution
   * payment processor.
   *
   * @return array
   */
  public static function createManualRecurringPaymentProcessorType() {
    $manualRecurringPaymentProcessorParams = array(
      'name' => self::$name,
      'title' => 'Manual Recurring Payment',
      'is_active' => '1',
      'is_default' => '0',
      'class_name' => 'CRM_Membership_Payment_ManualRecurringPayment',
      'billing_mode' => CRM_Core_Payment::BILLING_MODE_NOTIFY, // This parameter is required but deprecated so I guess the value doesn't matter too much.
      'is_recur' => '1',
      'payment_type' => CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT,
      'payment_instrument_id' => self::getPaymentInstrumentValue('EFT'),
    );

    return civicrm_api3('PaymentProcessorType', 'create', $manualRecurringPaymentProcessorParams);
  }

  /**
   * Returns Payment Instrument value for specified Payment Instrument name.
   *
   * @param string $name
   *
   * @return int|NULL
   */
  private static function getPaymentInstrumentValue($name) {
    $paymentInstrument = civicrm_api3('OptionValue', 'get', array(
      'sequential' => 1,
      'option_group_id' => 'payment_instrument',
      'name' => $name,
    ));

    if (empty($paymentInstrument['values'][0]['value'])) {
      return NULL;
    }

    return $paymentInstrument['values'][0]['value'];
  }
}
