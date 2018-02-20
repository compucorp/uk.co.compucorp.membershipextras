<?php

/**
 * Class CRM_Core_Payment_ManualRecurringPayment.
 *
 * It's used by new Manual_Recurring_Payment payment processor type
 * (created within CRM_PaymentPlan_Utils_PaymentProcessorType class)
 * and for Offline_Recurring_Contribution payment processor
 * (created within CRM_PaymentPlan_Utils_PaymentProcessor class).
 */
class CRM_Core_Payment_ManualRecurringPayment extends CRM_Core_Payment {

  /**
   * @inheritDoc
   */
  public function checkConfig() {
  }
}
