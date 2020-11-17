<?php
use CRM_MembershipExtras_Test_Fabricator_Base as BaseFabricator;

/**
 * Class CRM_MembershipExtras_Test_Fabricator_Contribution.
 */
class CRM_MembershipExtras_Test_Fabricator_Contribution extends BaseFabricator {

  /**
   * Entity name.
   *
   * @var string
   */
  protected static $entityName = 'Contribution';

  /**
   * Default parameters to be used when creating a contribution.
   *
   * @var array
   */
  protected static $defaultParams = [
    'fee_amount' => 0,
    'net_amount' => 120,
    'total_amount' => 120,
    'receive_date' => '2018-01-01',
    'is_pay_later' => TRUE,
    'skipLineItem' => 1,
    'skipCleanMoney' => TRUE,
  ];

  /**
   * Fabricates a contribution with given parameters.
   *
   * @param array $params
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function fabricate(array $params = []) {
    $contribution = parent::fabricate($params);

    $contributionSoftParams = CRM_Utils_Array::value('soft_credit', $params);
    if (!empty($contributionSoftParams)) {
      $contributionSoftParams['contribution_id'] = $contribution['id'];
      $contributionSoftParams['currency'] = $contribution['currency'];
      $contributionSoftParams['amount'] = $contribution['total_amount'];

      CRM_Contribute_BAO_ContributionSoft::add($contributionSoftParams);
    }

    return $contribution;
  }

  /**
   * @inheritDoc
   *
   * @throws \CiviCRM_API3_Exception
   */
  public static function getDefaultParameters() {
    self::$defaultParams['payment_instrument_id'] = self::getEftPaymentInstrumentID();
    self::$defaultParams['financial_type_id'] = self::getMemberDuesFinancialTypeID();
    self::$defaultParams['contribution_status_id'] = self::getContributionPendingStatusValue();

    return parent::getDefaultParameters();
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
   * Obtains value for the 'Pending' contribution status option value.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function getContributionPendingStatusValue() {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

}
