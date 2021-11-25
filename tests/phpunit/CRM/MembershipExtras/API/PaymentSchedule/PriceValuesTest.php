<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;

/**
 * Class CRM_MembershipExtras_API_PaymentSchedule_PriceValuesTest
 *
 * @group headless
 */
class CRM_MembershipExtras_API_PaymentSchedule_PriceValuesTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_PaymentMethodTrait;

  /**
   * @throws CiviCRM_API3_Exception
   * @throws API_Exception
   */
  public function testGetInstalmentsWithPriceFieldValues() {
    $priceFieldValues = $this->mockPriceFieldValues();
    $mockQuantity = 10;
    $expectedAmount = 0;
    $selectedPriceFieldValues = [];

    foreach ($priceFieldValues as $priceFieldValue) {
      if (empty($priceFieldValue['membership_type_id'])) {
        $selectedPriceFieldValues[$priceFieldValue['id']] = $mockQuantity;
        $expectedAmount += $priceFieldValue['amount'] * $mockQuantity;
        continue;
      }
      else {
        $selectedPriceFieldValues[$priceFieldValue['id']] = 1;
        $expectedAmount += $priceFieldValue['amount'];
      }
    }

    $params = [
      'schedule' => CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::MONTHLY,
      'price_field_values' => ['IN' => $selectedPriceFieldValues],
      'payment_method' => $this->getPaymentMethodValue(),
    ];

    $paymentSchedule = new CRM_MembershipExtras_API_PaymentSchedule_PriceValues($params);

    $schedule = $paymentSchedule->getPaymentSchedule();

    $this->assertNotEmpty($schedule);
    $totalAmount = 0;
    foreach ($schedule['instalments'] as $instalment) {
      $totalAmount += $instalment->getInstalmentAmount()->getAmount();
    }

    $this->assertEquals($expectedAmount, $totalAmount);
  }

  public function testGetScheduleOptionsWhenSelectPriceSet() {
    $priceFieldValues = $this->mockPriceFieldValues();
    $params = [
      'sequential' => 1,
      'schedule' => 'monthly',
      'payment_method' => $this->getPaymentMethodValue(),
    ];
    $selectedPriceFieldValues = [];
    foreach ($priceFieldValues as $priceFieldValue) {
      if (empty($priceFieldValue['membership_type_id'])) {
        $selectedPriceFieldValues[$priceFieldValue['id']] = 10;
      }
      else {
        $selectedPriceFieldValues[$priceFieldValue['id']] = 1;
      }
    }

    $params['price_field_values'] = ['IN' => $selectedPriceFieldValues];
    $paymentSchedule = new CRM_MembershipExtras_API_PaymentSchedule_PriceValues($params);
    $options = $paymentSchedule->getPaymentScheduleOptions();
    $expectedOptions = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'];
    $this->assertEquals($expectedOptions, $options);
  }

  /**
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceFieldValues() {
    $priceFieldValues = [];
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type',
      'period_type' => 'rolling',
      'duration_unit' => 'year',
      'minimum_fee' => 120,
      'duration_interval' => 1,
    ]);

    $priceSetParams = [
      'name' => "test_price_set",
      'extends' => "CiviMember",
      'financial_type_id' => "Member Dues",
      'is_active' => 1,
    ];

    $priceSet = PriceSetFabricator::fabricate($priceSetParams);

    $priceField1 = PriceFieldFabricator::fabricate([
      'price_set_id' => $priceSet['id'],
      'label' => "Price Field 1",
      'name' => "price_field_1",
      'html_type' => "Radio",
    ]);

    $priceFieldValues[] = PriceFieldValueFabricator::fabricate([
      'price_field_id' => $priceField1['id'],
      'label' => "Price Field Value with Membership Type 1",
      'amount' => 240,
      'membership_type_id' => $membershipType['id'],
      'financial_type_id' => "Member Dues",
    ]);

    $priceField2 = PriceFieldFabricator::fabricate([
      'price_set_id' => $priceSet['id'],
      'label' => "Price Field 1",
      'name' => "price_field_2",
      'html_type' => "Text",
    ]);

    $priceFieldValues[] = PriceFieldValueFabricator::fabricate([
      'price_field_id' => $priceField2['id'],
      'label' => "Price Field Value without Membership Type 1",
      'amount' => 120,
      'financial_type_id' => "Member Dues",
    ]);

    return $priceFieldValues;
  }

}
