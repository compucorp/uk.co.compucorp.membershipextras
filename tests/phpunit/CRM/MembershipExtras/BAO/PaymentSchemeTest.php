<?php

/**
 * CRM_MembershipExtras_BAO_PaymentSchemeTest
 *
 * @group headless
 */
class CRM_MembershipExtras_BAO_PaymentSchemeTest extends BaseHeadlessTest {

  public function testCreatePaymentScheme() {
    $paymentProcessor = civicrm_api3('PaymentProcessor', 'create', [
      'payment_processor_type_id' => "Dummy",
      'financial_account_id' => "Accounts Receivable",
    ]);
    $params = [
      'name' => 'Test scheme',
      'admin_title' => 'Admin title',
      'description' => 'description',
      'public_title' => 'Public value',
      'public_description' => 'Public description',
      'permission' => 'public',
      'enabled' => TRUE,
      'parameters' => '{"name":"John", "age":30, "car":null}',
      'payment_processor' => $paymentProcessor["id"],
    ];

    $scheme = CRM_MembershipExtras_BAO_PaymentScheme::create($params);

    $this->assertNotNull($scheme);
    $this->assertTrue(!empty($scheme->id));
    $this->assertEquals('Test scheme', $scheme->name);
  }

  public function testGetAll() {
    $paymentProcessor = civicrm_api3('PaymentProcessor', 'create', [
      'payment_processor_type_id' => "Dummy",
      'financial_account_id' => "Accounts Receivable",
    ]);

    $param = [
      'admin_title' => 'Admin title',
      'description' => 'description',
      'public_title' => 'Public value',
      'public_description' => 'Public description',
      'permission' => 'public',
      'enabled' => TRUE,
      'parameters' => '{"name":"John", "age":30, "car":null}',
      'payment_processor' => $paymentProcessor["id"],
    ];

    for ($i = 0; $i < 5; $i++) {
      $param['name'] = 'Test' . $i;
      CRM_MembershipExtras_BAO_PaymentScheme::create($param);
    }

    $schemes = CRM_MembershipExtras_BAO_PaymentScheme::getAll();
    $this->assertEquals(5, count($schemes));
  }

}
