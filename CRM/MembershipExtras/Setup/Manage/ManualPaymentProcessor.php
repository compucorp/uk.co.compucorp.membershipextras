<?php

use CRM_MembershipExtras_Setup_Manage_AbstractManager as AbstractManager;

/**
 * Managing the 'Offline Recurring Contribution' payment processor.
 */
class CRM_MembershipExtras_Setup_Manage_ManualPaymentProcessor extends AbstractManager {

  /**
   * @inheritDoc
   */
  public function create() {
    $params = [
      'is_test' => '0',
      'user_name' => 'unused',
      'url_site' => 'https://unused.org',
      'url_recur' => 'https://unused.org',
      'domain_id' => CRM_Core_Config::domainID(),
      'sequential' => 1,
      'name' => 'Offline Recurring Contribution',
      'payment_processor_type_id' => 'Manual_Recurring_Payment',
      'is_active' => '1',
      'is_default' => '0',
      'class_name' => 'Payment_Manual',
      'is_recur' => '1',
      'payment_instrument_id' => 'EFT',
    ];

    // creates the live version of the payment processor.
    $paymentProcessor = $this->alreadyExists(FALSE);
    if (empty($paymentProcessor)) {
      $params['is_test'] = 0;
      civicrm_api3('PaymentProcessor', 'create', $params);
    }

    // creates the test version of the payment processor.
    $paymentProcessor = $this->alreadyExists(TRUE);
    if (empty($paymentProcessor)) {
      $params['is_test'] = 1;
      civicrm_api3('PaymentProcessor', 'create', $params);
    }
  }

  /**
   * @inheritDoc
   */
  public function remove() {
    foreach ([0, 1] as $isTest) {
      civicrm_api3('PaymentProcessor', 'get', [
        'name' => 'Offline Recurring Contribution',
        'is_test' => $isTest,
        'api.PaymentProcessor.delete' => ['id' => '$value.id'],
      ]);
    }
  }

  /**
   * @inheritDoc
   */
  protected function toggle($status) {
    try {
      $paymentProcessorRecords = civicrm_api3('PaymentProcessor', 'get  ', [
        'return' => 'id',
        'name' => 'Offline Recurring Contribution',
      ]);
    }
    catch (Exception $e) {
      return;
    }

    foreach ($paymentProcessorRecords['values'] as $record) {
      // The API does not work properly for PaymentProcessor entity
      // for activation and deactivation, so we are using the DAO.
      $paymentProcessor = new CRM_Financial_DAO_PaymentProcessor();
      $paymentProcessor->id = $record['id'];
      $paymentProcessor->find(TRUE);
      $paymentProcessor->is_active = $status;
      $paymentProcessor->save();
    }
  }

  /**
   * @param bool $testVersion
   * Decides if we are checking for the test or the
   * live payment processor existence.
   *
   * @return bool
   *   True if the payment processor already exists,
   *   False otherwise.
   */
  private function alreadyExists($testVersion) {
    $processor = civicrm_api3('PaymentProcessor', 'get', [
      'name' => 'Offline Recurring Contribution',
      'sequential' => 1,
      'is_test' => $testVersion,
    ]);

    if (empty($processor['id'])) {
      return FALSE;
    }

    return TRUE;
  }

}
