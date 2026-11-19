<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;

/**
 * Correcting payment plans saved with status 0 (CIVIMM-593).
 *
 * @group headless
 */
class CRM_MembershipExtras_Upgrader_Steps_Step0014Test extends BaseHeadlessTest {

  public function testMonthlyPlanWithAPaidInstalmentBecomesInProgress() {
    $plan = $this->createPlanAtStatusZero('Monthly', 1);

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $this->assertEquals('In Progress', $this->getPlanStatus($plan['id']));
  }

  public function testMonthlyPlanWithAllInstalmentsPaidBecomesCompleted() {
    $plan = $this->createPlanAtStatusZero('Monthly', 12);

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $this->assertEquals('Completed', $this->getPlanStatus($plan['id']));
  }

  public function testPaidOneInstalmentPlanBecomesInProgress() {
    $plan = $this->createPlanAtStatusZero('Yearly', 1);

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $this->assertEquals('In Progress', $this->getPlanStatus($plan['id']));
  }

  public function testPlanWithNoPaidInstalmentBecomesPending() {
    $plan = $this->createPlanAtStatusZero('Monthly', 0);

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $this->assertEquals('Pending', $this->getPlanStatus($plan['id']));
  }

  public function testPartiallyPaidPlanBecomesInProgress() {
    $plan = $this->createPlanAtStatusZero('Monthly', 0);
    $this->setInstalmentStatuses($plan['id'], 1, 'Partially paid');

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $this->assertEquals('In Progress', $this->getPlanStatus($plan['id']));
  }

  /**
   * Only the status changes: the end date and next scheduled date stay.
   */
  public function testOnlyTheStatusChanges() {
    $plan = $this->createPlanAtStatusZero('Yearly', 1);
    CRM_Core_DAO::executeQuery("UPDATE civicrm_contribution_recur SET end_date = '2026-09-15', next_sched_contribution_date = '2027-09-15' WHERE id = %1", [1 => [$plan['id'], 'Integer']]);

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $after = CRM_Core_DAO::executeQuery('SELECT DATE(end_date) AS end_date, DATE(next_sched_contribution_date) AS next_date FROM civicrm_contribution_recur WHERE id = %1', [1 => [$plan['id'], 'Integer']])->fetchAll()[0];
    $this->assertEquals('2026-09-15', $after['end_date']);
    $this->assertEquals('2027-09-15', $after['next_date']);
  }

  public function testPlanNotAtStatusZeroIsNotChanged() {
    $plan = $this->createPlanAtStatusZero('Monthly', 1);
    $this->setPlanStatus($plan['id'], 'Cancelled');

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $this->assertEquals('Cancelled', $this->getPlanStatus($plan['id']));
  }

  public function testRecurringContributionNotLinkedToAMembershipIsNotChanged() {
    $recurID = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => civicrm_api3('Contact', 'create', ['contact_type' => 'Individual', 'first_name' => 'No', 'last_name' => 'Membership'])['id'],
      'amount' => 10,
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 12,
    ])['id'];
    CRM_Core_DAO::executeQuery('UPDATE civicrm_contribution_recur SET contribution_status_id = 0 WHERE id = %1', [1 => [$recurID, 'Integer']]);

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $this->assertEquals(0, (int) CRM_Core_DAO::singleValueQuery('SELECT contribution_status_id FROM civicrm_contribution_recur WHERE id = %1', [1 => [$recurID, 'Integer']]));
  }

  public function testRunningAgainChangesNothing() {
    $plan = $this->createPlanAtStatusZero('Monthly', 1);
    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();
    $this->setPlanStatus($plan['id'], 'Overdue');

    (new CRM_MembershipExtras_Upgrader_Steps_Step0014())->apply();

    $this->assertEquals('Overdue', $this->getPlanStatus($plan['id']));
  }

  /**
   * Creates a Pending membership payment plan, marks the first $paidCount
   * instalments Completed and saves the plan with status 0, as the bug did.
   *
   * @param string $frequency
   *   Monthly (12 instalments) or Yearly (1 instalment).
   * @param int $paidCount
   *
   * @return array
   */
  private function createPlanAtStatusZero($frequency, $paidCount) {
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Status Zero Membership ' . uniqid(),
      'period_type' => 'rolling',
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);
    $priceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $order = new PaymentPlanMembershipOrder();
    $order->membershipStartDate = date('Y-m-d');
    $order->paymentPlanFrequency = $frequency;
    $order->paymentPlanStatus = 'Pending';
    $order->lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $priceFieldValue['price_field_id'],
      'price_field_value_id' => $priceFieldValue['id'],
      'label' => $membershipType['name'],
      'qty' => 1,
      'unit_price' => $priceFieldValue['amount'],
      'line_total' => $priceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 1,
    ];
    $plan = PaymentPlanOrderFabricator::fabricate($order);

    $this->setInstalmentStatuses($plan['id'], $paidCount, 'Completed');
    CRM_Core_DAO::executeQuery('UPDATE civicrm_contribution_recur SET contribution_status_id = 0 WHERE id = %1', [1 => [$plan['id'], 'Integer']]);

    return $plan;
  }

  /**
   * Sets the status of the plan's first $count instalments, directly.
   */
  private function setInstalmentStatuses($planID, $count, $statusName) {
    if ($count < 1) {
      return;
    }
    $statusID = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_Contribution', 'contribution_status_id', $statusName);
    CRM_Core_DAO::executeQuery("
      UPDATE civicrm_contribution SET contribution_status_id = %1
      WHERE contribution_recur_id = %2
      ORDER BY receive_date, id
      LIMIT $count
    ", [1 => [$statusID, 'Integer'], 2 => [$planID, 'Integer']]);
  }

  private function setPlanStatus($planID, $statusName) {
    $statusID = CRM_Core_PseudoConstant::getKey('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', $statusName);
    CRM_Core_DAO::executeQuery('UPDATE civicrm_contribution_recur SET contribution_status_id = %1 WHERE id = %2', [1 => [$statusID, 'Integer'], 2 => [$planID, 'Integer']]);
  }

  private function getPlanStatus($planID) {
    $statusID = CRM_Core_DAO::singleValueQuery('SELECT contribution_status_id FROM civicrm_contribution_recur WHERE id = %1', [1 => [$planID, 'Integer']]);

    return (string) CRM_Core_PseudoConstant::getName('CRM_Contribute_BAO_ContributionRecur', 'contribution_status_id', $statusID);
  }

}
