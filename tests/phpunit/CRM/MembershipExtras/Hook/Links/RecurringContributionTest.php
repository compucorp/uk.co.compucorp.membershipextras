<?php

use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Hook_Links_RecurringContribution as RecurringContributionHook;
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;

/**
 * Class CRM_MembershipExtras_Hook_Post_MembershipPaymentTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_Links_RecurringContributionTest extends BaseHeadlessTest {

  /**
   * Links to be altered by the hook.
   *
   * @var array
   */
  private $links = [];

  public function setUp() {
    $this->links = [
      ['name' => 'Cancel', 'ref' => '', 'url' => ''],
    ];

    $this->setupNonManualPaymentProcessor();
  }

  public function testCancelLinkNotAlteredForNonManualPaymentPlan() {
    $mask = CRM_Core_Action::UPDATE;
    $recurringContribution = $this->createNonManualPaymentPlan();
    $hook = new RecurringContributionHook($recurringContribution['id'], $this->links, $mask);
    $hook->alterLinks();

    $this->assertEquals('', $this->links[0]['url']);
  }

  public function testCancelLinkAlteredForManualPaymentPlan() {
    $mask = CRM_Core_Action::UPDATE;
    $recurringContribution = $this->createManualPaymentPlan();
    $hook = new RecurringContributionHook($recurringContribution['id'], $this->links, $mask);
    $hook->alterLinks();

    $this->assertEquals('civicrm/recurring-contribution/cancel', $this->links[0]['url']);
  }

  /**
   * A helper funcitons that configures a non-manual payment plan.
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  private function createNonManualPaymentPlan($params = []) {
    $contact = ContactFabricator::fabricate();
    $params = array_merge([
      'sequential' => 1,
      'contact_id' => $contact['id'],
      'amount' => 0,
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 12,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => 'Not Manual',
      'financial_type_id' => 'Member Dues',
      'payment_instrument_id' => 'EFT',
      'start_date' => date('Y-m-d'),
    ], $params);

    $recurringContribution = RecurringContributionFabricator::fabricate($params);

    return $recurringContribution;
  }

  /**
   * A helper funcitons that configures a manual payment plan.
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  private function createManualPaymentPlan() {
    $testMembershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Rolling Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 120,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);

    $testMembershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $testMembershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $paymentPlanEntity = new CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder();
    $paymentPlanEntity->membershipStartDate = '2020-01-15';
    $paymentPlanEntity->paymentPlanStartDate = '2020-01-15 00:00:00';
    $paymentPlanEntity->paymentMethod = 'Cash';
    $paymentPlanEntity->paymentPlanFrequency = 'Monthly';
    $paymentPlanEntity->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $testMembershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $testMembershipTypePriceFieldValue['id'],
      'label' => $testMembershipType['name'],
      'qty' => 1,
      'unit_price' => $testMembershipTypePriceFieldValue['amount'],
      'line_total' => $testMembershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
    ];

    return PaymentPlanFabricator::fabricate($paymentPlanEntity);
  }

  /**
   * A helper funcitons that creates non-manual Payment processor.
   */
  private function setupNonManualPaymentProcessor() {
    $params = [
      'name' => 'Not Manual',
      'payment_processor_type_id' => 'Dummy',
      'class_name' => 'Payment_Dummy',
      "user_name" => "Hello",
      "url_site" => "http://google.com",
      "url_recur" => "http://google.com",
      'is_recur' => 1,
      'domain_id' => 1,
      'is_test' => 0,
    ];

    $paramsTest = array_merge($params, ['is_test' => 1]);

    try {
      civicrm_api3('PaymentProcessor', 'create', $params);
      civicrm_api3('PaymentProcessor', 'create', $paramsTest);
    }
    catch (CiviCRM_API3_Exception $e) {
    }
  }

}
