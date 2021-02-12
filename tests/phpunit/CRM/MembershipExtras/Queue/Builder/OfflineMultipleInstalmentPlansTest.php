<?php

use CRM_MembershipExtras_Queue_TestCase as QueueTestCase;
use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Queue_Builder_OfflineMultipleInstalmentPlans as OfflineMultipleInstalmentPlansQueueBuilder;

/**
 * Class CRM_MembershipExtras_Queue_Builder_MultipleInstalmentPlansTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Queue_Builder_MultipleInstalmentPlansTest extends QueueTestCase {

  /**
   * A rolling membership type that we
   * will use for creating payment plan orders.
   *
   * @var array
   */
  private $testRollingMembershipType;

  /**
   * The PriceFieldValue entity for the
   * test rolling membership type.
   *
   * @var array
   */
  private $testRollingMembershipTypePriceFieldValue;

  public function setUp() {
    parent::setUp();
    $this->setDefaultPaymentPlanSettings();
    $this->createTestRollingMembershipType();
  }

  private function setDefaultPaymentPlanSettings() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_days_to_renew_in_advance' => 0,
    ]);

    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_update_start_date_renewal' => 0,
    ]);
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

  public function testBuildQueueWillAddTasksToQueue() {
    $NumberOfPaymentPlans = 12;
    for ($i = 0; $i < $NumberOfPaymentPlans; $i++) {
      $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
      $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year +1 day'));
      $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
      $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
      $paymentPlanMembershipOrder->lineItems[] = [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $this->testRollingMembershipTypePriceFieldValue['price_field_id'],
        'price_field_value_id' => $this->testRollingMembershipTypePriceFieldValue['id'],
        'label' => $this->testRollingMembershipType['name'],
        'qty' => 1,
        'unit_price' => $this->testRollingMembershipTypePriceFieldValue['amount'],
        'line_total' => $this->testRollingMembershipTypePriceFieldValue['amount'],
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
      ];
      PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
    }

    $this->runQueueBuilder(OfflineMultipleInstalmentPlansQueueBuilder::class);

    $expectedNumberOfTasks = ceil($NumberOfPaymentPlans / CRM_MembershipExtras_Queue_Builder_Base::RECORDS_LIMIT);
    $this->assertEquals($expectedNumberOfTasks, $this->getNumberOfTasks());
  }

}
