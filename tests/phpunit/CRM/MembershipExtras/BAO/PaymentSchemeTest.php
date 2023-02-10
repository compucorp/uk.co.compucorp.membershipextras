<?php

/**
 * CRM_MembershipExtras_BAO_PaymentSchemeTest
 *
 * @group headless
 */
class CRM_MembershipExtras_BAO_PaymentSchemeTest extends BaseHeadlessTest {

  public function testCreatePaymentScheme() {
    $params = [
      'name' => 'Test scheme',
      'admin_title' => 'Admin title',
      'admin_description' => 'Admin description',
      'public_title' => 'Public value',
      'public_description' => 'Public description',
      'permission' => 'public',
      'enabled' => TRUE,
      'parameters' => '{"name":"John", "age":30, "car":null}',
    ];

    $scheme = CRM_MembershipExtras_BAO_PaymentScheme::create($params);

    $this->assertNotNull($scheme);
    $this->assertTrue(!empty($scheme->id));
    $this->assertEquals('Test scheme', $scheme->name);
  }

  public function testGetAll() {
    $param = [
      'admin_title' => 'Admin title',
      'admin_description' => 'Admin description',
      'public_title' => 'Public value',
      'public_description' => 'Public description',
      'permission' => 'public',
      'enabled' => TRUE,
      'parameters' => '{"name":"John", "age":30, "car":null}',
    ];

    for ($i = 0; $i < 5; $i++) {
      $param['name'] = random_bytes(5);
      CRM_MembershipExtras_BAO_PaymentScheme::create($param);
    }

    $schemes = CRM_MembershipExtras_BAO_PaymentScheme::getAll();
    $this->assertEquals(5, count($schemes));
    $this->assertEqusls(5, $schemes[0]);
  }

}
