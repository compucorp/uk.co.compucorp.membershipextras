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

  /*
   * @TODO: Implement enable / disable / uninstall methods.
   *
   * - What to do with offline payment processor type and offline processor in these
   *   cases?
   * - Do we want to disable them?
   * - What if there are pending transactions using the offline processor?
   */

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
}
