<?php

use CRM_MembershipExtras_PaymentProcessorType_ManualRecurringPayment as ManualRecurringPaymentProcessorType;

/**
 * 'Offline Recurring Contribution' payment processor.
 */
class CRM_MembershipExtras_PaymentProcessor_OfflineRecurringContribution {

  /**
   * 'Offline Recurring Contribution' Payment Processor machine name.
   */
  const NAME = 'Offline Recurring Contribution';

  /**
   * Creates 'Offline Recurring Contribution' payment processor if it does not
   * exist.
   *
   */
  public function createIfNotExists() {
    $this->createLiveVersion();
    $this->createTestVersion();
  }

  private function createLiveVersion() {
    $paymentProcessor = $this->getLiveVersion();
    if ($paymentProcessor != NULL) {
      return;
    }

    $params = [
      'is_test' => '0',
      'user_name' => 'Live',
      'url_site' => 'https://live.civicrm.org',
      'url_recur' => 'https://liverecurr.civicrm.org',
    ];

    $params = array_merge($params, $this->getCommonCreationParameters());

    civicrm_api3('PaymentProcessor', 'create', $params);
  }

  private function createTestVersion() {
    $paymentProcessor = $this->getTestVersion();
    if ($paymentProcessor != NULL) {
      return;
    }

    $params = [
      'is_test' => '1',
      'user_name' => 'Test',
      'url_site' => 'https://test.civicrm.org',
      'url_recur' => 'https://testrecurr.civicrm.org',
    ];

    $params = array_merge($params, $this->getCommonCreationParameters());

    civicrm_api3('PaymentProcessor', 'create', $params);
  }


  private function getLiveVersion() {
    return $this->getPaymentProcessor(0);
  }


  private function getTestVersion() {
    return $this->getPaymentProcessor(1);
  }

  private function getPaymentProcessor($isTest) {
    $processor = civicrm_api3('PaymentProcessor', 'get', [
      'name' => self::NAME,
      'sequential' => 1,
      'is_test' => $isTest,
    ]);

    if (empty($processor['id'])) {
      return NULL;
    }

    return $processor['values'][0];
  }

  private function getCommonCreationParameters() {
    return [
      'domain_id' => CRM_Core_Config::domainID(),
      'sequential' => 1,
      'name' => self::NAME,
      'payment_processor_type_id' => ManualRecurringPaymentProcessorType::NAME,
      'is_active' => '1',
      'is_default' => '0',
      'class_name' => 'Payment_Manual',
      'is_recur' => '1',
      'payment_instrument_id' => 'EFT',
      'financial_account_id' => $this->getDepositBankAccountId(),
    ];
  }

  private function getDepositBankAccountId() {
    return civicrm_api3('FinancialAccount', 'getvalue', [
      'return' => 'id',
      'name' => 'Deposit Bank Account',
    ]);
  }

  public function setAsDefaultPaymentPlanProcessor() {
    $paymentProcessorId = civicrm_api3('PaymentProcessor', 'getvalue', [
      'return' => 'id',
      'name' => self::NAME,
      'is_test' => 0,
    ]);

    civicrm_api3('setting', 'create',[
      'membershipextras_paymentplan_default_processor' => $paymentProcessorId,
    ]);
  }

  /**
   * Removes the payment processor.
   */
  public function remove() {
    foreach([0, 1] as $isTest) {
      civicrm_api3('PaymentProcessor', 'get', [
        'name' => self::NAME,
        'is_test' => $isTest,
        'api.PaymentProcessor.delete' => ['id' => '$value.id'],
      ]);
    }
  }

  /**
   * Enables/Disables the payment
   * processor based on the passed
   * status.
   *
   * @param $newStatus
   *   True to enable, False to disable.
   */
  public function toggle($newStatus) {
    $paymentProcessorRecords = civicrm_api3('PaymentProcessor', 'get', [
      'return' => 'id',
      'name' => self::NAME,
    ]);

    foreach ($paymentProcessorRecords['values'] as $record) {
      $paymentProcessor = new CRM_Financial_DAO_PaymentProcessor();
      $paymentProcessor->id = $record['id'];
      $paymentProcessor->find(TRUE);
      $paymentProcessor->is_active = $newStatus;
      $paymentProcessor->save();
    }
  }

}
