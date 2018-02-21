<?php

/**
 * Class CRM_Core_Payment_ManualRecurringPayment.
 *
 * Empty payment processor implementation used for
 * any payment processor of Manual_Recurring_Payment type.
 *
 * The implementation is empty because this payment processor
 * will be treated similar to how we use 'pay later' option
 * so no transactions will actually happen and it will be done
 * manually instead.
 *
 * In this extension, we use it for Offline_Recurring_Contribution
 * payment processor.
 */
class CRM_Core_Payment_ManualRecurringPayment extends CRM_Core_Payment {

  /**
   * @inheritDoc
   */
  public function checkConfig() {
    return NULL;
  }

}
