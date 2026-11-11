<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Job_OfflineAutoRenewal_SingleInstalmentPlan as SingleInstalmentRenewalJob;

/**
 * A payment recorded through Payment.create or Contribution.completetransaction
 * adds a membership term only when the membership doesn't have that term yet.
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_Pre_MembershipEditPaymentTermTest extends CRM_MembershipExtras_Job_OfflineAutoRenewal_BaseRenewalJobTestHelper {

  public function paymentApiProvider() {
    return [
      'Contribution.completetransaction' => ['completetransaction'],
      'Payment.create' => ['payment'],
    ];
  }

  /**
   * @dataProvider paymentApiProvider
   */
  public function testInstalmentPaymentKeepsEndDate($paymentApi) {
    $paymentPlan = $this->createPlan('Monthly', 'Pending', date('Y-m-d'));
    $membershipBefore = $this->getPlanMembership($paymentPlan['id']);
    $contributions = $this->getPaymentPlanContributions($paymentPlan['id']);

    $this->pay($contributions[0], $paymentApi);
    $this->pay($contributions[1], $paymentApi);

    $this->assertEquals($membershipBefore['end_date'], $this->getMembership($membershipBefore['id'])['end_date']);
  }

  /**
   * @dataProvider paymentApiProvider
   */
  public function testPaymentForTermAddedByAutoRenewalKeepsEndDate($paymentApi) {
    $paymentPlan = $this->createPlan('Yearly', 'Completed', date('Y-m-d', strtotime('-1 year +1 day')));
    (new SingleInstalmentRenewalJob())->run();
    $membershipAfterRenewal = $this->getPlanMembership($paymentPlan['id']);
    $renewalContribution = $this->getPendingContributions($paymentPlan['id'])[0];

    $this->pay($renewalContribution, $paymentApi);

    $this->assertEquals(date('Y-m-d', strtotime('+1 year')), $membershipAfterRenewal['end_date']);
    $this->assertEquals($membershipAfterRenewal['end_date'], $this->getMembership($membershipAfterRenewal['id'])['end_date']);
  }

  /**
   * @dataProvider paymentApiProvider
   */
  public function testFirstPaymentOfNewRenewalPlanExtendsMembership($paymentApi) {
    $oldPlan = $this->createPlan('Yearly', 'Completed', date('Y-m-d', strtotime('-1 year -14 days')));
    $membership = $this->getPlanMembership($oldPlan['id']);
    $renewalPlan = $this->createPlan('Yearly', 'Pending', date('Y-m-d'), $membership['contact_id']);

    $this->pay($this->getPaymentPlanContributions($renewalPlan['id'])[0], $paymentApi);

    $this->assertEquals(
      date('Y-m-d', strtotime($membership['end_date'] . ' +1 year')),
      $this->getMembership($membership['id'])['end_date']
    );
  }

  /**
   * @dataProvider paymentApiProvider
   */
  public function testRenewalPaymentsInOneRequestEachExtendTheirMembership($paymentApi) {
    $memberships = [];
    $renewalContributions = [];
    foreach (['Type A', 'Type B'] as $typeName) {
      $type = $this->createMembershipType([
        'name' => $typeName,
        'period_type' => 'rolling',
        'minimum_fee' => 120,
        'duration_interval' => 1,
        'duration_unit' => 'year',
      ]);
      $oldPlan = $this->createPlan('Yearly', 'Completed', date('Y-m-d', strtotime('-1 year -14 days')), NULL, $type);
      $membership = $this->getPlanMembership($oldPlan['id']);
      $renewalPlan = $this->createPlan('Yearly', 'Pending', date('Y-m-d'), $membership['contact_id'], $type);
      $memberships[] = $membership;
      $renewalContributions[] = $this->getPaymentPlanContributions($renewalPlan['id'])[0];
    }

    foreach ($renewalContributions as $contribution) {
      $this->pay($contribution, $paymentApi);
    }

    foreach ($memberships as $membership) {
      $this->assertEquals(
        date('Y-m-d', strtotime($membership['end_date'] . ' +1 year')),
        $this->getMembership($membership['id'])['end_date']
      );
    }
  }

  private function createPlan($frequency, $status, $startDate, $contactId = NULL, $type = NULL) {
    $membershipType = $type ? $type->membershipType : $this->testRollingMembershipType;
    $priceFieldValue = $type ? $type->priceFieldValue : $this->testRollingMembershipTypePriceFieldValue;

    $order = new PaymentPlanMembershipOrder();
    $order->contactId = $contactId;
    $order->membershipStartDate = $startDate;
    $order->paymentPlanFrequency = $frequency;
    $order->paymentPlanStatus = $status;
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

    return PaymentPlanOrderFabricator::fabricate($order);
  }

  private function getPlanMembership($paymentPlanId) {
    $membershipId = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id.contribution_recur_id' => $paymentPlanId,
      'entity_table' => 'civicrm_membership',
      'options' => ['limit' => 1],
    ])['values'][0]['entity_id'];

    return $this->getMembership($membershipId);
  }

  private function getMembership($membershipId) {
    return civicrm_api3('Membership', 'getsingle', ['id' => $membershipId]);
  }

  private function getPendingContributions($paymentPlanId) {
    return civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanId,
      'contribution_status_id' => 'Pending',
      'options' => ['limit' => 0, 'sort' => 'id ASC'],
    ])['values'];
  }

  private function pay($contribution, $paymentApi) {
    if ($paymentApi === 'payment') {
      civicrm_api3('Payment', 'create', [
        'contribution_id' => $contribution['id'],
        'total_amount' => $contribution['total_amount'],
        'trxn_date' => date('Y-m-d'),
      ]);

      return;
    }

    civicrm_api3('Contribution', 'completetransaction', [
      'id' => $contribution['id'],
      'trxn_id' => 'TEST-' . $contribution['id'],
      'is_transactional' => FALSE,
    ]);
  }

}
