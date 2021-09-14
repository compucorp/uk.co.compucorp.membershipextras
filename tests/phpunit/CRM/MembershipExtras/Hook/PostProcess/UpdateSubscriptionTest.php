<?php

/**
 * Class CRM_MembershipExtras_Hook_PostProcess_UpdateSubscriptionTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_PostProcess_UpdateSubscriptionTest extends BaseHeadlessTest {

  private $eftPaymentInstrumentID = 0;
  private $contributionPendingStatusValue = 0;

  /**
   * The form used to update recurring contributions.
   *
   * @var CRM_Contribute_Form_UpdateSubscription
   */
  private $updateSubscriptionForm;

  public function setUp() {
    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_days_to_renew_in_advance' => 0,
    ]);

    civicrm_api3('Setting', 'create', [
      'membershipextras_paymentplan_update_start_date_renewal' => 0,
    ]);

    $this->eftPaymentInstrumentID = $this->getEFTPaymentInstrumentID();
    $this->contributionPendingStatusValue = $this->getPendingContributionStatusValue();

    $this->testRollingMembershipType = CRM_MembershipExtras_Test_Fabricator_MembershipType::fabricate(
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

    $this->setUpUpdateSubscriptionForm();
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
   * Sets up the update recurring contribution form.
   */
  private function setUpUpdateSubscriptionForm() {
    $controller = new CRM_Core_Controller();
    $this->updateSubscriptionForm = new CRM_Contribute_Form_UpdateSubscription();
    $this->updateSubscriptionForm->controller = $controller;
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

  public function testUpdatingCycleDayUpdatesReceiveDatesOfContributionsInFuture() {
    $paymentPlanMembershipOrder = new CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-01', strtotime('-6 months'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
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
    $paymentPlan = CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder::fabricate($paymentPlanMembershipOrder);

    $installmentsBeforeUpdating = $this->getPaymentPlanInstallments($paymentPlan['id']);
    $this->assertEquals(12, count($installmentsBeforeUpdating));

    $newCycleDay = 15;
    $this->simulateUpdateCycleDayWithForm($paymentPlan, $newCycleDay);

    $updateHook = new CRM_MembershipExtras_Hook_PostProcess_UpdateSubscription($this->updateSubscriptionForm);
    $updateHook->postProcess();

    $i = 1;
    foreach ($installmentsBeforeUpdating as $installment) {
      $this->assertInstallmentReceiveDateIsOK($installment, $newCycleDay, $i);
      $i++;
    }
  }

  /**
   * Updates the payment plan to the given cycle day, setting up the form.
   *
   * @param $paymentPlan
   * @param $newCycleDay
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function simulateUpdateCycleDayWithForm($paymentPlan, $newCycleDay) {
    $this->updateSubscriptionForm->set('crid', $paymentPlan['id']);
    $this->updateSubscriptionForm->buildForm();
    $this->updateSubscriptionForm->set('update_installments', 1);
    $this->updateSubscriptionForm->set('auto_renew', 1);
    $this->updateSubscriptionForm->set('old_cycle_day', 1);
    $this->updateSubscriptionForm->set('old_payment_instrument_id', $this->eftPaymentInstrumentID);
    $this->updateSubscriptionForm->setVar('_submitValues', [
      'old_cycle_day' => 1,
      'cycle_day' => $newCycleDay,
      'auto_renew' => 1,
      'payment_instrument_id' => $this->eftPaymentInstrumentID,
    ]);

    $this->updateRecurringContributionCycleDay($paymentPlan['id'], $newCycleDay);
  }

  public function testOnlyPendingContributionsChangeReceiveDateOnCycleDayUpdate() {
    $paymentPlanMembershipOrder = new CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-01', strtotime('+1 month'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
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
    $paymentPlan = CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder::fabricate($paymentPlanMembershipOrder);

    $installmentsBeforeUpdating = $this->getPaymentPlanInstallments($paymentPlan['id']);
    $this->changeContributionStatusToCompleted($installmentsBeforeUpdating[0]['id']);
    $this->changeContributionStatusToCompleted($installmentsBeforeUpdating[1]['id']);
    $this->changeContributionStatusToCompleted($installmentsBeforeUpdating[2]['id']);

    $newCycleDay = 15;
    $this->simulateUpdateCycleDayWithForm($paymentPlan, $newCycleDay);

    $updateHook = new CRM_MembershipExtras_Hook_PostProcess_UpdateSubscription($this->updateSubscriptionForm);
    $updateHook->postProcess();

    $i = 1;
    foreach ($installmentsBeforeUpdating as $installment) {
      $this->assertInstallmentReceiveDateIsOK($installment, $newCycleDay, $i);
      $i++;
    }
  }

  public function testChangingCycleDayForMonthlyPaymentPlanWillAdjustNextScheduledContributionDateToThatDay() {
    $paymentPlanMembershipOrder = new CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = '2019-01-15';
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Monthly';
    $paymentPlanMembershipOrder->paymentPlanStatus = 'Pending';
    $paymentPlanMembershipOrder->nextContributionDate = '2019-01-15';
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
    $paymentPlan = CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder::fabricate($paymentPlanMembershipOrder);

    $newCycleDay = 3;
    $this->simulateUpdateCycleDayWithForm($paymentPlan, $newCycleDay);

    $updateHook = new CRM_MembershipExtras_Hook_PostProcess_UpdateSubscription($this->updateSubscriptionForm);
    $updateHook->postProcess();

    $nextContributionDate = civicrm_api3('ContributionRecur', 'getvalue', [
      'return' => 'next_sched_contribution_date',
      'id' => $paymentPlan['id'],
    ]);

    $this->assertEquals('2019-01-03 00:00:00', $nextContributionDate);
  }

  /**
   * Completes the given installment.
   *
   * @param $installmentID
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function changeContributionStatusToCompleted($installmentID) {
    civicrm_api3('Contribution', 'create', [
      'sequential' => 1,
      'id' => $installmentID,
      'contribution_status_id' => 'Completed',
      'options' => ['limit' => 0],
    ]);
  }

  /**
   * Updates cycle day for recurring contribution.
   *
   * @param $recurringContributionID
   * @param $cycleDay
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function updateRecurringContributionCycleDay($recurringContributionID, $cycleDay) {
    civicrm_api3('ContributionRecur', 'create', [
      'sequential' => 1,
      'id' => $recurringContributionID,
      'cycle_day' => $cycleDay,
    ]);
  }

  /**
   * Checks the date for the installment follows expected business logic.
   *
   * @param array $installmentBeforeUpdate
   * @param int $newCycleDay
   * @param int $nth
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function assertInstallmentReceiveDateIsOK($installmentBeforeUpdate, $newCycleDay, $nth) {
    $installmentAfterUpdate = civicrm_api3('Contribution', 'getsingle', [
      'id' => $installmentBeforeUpdate['id'],
    ]);

    $now = new DateTime(date('Y-m-d 00:00:00'));
    $originalReceiveDate = new DateTime($installmentBeforeUpdate['receive_date']);
    $newReceiveDate = new DateTime($installmentAfterUpdate['receive_date']);

    if ($originalReceiveDate >= $now && $installmentAfterUpdate['contribution_status_id'] === $this->contributionPendingStatusValue) {
      $this->assertEquals(
        $originalReceiveDate->format('Y-m-') . $newCycleDay,
        $newReceiveDate->format('Y-m-d'),
        "Installment $nth did not get updated! Original date: {$originalReceiveDate->format('Y-m-d')} / Current Date: {$newReceiveDate->format('Y-m-d')}"
      );
    }
    else {
      $this->assertEquals(
        $installmentBeforeUpdate['receive_date'],
        $installmentAfterUpdate['receive_date'],
        "Installment $nth changed receive_date and it should not have! Original receive date: {$originalReceiveDate->format('Y-m-d')} / Current Date: {$newReceiveDate->format('Y-m-d')}"
      );
    }
  }

  /**
   * Obtains installments for the recurring contribution.
   *
   * @param int $recurringContributionID
   *
   * @return array|mixed
   * @throws \CiviCRM_API3_Exception
   */
  private function getPaymentPlanInstallments($recurringContributionID) {
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'options' => ['limit' => 0, 'sort' => 'id ASC'],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return [];
  }

}
