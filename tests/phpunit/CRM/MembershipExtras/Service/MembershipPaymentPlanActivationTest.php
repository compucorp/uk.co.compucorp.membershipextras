<?php

use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;

/**
 * @group headless
 */
class CRM_MembershipExtras_Service_MembershipPaymentPlanActivationTest extends BaseHeadlessTest {

  private $testRollingMembershipType;

  private $testRollingMembershipTypePriceFieldValue;

  public function setUp() {
    $this->createTestRollingMembershipType();
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

  public function testThatOnlyLastRecurringContributionRelatedToMembershipWillGetActivated() {
    $contactId = ContactFabricator::fabricate()['id'];
    $this->createPaymentPlanRenewalSequence($contactId, 3);

    $membershipId = $this->getContactMembershipId($contactId);
    $paymentPlanActivationService = new CRM_MembershipExtras_Service_MembershipPaymentPlanActivation();
    $paymentPlanActivationService->activateMembershipCurrentPaymentPlan($membershipId);

    $paymentPlans = $this->getMembershipRelatedPaymentPlansWithTheirActiveStatus($membershipId);
    $this->assertEquals(0, $paymentPlans[0]['is_active']);
    $this->assertEquals(0, $paymentPlans[1]['is_active']);
    $this->assertEquals(1, $paymentPlans[2]['is_active']);
  }

  private function createPaymentPlanRenewalSequence($contactId, $renewalTermsCount) {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d', strtotime('-1 year -1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    $paymentPlanMembershipOrder->contactId = $contactId;
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

    for ($i = 0; $i < $renewalTermsCount; $i++) {
      PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
    }
  }

  private function getContactMembershipId($contactId) {
    return civicrm_api3('Membership', 'get', [
      'sequential'   => 1,
      'contact_id' => $contactId,
    ])['values'][0]['id'];
  }

  private function getMembershipRelatedPaymentPlansWithTheirActiveStatus($membershipId) {
    $query = "
      SELECT cc.contribution_recur_id as id, ppea.is_active as is_active FROM civicrm_membership cm 
      INNER JOIN civicrm_membership_payment cmp ON cm.id = cmp.membership_id
      INNER JOIN civicrm_contribution cc ON cmp.contribution_id = cc.id  
      LEFT JOIN civicrm_value_payment_plan_extra_attributes ppea ON cc.contribution_recur_id = ppea.entity_id 
      WHERE cm.id = {$membershipId} 
      GROUP BY cc.contribution_recur_id 
      ORDER BY cc.contribution_recur_id ASC 
    ";
    $recurContributions = CRM_Core_DAO::executeQuery($query);

    $recurContributionsList = [];
    while ($recurContributions->fetch()) {
      $recurContribution['id'] = $recurContributions->id;
      $recurContribution['is_active'] = !empty($recurContributions->is_active) ? 1 : 0;
      $recurContributionsList[] = $recurContribution;
    }

    return $recurContributionsList;
  }

}
