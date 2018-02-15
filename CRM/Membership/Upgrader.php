<?php
use CRM_Membership_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Membership_Upgrader extends CRM_Membership_Upgrader_Base {

  /**
   * Install action.
   *
   * @return bool
   */
  public function install() {
    // First, install the new Payment Processor Type.
    $this->installManualRecurringPaymentProcessorType();
    // Then, install the new Payment Processor.
    $this->installOfflineRecurringContributionProcessor();

    return TRUE;
  }

  /**
   * Creates Manual Recurring Payment processor type if it doesn't exist yet.
   */
  private function installManualRecurringPaymentProcessorType() {
    $paymentProcessorTypeId = CRM_Membership_Utils_PaymentProcessorType::getManualRecurringPaymentProcessorTypeId();
    if (empty($paymentProcessorTypeId)) {
      CRM_Membership_Utils_PaymentProcessorType::createManualRecurringPaymentProcessorType();
    }
  }

  /**
   * Creates Offline Recurring Contribution processor if it doesn't exist yet.
   */
  private function installOfflineRecurringContributionProcessor() {
    $paymentProcessorId = CRM_Membership_Utils_PaymentProcessor::getOfflineRecurringContributionProcessorId();
    if (empty($paymentProcessorId)) {
      CRM_Membership_Utils_PaymentProcessor::createOfflineRecurringContributionProcessor();
    }
  }

  public function uninstall() {
    $this->removeManualRecurringPaymentProcessors();
    $this->removeManualRecurringPaymentProcessorType();
  }

  private function removeManualRecurringPaymentProcessors() {
    civicrm_api3('PaymentProcessor', 'get', [
      'payment_processor_type_id' => 'Manual_Recurring_Payment',
      'api.PaymentProcessor.delete' => ['id' => '$value.id'],
    ]);
  }

  private function removeManualRecurringPaymentProcessorType() {
    civicrm_api3('PaymentProcessorType', 'get', [
      'name' => 'Manual_Recurring_Payment',
      'api.PaymentProcessorType.delete' => ['id' => '$value.id'],
    ]);
  }

  public function enable() {
    $this->toggleManualRecurringPaymentProcessors(TRUE);
    $this->toggleManualRecurringPaymentProcessorType(TRUE);
  }

  public function disable() {
    $this->toggleManualRecurringPaymentProcessors(FALSE);
    $this->toggleManualRecurringPaymentProcessorType(FALSE);
  }

  private function toggleManualRecurringPaymentProcessors($state) {
    civicrm_api3('PaymentProcessor', 'get', [
      'payment_processor_type_id' => 'Manual_Recurring_Payment',
      'api.PaymentProcessor.create' => ['id' => '$value.id', 'is_active' => $state],
    ]);
  }

  private function toggleManualRecurringPaymentProcessorType($state) {
    civicrm_api3('PaymentProcessorType', 'get', [
      'name' => 'Manual_Recurring_Payment',
      'api.PaymentProcessorType.create' => ['id' => '$value.id', 'is_active' => $state],
    ]);
  }

}
