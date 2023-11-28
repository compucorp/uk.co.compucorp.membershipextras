<?php

use CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsDonationLineItem as AddNoInstalmentsDonationLineItemForm;
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;

/**
 * Class CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsDonationLineItemTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsDonationLineItemTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;

  private $testContact;

  private $testRecurringContribution;

  public function setUp(): void {
    $this->mockSalesTaxFinancialAccount(10, 'Member Dues');

    $this->testContact = ContactFabricator::fabricate();

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

  public function testAddDonationLineItemWithOneOffPayment(): void {
    $_REQUEST['contribution_recur_id'] = $this->testRecurringContribution['id'];
    $_REQUEST['line_item'] = [
      'start_date' => '2023-03-01',
      'auto_renew' => 1,
      'item' => 'Test Line 1',
      'amount' => 25,
      'financial_type_id' => 2,
    ];

    $formValues = [
      'noinstalmentline_send_confirmation_email' => 0,
    ];
    $this->submitForm($formValues);

    $this->validateRecurContributionLineData();
    $this->validateContribution();
  }

  private function submitForm($formValues) {
    $form = new AddNoInstalmentsDonationLineItemForm();
    $form->controller = new CRM_Core_Controller_Simple('CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsMembershipLineItem', '');
    $form->_submitValues = $formValues;

    $form->buildForm();
    $form->loadValues($formValues);
    $form->validate();

    $form->postProcess();

    return $form;
  }

  private function validateRecurContributionLineData() {
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
      'entity_table' => 'civicrm_contribution_recur',
      'unit_price' => 25,
      'line_total' => 25,
      'tax_amount' => 2.5,
    ];

    $this->assertEquals($expectedParams, $actualParams);
  }

  private function validateContribution() {
    $contribution = \Civi\Api4\Contribution::get()
      ->addWhere('contact_id', '=', $this->testContact['id'])
      ->execute();
    $contribution = $contribution[0];
    $actualParams = [
      'total_amount' => $contribution['total_amount'],
      'tax_amount' => $contribution['tax_amount'],
      'contribution_recur_id' => $contribution['contribution_recur_id'],
    ];

    $expectedParams = [
      'total_amount' => 27.5,
      'tax_amount' => 2.5,
      'contribution_recur_id' => $this->testRecurringContribution['id'],
    ];

    $this->assertEquals($expectedParams, $actualParams);
  }

}
