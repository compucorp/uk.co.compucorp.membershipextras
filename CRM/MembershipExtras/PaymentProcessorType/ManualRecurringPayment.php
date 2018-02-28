<?php

/**
 * 'Manual Recurring Payment' payment processor type.
 *  this type implements Payment_Manual
 *  which is defined for offline payment 'pay later' purposes.
 */
class CRM_MembershipExtras_PaymentProcessorType_ManualRecurringPayment {

  /**
   * 'Manual Recurring Payment' Payment Processor type machine name.
   */
  const NAME = 'Manual_Recurring_Payment';

  /**
   * Creates the payment processor type if
   * it does not exist.
   *
   * @return array
   *   The details of the created payment processor type.
   */
  public function create() {
    $processorType = $this->get();
    if ($processorType !== NULL) {
      return $processorType;
    }

    $params = [
      'sequential' => 1,
      'name' => self::NAME,
      'title' => 'Manual Recurring Payment',
      'is_active' => '1',
      'is_default' => '0',
      'class_name' => 'Payment_Manual',
      // This parameter is required for now but it's deprecated, so its value does not matter
      'billing_mode' => CRM_Core_Payment::BILLING_MODE_NOTIFY,
      'is_recur' => '1',
      'payment_type' => CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT,
      'payment_instrument_id' => 'EFT',
    ];

    $processorType = civicrm_api3('PaymentProcessorType', 'create', $params);
    return $processorType['values'][0];
  }

  /**
   * Returns the details of the payment processor type,
   * or NULL if it does not exist.
   *
   * @return array
   */
  private function get() {
    $processorType = civicrm_api3('PaymentProcessorType', 'get', [
      'name' => self::NAME,
      'sequential' => 1,
    ]);

    if (empty($processorType['id'])) {
      return NULL;
    }

    return $processorType['values'][0];
  }

  /**
   * Enables/Disables the payment
   * processor type based on the passed
   * status.
   *
   * @param $newStatus
   *   True to enable, False to disable.
   */
  public function toggle($newStatus) {
    civicrm_api3('PaymentProcessorType', 'get', [
      'name' => self::NAME,
      'api.PaymentProcessorType.create' => ['id' => '$value.id', 'is_active' => $newStatus],
    ]);
  }

  /**
   * Removes the payment processor type.
   */
  public function remove() {
    civicrm_api3('PaymentProcessorType', 'get', [
      'name' => self::NAME,
      'api.PaymentProcessorType.delete' => ['id' => '$value.id'],
    ]);
  }

}
