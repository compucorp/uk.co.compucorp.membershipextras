<?php

use CRM_MembershipExtras_Service_PaymentScheme_PaymentPlanScheduleGenerator as PaymentPlanScheduleGenerator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Entity_PaymentPlanMembershipOrder as PaymentPlanMembershipOrder;
use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;

/**
 * Class CRM_MembershipExtras_Service_PaymentPlanScheduleGeneratorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_PaymentPlanScheduleGeneratorTest extends BaseHeadlessTest {

  private $testRollingMembershipType;

  private $testRollingMembershipTypePriceFieldValue;

  private $paymentScheme;

  private $paymentPlanOrder;

  public function setUp(): void {
    $this->createTestRollingMembershipType();
    $this->createPaymentPlanOrder();
    $this->createPaymentSchemeAndLinkToPaymentPlanOrder();
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

  private function createPaymentPlanOrder() {
    $paymentPlanMembershipOrder = new PaymentPlanMembershipOrder();
    $paymentPlanMembershipOrder->membershipStartDate = date('Y-m-d');
    $paymentPlanMembershipOrder->membershipEndDate = date('Y-m-d', strtotime('+1 year'));
    $paymentPlanMembershipOrder->paymentPlanFrequency = 'Yearly';
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
    $this->paymentPlanOrder = PaymentPlanOrderFabricator::fabricate($paymentPlanMembershipOrder);
  }

  private function createPaymentSchemeAndLinkToPaymentPlanOrder() {
    $params = [
      'name' => 'Test scheme',
      'admin_title' => 'Admin title',
      'description' => 'description',
      'public_title' => 'Public value',
      'public_description' => 'Public description',
      'permission' => 'public',
      'enabled' => TRUE,
      'parameters' => '{"instalments_count": 2,"instalments": [{"charge_date": "{next_period_start_date}, + 1 month"},{"charge_date": "{next_period_start_date}, + 2 months"}]}',
      'payment_processor' => 1,
    ];
    $this->paymentScheme = CRM_MembershipExtras_BAO_PaymentScheme::create($params);

    \Civi\Api4\ContributionRecur::update()
      ->addValue('id', $this->paymentPlanOrder['id'])
      ->addValue('payment_plan_extra_attributes.payment_scheme_id', $this->paymentScheme->id)
      ->execute();
  }

  public function testUsingInvalidRecurringContributionIdThrowsAnError() {
    $this->expectException(CRM_Extension_Exception::class);

    $paymentPlanScheduleGenerator = new PaymentPlanScheduleGenerator(100000000000);
    $paymentPlanScheduleGenerator->generateSchedule();
  }

  public function testUsingRecurringContributionWithNoLinkedPaymentSchemeThrowsAnError() {
    $this->expectException(CRM_Extension_Exception::class);

    $results = \Civi\Api4\ContributionRecur::create()
      ->addValue('contact_id', 1)
      ->addValue('amount', 100)
      ->addValue('is_test', FALSE)
      ->addValue('auto_renew', TRUE)
      ->execute()
      ->getArrayCopy();

    $paymentPlanScheduleGenerator = new PaymentPlanScheduleGenerator($results[0]['id']);
    $paymentPlanScheduleGenerator->generateSchedule();
  }

  public function testGenerateScheduleReturnsCorrectScheduleFormat() {
    $paymentPlanScheduleGenerator = new PaymentPlanScheduleGenerator($this->paymentPlanOrder['id']);
    $paymentPlanSchedule = $paymentPlanScheduleGenerator->generateSchedule();

    $this->assertArrayHasKey('name', $paymentPlanSchedule);
    $this->assertArrayHasKey('currency', $paymentPlanSchedule);
    $this->assertArrayHasKey('instalments', $paymentPlanSchedule);
    $this->assertArrayHasKey('total_amount', $paymentPlanSchedule);
    $this->assertTrue(is_array($paymentPlanSchedule['instalments']));
    $this->assertTrue(is_array($paymentPlanSchedule['instalments'][0]));
  }

  public function testTotalAmountIsTheSumOfAllInstalmentAmounts() {
    $paymentPlanScheduleGenerator = new PaymentPlanScheduleGenerator($this->paymentPlanOrder['id']);
    $paymentPlanSchedule = $paymentPlanScheduleGenerator->generateSchedule();

    $this->assertEquals(240, $paymentPlanSchedule['total_amount']);
  }

  /**
   * @dataProvider providerTest
   */
  public function testInstalmentsScheduleGeneratedCorrectlyFromPaymentSchemeParameters($expectedInstalmentsSchedule, $newSchemeParams) {
    $params = [
      'id' => $this->paymentScheme->id,
      'parameters' => $newSchemeParams,
    ];
    $this->paymentScheme = CRM_MembershipExtras_BAO_PaymentScheme::create($params);

    $paymentPlanScheduleGenerator = new PaymentPlanScheduleGenerator($this->paymentPlanOrder['id']);
    $paymentPlanSchedule = $paymentPlanScheduleGenerator->generateSchedule();

    $this->assertEquals($expectedInstalmentsSchedule, $paymentPlanSchedule['instalments']);
  }

  public function providerTest() {
    return [
      [
        // with membership token
        [['charge_date' => date('Y-m-d', strtotime('+1 year +1 day +1 month')), 'amount' => 120], ['charge_date' => date('Y-m-d', strtotime('+1 year +1 day +2 month')), 'amount' => 120]],
        '{"instalments_count": 2,"instalments": [{"charge_date": "{next_period_start_date}, + 1 month"},{"charge_date": "{next_period_start_date}, + 2 months"}]}',
      ],
      [
        // with hardcoded date
        [['charge_date' => date('Y-m-d', strtotime('2021-01-01 +1 month')), 'amount' => 120], ['charge_date' => date('Y-m-d', strtotime('2021-01-01 +6 month')), 'amount' => 120]],
        '{"instalments_count": 2,"instalments": [{"charge_date": "2021-01-01, + 1 month"},{"charge_date": "2021-01-01, + 6 months"}]}',
      ],
      [
        // with relative date and no added time
        [['charge_date' => date('Y-m-d', strtotime('10 March')), 'amount' => 120], ['charge_date' => date('Y-m-d', strtotime('13 June')), 'amount' => 120]],
        '{"instalments_count": 2,"instalments": [{"charge_date": "10 March"},{"charge_date": "13 June"}]}',
      ],
    ];
  }

}
