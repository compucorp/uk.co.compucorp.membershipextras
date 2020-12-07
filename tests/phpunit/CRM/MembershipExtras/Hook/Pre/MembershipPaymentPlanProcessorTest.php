<?php
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;

/**
 * Class CRM_MembershpExtras_Hook_Pre_MembershipPaymentPlanProcessorTest
 *
 * @group headless
 */
class CRM_MembershpExtras_Hook_Pre_MembershipPaymentPlanProcessorTest extends BaseHeadlessTest {

  public function testMonthlyCycleDayIsCalculatedFromReceiveDate() {
    $_REQUEST['installments'] = 12;
    $_REQUEST['installments_frequency'] = 1;
    $_REQUEST['installments_frequency_unit'] = 'month';

    $contact = ContactFabricator::fabricate();
    $params = [
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => '2020-06-27',
      'contact_id' => $contact['id'],
      'fee_amount' => 0,
      'net_amount' => "1200",
      'total_amount' => "1200",
      'payment_instrument_id' => $this->getOptionValue('EFT', 'payment_instrument'),
      'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      'contribution_status_id' => 'Pending',
      'currency' => NULL,
      'is_test' => FALSE,
      'campaign_id' => NULL,
    ];
    $paymentPlanCreator = new CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor($params);
    $paymentPlanCreator->createPaymentPlan();
    $recurringContribution = $paymentPlanCreator->getRecurringContribution();
    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContribution['id'],
      'options' => ['limit' => 0],
    ])['values'][0];

    $this->assertEquals('27', $recurringContribution['cycle_day']);
  }

  public function testYearlyCycleDayIsCalculatedFromReceiveDate() {
    $_REQUEST['installments'] = 1;
    $_REQUEST['installments_frequency'] = 1;
    $_REQUEST['installments_frequency_unit'] = 'year';

    $contact = ContactFabricator::fabricate();
    $params = [
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => '2020-02-01',
      'contact_id' => $contact['id'],
      'fee_amount' => 0,
      'net_amount' => "1200",
      'total_amount' => "1200",
      'payment_instrument_id' => $this->getOptionValue('EFT', 'payment_instrument'),
      'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      'contribution_status_id' => 'Pending',
      'currency' => NULL,
      'is_test' => FALSE,
      'campaign_id' => NULL,
    ];
    $paymentPlanCreator = new CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor($params);
    $paymentPlanCreator->createPaymentPlan();
    $recurringContribution = $paymentPlanCreator->getRecurringContribution();
    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContribution['id'],
      'options' => ['limit' => 0],
    ])['values'][0];

    $this->assertEquals('32', $recurringContribution['cycle_day']);
  }

  public function testReceiveDateCalculationHookChangesReceiveDate() {
    $_REQUEST['installments'] = 12;
    $_REQUEST['installments_frequency'] = 1;
    $_REQUEST['installments_frequency_unit'] = 'month';

    $contact = ContactFabricator::fabricate();
    $params = [
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => '2020-01-01',
      'contact_id' => $contact['id'],
      'fee_amount' => 0,
      'net_amount' => "1200",
      'total_amount' => "1200",
      'payment_instrument_id' => $this->getOptionValue('EFT', 'payment_instrument'),
      'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      'contribution_status_id' => 'Pending',
      'currency' => NULL,
      'is_test' => FALSE,
      'campaign_id' => NULL,
      'test_receive_date_calculation_hook' => '2020-06-27',
    ];
    $paymentPlanCreator = new CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor($params);
    $paymentPlanCreator->createPaymentPlan();
    $recurringContribution = $paymentPlanCreator->getRecurringContribution();
    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContribution['id'],
      'options' => ['limit' => 0],
    ])['values'][0];

    $this->assertEquals('2020-06-27 00:00:00', $recurringContribution['start_date']);
    $this->assertEquals('27', $recurringContribution['cycle_day']);
  }

  /**
   * Obtains value for the given name option in the option group.
   *
   * @param string $name
   * @param string $group
   *
   * @return array|string
   * @throws \CiviCRM_API3_Exception
   */
  private function getOptionValue($name, $group) {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => $group,
      'name' => $name,
    ]);
  }

  /**
   * Obtains ID for the given financial type name.
   *
   * @param $financialType
   *
   * @return int|array
   * @throws \CiviCRM_API3_Exception
   */
  private function getFinancialTypeID($financialType) {
    return civicrm_api3('FinancialType', 'getvalue', [
      'return' => 'id',
      'name' => $financialType,
    ]);
  }

}
