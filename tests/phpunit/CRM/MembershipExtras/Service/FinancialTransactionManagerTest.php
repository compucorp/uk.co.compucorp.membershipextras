<?php

/**
 * Class CRM_MembershipExtras_Service_FinancialTransactionManagerTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_FinancialTransactionManagerTest extends BaseHeadlessTest {

  public function testInsertFinancialItemOnLineItemAdditionWithVat(): void {
    $amount = 100;
    $tax = 20;
    $contact = CRM_MembershipExtras_Test_Fabricator_Contact::fabricate();
    $contribution = CRM_MembershipExtras_Test_Fabricator_Contribution::fabricate([
      'is_pay_later' => TRUE,
      'skipCleanMoney' => TRUE,
      'receive_date' => date('Y-m-d'),
      'contact_id' => $contact['id'],
      'fee_amount' => 0,
      'net_amount' => $amount,
      'total_amount' => $amount,
      'payment_instrument_id' => 'EFT',
      'financial_type_id' => 'Member Dues',
    ]);
    $lineItem = [
      'id' => 2,
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $contribution['id'],
      'contribution_id' => $contribution['id'],
      'price_field_id' => 1,
      'price_field_value_id' => 1,
      'label' => 'test',
      'qty' => 1,
      'unit_price' => $amount,
      'line_total' => $amount,
      'tax_amount' => $tax,
      'financial_type_id' => '1',
      'non_deductible_amount' => 0,
      'auto_renew' => 0,
    ];

    $financialManager = new CRM_MembershipExtras_Service_FinancialTransactionManager();
    $financialManager->insertFinancialItemOnLineItemAddition($lineItem);
    $financialTrxn = civicrm_api3('FinancialTrxn', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 1, 'sort' => 'id desc'],
    ]);

    $this->assertEquals($amount + $tax, $financialTrxn['values'][0]['total_amount']);
  }

  public function testInsertFinancialItemOnLineItemAdditionWithoutVat(): void {
    $amount = 100;
    $contact = CRM_MembershipExtras_Test_Fabricator_Contact::fabricate();
    $contribution = CRM_MembershipExtras_Test_Fabricator_Contribution::fabricate([
      'is_pay_later' => TRUE,
      'skipCleanMoney' => TRUE,
      'receive_date' => date('Y-m-d'),
      'contact_id' => $contact['id'],
      'fee_amount' => 0,
      'net_amount' => $amount,
      'total_amount' => $amount,
      'payment_instrument_id' => 'EFT',
      'financial_type_id' => 'Member Dues',
    ]);
    $lineItem = [
      'id' => 2,
      'entity_table' => 'civicrm_contribution',
      'entity_id' => $contribution['id'],
      'contribution_id' => $contribution['id'],
      'price_field_id' => 1,
      'price_field_value_id' => 1,
      'label' => 'test',
      'qty' => 1,
      'unit_price' => $amount,
      'line_total' => $amount,
      'financial_type_id' => '1',
      'non_deductible_amount' => 0,
      'auto_renew' => 0,
    ];

    $financialManager = new CRM_MembershipExtras_Service_FinancialTransactionManager();
    $financialManager->insertFinancialItemOnLineItemAddition($lineItem);
    $financialTrxn = civicrm_api3('FinancialTrxn', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 1, 'sort' => 'id desc'],
    ]);

    $this->assertEquals($amount, $financialTrxn['values'][0]['total_amount']);
  }

}
