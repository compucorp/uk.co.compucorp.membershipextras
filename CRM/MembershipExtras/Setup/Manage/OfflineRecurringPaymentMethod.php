<?php

use Civi\Api4\EntityFinancialAccount;

class CRM_MembershipExtras_Setup_Manage_OfflineRecurringPaymentMethod {

  private const NAME = 'Offline Automated Recurring';

  /**
   * Ensure that payment method is there and return it.
   *
   * @return string
   */
  public function getPaymentMethod(): string {
    $this->ensurePaymentMethod();

    return self::NAME;
  }

  /**
   * Create offline automated recurring payment method if
   * it does not exist already.
   *
   * @return void
   */
  private function ensurePaymentMethod(): void {
    if ($this->PaymentMethodExists()) {
      return;
    }

    $result = civicrm_api3('OptionValue', 'create', [
      'option_group_id' => 'payment_instrument',
      'name' => self::NAME,
      'label' => self::NAME,
      'description' => 'A payment method used by Membership Extras extension manual payment processor. This is created as the payment method is a required field on installing a payment processor and can be changed on the payment processor if not needed.',
      'is_active' => 1,
      'is_default' => 0,
      'is_reserved' => 0,
      'weight' => 1,
    ]);

    $paymentMethodId = $result['id'];
    $financialAccountId = $this->getFinancialAccountForPaymentMethod();
    if ($financialAccountId === 0) {
      throw new Exception('Could not find a financial account to use with payment method for manual payment processor.');
    }

    EntityFinancialAccount::create(FALSE)
      ->addValue('entity_table', 'civicrm_option_value')
      ->addValue('entity_id', $paymentMethodId)
      ->addValue('financial_account_id', $financialAccountId)
      ->addValue('account_relationship:name', 'Asset Account is')
      ->execute();
  }

  /**
   * Checks if offline automated recurring payment method exists.
   *
   * @return bool
   */
  private function PaymentMethodExists(): bool {
    $paymentMethod = civicrm_api3('OptionValue', 'get', [
      'option_group_id' => 'payment_instrument',
      'name' => self::NAME,
      'sequential' => 1,
    ]);

    return !empty($paymentMethod['id']);
  }

  /**
   * Returns the financial account id to be used with offline automated recurring payment method.
   *
   * @return int
   */
  private function getFinancialAccountForPaymentMethod(): int {
    $financialAccountId = 0;
    $assetAccountType = CRM_Core_PseudoConstant::accountOptionValues('financial_account_type', NULL, " AND v.name = 'Asset' ");
    if (empty($assetAccountType)) {
      return $financialAccountId;
    }

    $financialAccounts = civicrm_api3('FinancialAccount', 'get', [
      'sequential' => 1,
      'financial_account_type_id' => key($assetAccountType),
    ]);
    if (empty($financialAccounts['values'])) {
      return $financialAccountId;
    }

    foreach ($financialAccounts['values'] as $financialAccount) {
      if ((int) $financialAccount['is_default'] === 1) {
        $financialAccountId = $financialAccount['id'];
        break;
      }
    }

    return (int) ($financialAccountId > 0 ? $financialAccountId : $financialAccounts['values'][0]['id']);
  }

}
