<?php

use CRM_PaymentPlan_ExtensionUtil as E;
use CRM_PaymentPlan_PaymentProcessorType_ManualRecurringPayment as ManualRecurringPaymentProcessorType;
use CRM_PaymentPlan_PaymentProcessor_OfflineRecurringContribution as OfflineRecurringPaymentProcessor;

/**
 * Collection of upgrade steps.
 */
class CRM_PaymentPlan_Upgrader extends CRM_PaymentPlan_Upgrader_Base {

  public function install() {
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
