<?php
use CRM_MembershipExtras_Test_Fabricator_Base as BaseFabricator;
use CRM_MembershipExtras_PaymentProcessor_OfflineRecurringContribution as OfflineRecurringContributionPaymentProcessor;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_RecurringContribution.
 *
 */
class CRM_MembershipExtras_Test_Fabricator_RecurringContribution extends BaseFabricator {

  /**
   * Entity name.
   *
   * @var string
   */
  protected static $entityName = 'ContributionRecur';

  /**
   * Default parameters to create a recurring contribution.
   *
   * @var array
   */
  protected static $defaultParams = [
    'amount' => 0,
    'frequency_unit' => 'year',
    'frequency_interval' => 1,
    'installments' => 1,
    'contribution_status_id' => 'Pending',
    'is_test' => 0,
    'auto_renew' => 1,
    'cycle_day' => 1,
    'start_date' => '2018-01-01',
  ];

  /**
   * @inheritDoc
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function getDefaultParameters() {
    self::$defaultParams['payment_processor_id'] = self::getPayLaterProcessorID();
    self::$defaultParams['financial_type_id'] = self::getMemberDuesFinancialTypeID();
    self::$defaultParams['payment_instrument_id'] = self::getEftPaymentInstrumentID();

    return parent::getDefaultParameters();
  }

  /**
   * Obtains the ID of the pay later payment processor.
   *
   * @return int
   * @throws \CiviCRM_API3_Exception
   */
  private static function getPayLaterProcessorID() {
    $result = civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'name' => OfflineRecurringContributionPaymentProcessor::NAME,
      'is_test' => '0',
      'options' => ['limit' => 1],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values'])['id'];
    }

    return 0;
  }

  /**
   * Obtains ID of 'Member Dues' Financial Type.
   *
   * @return int
   * @throws \CiviCRM_API3_Exception
   */
  private static function getMemberDuesFinancialTypeID() {
    $result = civicrm_api3('FinancialType', 'get', [
      'sequential' => 1,
      'name' => 'Member Dues',
      'options' => ['limit' => 1],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values'])['id'];
    }

    return 0;
  }

  /**
   * Obtains value for EFT payment instrument option value.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function getEftPaymentInstrumentID() {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'payment_instrument',
      'label' => 'EFT',
    ]);
  }

}
