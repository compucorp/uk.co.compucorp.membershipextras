<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing the 'Manual Recurring Payment' payment processor type.
 */
class CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessorType extends AbstractManager {

  /**
   * @inheritDoc
   */
  public function create() {
    $processorType = civicrm_api3('PaymentProcessorType', 'get', [
      'name' => 'Manual_Recurring_Payment',
      'sequential' => 1,
    ]);
    if (!empty($processorType['id'])) {
      return;
    }

    civicrm_api3('PaymentProcessorType', 'create', [
      'sequential' => 1,
      'name' => 'Manual_Recurring_Payment',
      'title' => 'Manual Recurring Payment',
      'is_active' => '1',
      'is_default' => '0',
      'class_name' => 'Payment_Manual',
      // This parameter is required for now for the API, but it's deprecated, so its value does not matter.
      'billing_mode' => CRM_Core_Payment::BILLING_MODE_NOTIFY,
      'is_recur' => '1',
      'payment_type' => CRM_Core_Payment::PAYMENT_TYPE_DIRECT_DEBIT,
      'payment_instrument_id' => 'EFT',
      'user_name_label' => 'User Name',
    ]);
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    civicrm_api3('PaymentProcessorType', 'get', [
      'name' => 'Manual_Recurring_Payment',
      'api.PaymentProcessorType.delete' => ['id' => '$value.id'],
    ]);
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    civicrm_api3('PaymentProcessorType', 'get', [
      'name' => 'Manual_Recurring_Payment',
      'api.PaymentProcessorType.create' => ['id' => '$value.id', 'is_active' => $status],
    ]);
  }

}
