<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_PaymentProcessorType_ManualRecurringPayment as ManualRecurringPaymentProcessorType;
use CRM_MembershipExtras_PaymentProcessor_OfflineRecurringContribution as OfflineRecurringPaymentProcessor;

/**
 * Collection of upgrade steps.
 */
class CRM_MembershipExtras_Upgrader extends CRM_MembershipExtras_Upgrader_Base {

  public function postInstall() {
    $this->createManualRecurringPaymentProcessorType();
    $this->createOfflineRecurringContributionProcessor();
  }

  /**
   * Creates 'Manual Recurring Payment' Payment processor type
   */
  private function createManualRecurringPaymentProcessorType() {
    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->create();
  }

  /**
   * Creates 'Offline Recurring Contribution' payment processor
   */
  private function createOfflineRecurringContributionProcessor() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->create();
  }

  public function enable() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->toggle(TRUE);

    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->toggle(TRUE);
  }

  public function disable() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->toggle(FALSE);

    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->toggle(FALSE);
  }

  public function uninstall() {
    $this->removeOfflineRecurringContributionProcessor();
    $this->removeManualRecurringPaymentProcessorType();
  }

  /**
   * Removes 'Offline Recurring Contribution' payment processor
   */
  private function removeOfflineRecurringContributionProcessor() {
    $paymentProcessor = new OfflineRecurringPaymentProcessor();
    $paymentProcessor->remove();
  }

  /**
   * Removes 'Manual Recurring Payment' Payment processor type
   */
  private function removeManualRecurringPaymentProcessorType() {
    $paymentProcessorType = new ManualRecurringPaymentProcessorType();
    $paymentProcessorType->remove();
  }

}
