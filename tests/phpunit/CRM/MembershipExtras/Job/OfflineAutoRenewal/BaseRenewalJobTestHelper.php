<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringLineItem as RecurringLineItemFabricator;

/**
 * Class CRM_MembershipExtras_Job_OfflineAutoRenewal_BaseRenewalJobTestHelper
 *
 * @group headless
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_BaseRenewalJobTestHelper extends BaseHeadlessTest {

  /**
   * A rolling membership type that we
   * will use for creating payment plan orders.
   *
   * @var array
   */
  protected $testRollingMembershipType;

  /**
   * The PriceFieldValue entity for the
   * test rolling membership type.
   *
   * @var array
   */
  protected $testRollingMembershipTypePriceFieldValue;

  public function setUp() {
    $this->setDefaultPaymentPlanSettings();
    $this->createTestRollingMembershipType();
  }

  private function setDefaultPaymentPlanSettings() {
    Civi::settings()->set('membershipextras_paymentplan_days_to_renew_in_advance', 0);
    Civi::settings()->set('membershipextras_paymentplan_update_start_date_renewal', 0);
  }

  private function createTestRollingMembershipType() {
    $this->testRollingMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Rolling Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 120,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);

    $this->testRollingMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->testRollingMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];
  }

  /**
   * Gets the subscription line items
   * (along with the related line item
   * for each one) for the payment plan.
   *
   * @param int $recurringContributionID
   *
   * @return array
   */
  protected function getSubscriptionLineItems($recurringContributionID) {
    $q = '
      SELECT msl.start_date, msl.end_date, li.entity_table,
        li.entity_id, li.price_field_id, li.price_field_value_id,
        li.line_total, li.label
      FROM membershipextras_subscription_line msl
      INNER JOIN civicrm_line_item li ON msl.line_item_id = li.id
        WHERE msl.contribution_recur_id = %1
        AND li.contribution_id IS NULL
        ORDER BY msl.id ASC
      ';
    $dbResultSet = CRM_Core_DAO::executeQuery($q, [
      1 => [$recurringContributionID, 'Integer'],
    ]);

    $lineItems = [];
    while ($dbResultSet->fetch()) {
      $lineItems[] = $dbResultSet->toArray();
    }

    return $lineItems;
  }

  /**
   * Adds a new membership line item to the next period of the given payment plan.
   *
   * @param array $paymentPlan
   * @param array $membershipParams
   *
   * @throws \CiviCRM_API3_Exception
   */
  protected function addRenewableNewMembershipToNextPeriodOnly($paymentPlan, $membershipParams) {
    $membershipTypeObject = $this->createMembershipType($membershipParams);
    $membershipType = $membershipTypeObject->membershipType;
    $membershipTypePriceFieldValue = $membershipTypeObject->priceFieldValue;

    $newLineItem = LineItemFabricator::fabricate([
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id' => $paymentPlan['id'],
      'price_field_id' => $membershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $membershipTypePriceFieldValue['id'],
      'label' => $membershipType['name'],
      'qty' => 1,
      'unit_price' => $membershipTypePriceFieldValue['amount'] / $paymentPlan['installments'],
      'line_total' => $membershipTypePriceFieldValue['amount'] / $paymentPlan['installments'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ]);
    RecurringLineItemFabricator::fabricate([
      'contribution_recur_id' => $paymentPlan['id'],
      'line_item_id' => $newLineItem['id'],
      'auto_renew' => 1,
    ]);
  }

  /**
   * Returns list of contributions associated to the given payment plan ID.
   * @param int $paymentPlanID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  protected function getPaymentPlanContributions($paymentPlanID) {
    return civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanID,
      'options' => [
        'limit' => 0,
        'sort' => 'id',
      ],
    ])['values'];
  }

  /**
   * Helper function to create memberships and its default price field value.
   *
   * @param array $params
   *
   * @return \stdClass
   * @throws \CiviCRM_API3_Exception
   */
  protected function createMembershipType($params) {
    $membershipType = MembershipTypeFabricator::fabricate($params);
    $priceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $result = new stdClass();
    $result->membershipType = $membershipType;
    $result->priceFieldValue = $priceFieldValue;

    return $result;
  }

  protected function createPaymentScheme() {
    $params = [
      'name' => 'Test scheme',
      'admin_title' => 'Admin title',
      'admin_description' => 'Admin description',
      'public_title' => 'Public value',
      'public_description' => 'Public description',
      'permission' => 'public',
      'enabled' => TRUE,
      'parameters' => '{"instalments_count": 2,"instalments": [{"charge_date": "{next_period_start_date}, + 1 month"},{"charge_date": "{next_period_start_date}, + 4 months"}]}',
      'payment_processor' => 1,
    ];

    return CRM_MembershipExtras_BAO_PaymentScheme::create($params);
  }

  protected function getTheNewRecurContributionIdFromCurrentOne($currentRecurContributionId) {
    $contactId = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'return' => 'contact_id',
      'id' => $currentRecurContributionId,
    ])['values'][0]['contact_id'];

    $lastRecurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'contact_id' => $contactId,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ]);

    if (empty($lastRecurContribution['values'][0]['id'])) {
      return NULL;
    }

    $lastRecurContributionId = $lastRecurContribution['values'][0]['id'];

    if ($lastRecurContributionId != $currentRecurContributionId) {
      return $lastRecurContributionId;
    }

    return NULL;
  }

}
