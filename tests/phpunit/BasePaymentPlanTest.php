<?php

use CRM_MembershipExtras_PaymentProcessor_OfflineRecurringContribution as OfflineRecurringContributionPaymentProcessor;

/**
 * Class BasePaymentPlanTest.
 *
 * Abstract class holding common attributes and methods for al tests where
 * payment plans are involved.
 */
abstract class BasePaymentPlanTest extends BaseHeadlessTest {

  protected $contributionPendingStatusValue = 0;
  protected $memberDuesFinancialType = [];
  protected $eftPaymentInstrumentID = 0;
  protected $defaultMembershipsPriceSet = [];
  protected $contact;
  protected $membershipType;
  protected $recurringContributionParams = [];
  protected $lineItemsParams = [];
  protected $contributionParams = [];

  public function setUp() {
    $this->setTestParametricValues();
  }

  /**
   * Loads parameters required for the tests.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setTestParametricValues() {
    $this->contributionPendingStatusValue = $this->getPendingContributionStatusValue();
    $this->memberDuesFinancialType = $this->getMembershipDuesFinancialType();
    $this->eftPaymentInstrumentID = $this->getEFTPaymentInstrumentID();
    $this->defaultMembershipsPriceSet = $this->getDefaultPriceSet();
  }

  /**
   * Obtains value for the 'Pending' contribution status option value.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getPendingContributionStatusValue() {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  /**
   * Obtains 'Membership Dues' financial type.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  protected function getMembershipDuesFinancialType() {
    $result = civicrm_api3('FinancialType', 'get', [
      'sequential' => 1,
      'name' => 'Member Dues',
      'options' => ['limit' => 1],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Obtains value for EFT payment instrument option value.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getEFTPaymentInstrumentID() {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'payment_instrument',
      'label' => 'EFT',
    ]);
  }

  /**
   * Obtains default price set for memberships.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getDefaultPriceSet() {
    $result = civicrm_api3('PriceSet', 'get', [
      'sequential' => 1,
      'name' => 'default_membership_type_amount',
      'options' => ['limit' => 1],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Obtains default payment processor used for offline recurring contributions.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  protected function getPayLaterProcessorID() {
    $result = civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'name' => OfflineRecurringContributionPaymentProcessor::NAME,
      'is_test' => '0',
      'options' => ['limit' => 1],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Gets the default price field value for the given membership ID.
   *
   * @param int $membershipTypeID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  protected function getDefaultPriceFieldValueID($membershipTypeID) {
    $result = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'price_field_id.price_set_id.name' => 'default_membership_type_amount',
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Obtains list of contributions related to the payment plan.
   *
   * @return array|mixed
   * @throws \CiviCRM_API3_Exception
   */
  protected function getPaymentPlanContributions($recurringContributionID) {
    $contributions = $contributions = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'options' => ['limit' => 0],
    ]);

    if ($contributions['count'] > 0) {
      return $contributions['values'];
    }

    return [];
  }

}
