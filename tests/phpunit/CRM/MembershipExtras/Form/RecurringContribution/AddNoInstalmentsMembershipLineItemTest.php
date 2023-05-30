<?php

use CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsMembershipLineItem as AddNoInstalmentsMembershipLineItemForm;
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;

/**
 * Class CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsMembershipLineItemTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsMembershipLineItemTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;

  private $testContact;

  private $testMembershipType;

  private $testRecurringContribution;

  public function setUp(): void {
    $this->mockSalesTaxFinancialAccount(10, 'Member Dues');

    $this->testContact = ContactFabricator::fabricate();

    $this->testMembershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Test Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 12,
      'duration_unit' => 'month',
      'financial_type_id' => 'Member Dues',
    ]);

    $this->testRecurringContribution = RecurringContributionFabricator::fabricate([
      'sequential' => 1,
      'contact_id' => $this->testContact['id'],
      'amount' => 0,
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 12,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'financial_type_id' => 'Member Dues',
      'payment_instrument_id' => 'EFT',
      'start_date' => '2023-01-01',
    ]);
  }

  public function testAddMembershipLineItemWithNoPayment(): void {
    $_REQUEST['contribution_recur_id'] = $this->testRecurringContribution['id'];
    $_REQUEST['line_item'] = [
      'start_date' => '2023-03-01',
      'end_date' => '2023-10-31',
      'auto_renew' => 1,
      'membership_type_id' => $this->testMembershipType['id'],
    ];

    $formValues = [
      'payment_type' => AddNoInstalmentsMembershipLineItemForm::PAYMENT_TYPE_NO_PAYMENT,
      'datepicker' => '2023-05-01',
      'noinstalmentline_financial_type_id' => 2,
      'amount_exc_tax' => 100,
      'amount_inc_tax' => 110,
      'noinstalmentline_send_confirmation_email' => 0,
    ];
    $this->submitForm($formValues);

    $this->validateMembershipData();
    $this->validateRecurContributionLineData(FALSE);
    $this->validateContribution(FALSE);
  }

  public function testAddMembershipLineItemWithOneOffPayment(): void {
    $_REQUEST['contribution_recur_id'] = $this->testRecurringContribution['id'];
    $_REQUEST['line_item'] = [
      'start_date' => '2023-03-01',
      'end_date' => '2023-10-31',
      'auto_renew' => 1,
      'membership_type_id' => $this->testMembershipType['id'],
    ];

    $formValues = [
      'payment_type' => AddNoInstalmentsMembershipLineItemForm::PAYMENT_TYPE_ONE_OFF_PAYMENT,
      'datepicker' => '2023-05-01',
      'noinstalmentline_financial_type_id' => 2,
      'amount_exc_tax' => 100,
      'amount_inc_tax' => 110,
      'noinstalmentline_send_confirmation_email' => 0,
    ];
    $this->submitForm($formValues);

    $this->validateMembershipData();
    $this->validateRecurContributionLineData(TRUE);
    $this->validateContribution(TRUE);
  }

  private function submitForm($formValues) {
    $form = new AddNoInstalmentsMembershipLineItemForm();
    $form->controller = new CRM_Core_Controller_Simple('CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsMembershipLineItem', '');
    $form->_submitValues = $formValues;

    $form->buildForm();
    $form->loadValues($formValues);
    $form->validate();

    $form->postProcess();

    return $form;
  }

  private function validateMembershipData() {
    $createdMembership = \Civi\Api4\Membership::get()
      ->addWhere('contact_id', '=', $this->testContact['id'])
      ->setLimit(1)
      ->execute()[0];
    $actualMembershipParams = [
      'join_date' => $createdMembership['join_date'],
      'start_date' => $createdMembership['start_date'],
      'end_date' => $createdMembership['end_date'],
      'contribution_recur_id' => $createdMembership['contribution_recur_id'],
      'membership_type_id' => $createdMembership['membership_type_id'],
    ];

    $expectedMembershipParams = [
      'join_date' => $_REQUEST['line_item']['start_date'],
      'start_date' => $_REQUEST['line_item']['start_date'],
      'end_date' => $_REQUEST['line_item']['end_date'],
      'contribution_recur_id' => $this->testRecurringContribution['id'],
      'membership_type_id' => $_REQUEST['line_item']['membership_type_id'],
    ];

    $this->assertEquals($expectedMembershipParams, $actualMembershipParams);
  }

  private function validateRecurContributionLineData($withPayment) {
    $recurLineItem = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->testRecurringContribution['id'],
    ])['values'][0];

    $lineItem = \Civi\Api4\LineItem::get()
      ->addWhere('id', '=', $recurLineItem['line_item_id'])
      ->execute()[0];

    $actualParams = [
      'start_date' => date('Y-m-d', strtotime($recurLineItem['start_date'])) ,
      'entity_table' => $lineItem['entity_table'],
      'unit_price' => $lineItem['unit_price'],
      'line_total' => $lineItem['line_total'],
      'tax_amount' => $lineItem['tax_amount'],
    ];

    $expectedParams = [
      'start_date' => $_REQUEST['line_item']['start_date'],
      'entity_table' => 'civicrm_membership',
      'unit_price' => $withPayment ? 100 : 0,
      'line_total' => $withPayment ? 100 : 0,
      'tax_amount' => $withPayment ? 10 : 0,
    ];

    $this->assertEquals($expectedParams, $actualParams);
  }

  private function validateContribution($isOnOffPayment) {
    $contribution = \Civi\Api4\Contribution::get()
      ->addWhere('contact_id', '=', $this->testContact['id'])
      ->execute();

    if (!$isOnOffPayment) {
      $this->assertEmpty($contribution);
      return;
    }

    $contribution = $contribution[0];
    $actualParams = [
      'total_amount' => $contribution['total_amount'],
      'tax_amount' => $contribution['tax_amount'],
      'contribution_recur_id' => $contribution['contribution_recur_id'],
    ];

    $expectedParams = [
      'total_amount' => 110,
      'tax_amount' => 10,
      'contribution_recur_id' => $this->testRecurringContribution['id'],
    ];

    $this->assertEquals($expectedParams, $actualParams);
  }

}
