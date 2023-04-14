<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentSchemePlan as PaymentSchemePlanRenewalJob;

/**
 * Class CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentSchemePlanTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Job_OfflineAutoRenewal_PaymentSchemePlanTest extends CRM_MembershipExtras_Job_OfflineAutoRenewal_BaseRenewalJobTestHelper {

  public function testWillRenewPaymentPlansLinkedToPaymentScheme() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->paymentSchemeId = $this->createPaymentScheme()->id;
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
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $paymentSchemeRenewal = new PaymentSchemePlanRenewalJob();
    $paymentSchemeRenewal->run();

    $this->assertTrue($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day', 2));
  }

  public function testWillNotRenewPaymentPlansNotLinkedToPaymentScheme() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-2 year -1 month'));
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
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $paymentSchemeRenewal = new PaymentSchemePlanRenewalJob();
    $paymentSchemeRenewal->run();

    $this->assertFalse($this->isPaymentPlanMembershipRenewed($paymentPlan['id'], '-1 month -1 day', 2));
  }

  public function testCreatedInstalmentsDatesFollowConfiguredPaymentScheme() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = '2021-01-01';
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Completed';
    $paymentPlanMembershipOrder->paymentSchemeId = $this->createPaymentScheme()->id;
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
    $paymentPlan = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);

    $paymentSchemeRenewal = new PaymentSchemePlanRenewalJob();
    $paymentSchemeRenewal->run();

    $nextPeriodID = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlan['id']);

    $newContributionsReceiveDates = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['receive_date'],
      'contribution_recur_id' => $nextPeriodID,
      'options' => ['limit' => 0, 'sort' => 'id ASC'],
    ])['values'];

    $this->assertEquals('2022-02-01', date('Y-m-d', strtotime($newContributionsReceiveDates[0]['receive_date'])));
    $this->assertEquals('2022-05-01', date('Y-m-d', strtotime($newContributionsReceiveDates[1]['receive_date'])));
  }

  private function isPaymentPlanMembershipRenewed($paymentPlanId, $expectedNewEndDateOffset, $expectedInstalmentsCount) {
    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $paymentPlanId,
    ])['values'][0];

    $contributionCount = 0;

    $nextPeriodId = $this->getTheNewRecurContributionIdFromCurrentOne($paymentPlanId);
    if (!empty($nextPeriodId)) {
      $contributionCount = civicrm_api3('Contribution', 'getcount', [
        'contribution_recur_id' => $nextPeriodId,
      ]);
    }

    if ((date('Y-m-d', strtotime($expectedNewEndDateOffset)) == $membership['end_date']) && ($contributionCount == $expectedInstalmentsCount)) {
      return TRUE;
    }

    return FALSE;
  }

}
