<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;

/**
 * Class api_v3_PaymentSchedule_GetScheduleOptionsTest
 *
 * @group headless
 */
class api_v3_PaymentSchedule_GetScheduleOptionsTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;

  /**
   * Test get schedule options for fixed period membership type
   *
   * @throws CiviCRM_API3_Exception
   */
  public function testGetScheduleOptionsForFixedPeriodMembershipType() {
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Fixed Membership Type',
      'period_type' => 'fixed',
      'duration_unit' => 'year',
      'duration_interval' => 1,
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
      'minimum_fee' => 120,
    ]);
    $params = ['membership_type_id' => $membershipType['id']];
    $options = $this->callAPI($params, 'getScheduleOptionsByMembershipType');
    $expectedOptions = ['monthly' => 'Monthly', 'annual' => 'Annual'];
    $this->assertEquals($expectedOptions, $options);
  }

  /**
   * Test get schedule options for rolling period membership type with year duration unit
   */
  public function testGetScheduleOptionsForRollingPeriodMembershipTypeWithYearDurationUnit() {
    $membershipType = $this->mockRollingMembershipType();
    $params = ['membership_type_id' => $membershipType['id']];
    $options = $this->callAPI($params, 'getScheduleOptionsByMembershipType');
    $expectedOptions = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'];
    $this->assertEquals($expectedOptions, $options);
  }

  /**
   * Test get schedule options for rolling period membership type with month duration unit
   */
  public function testGetScheduleOptionsForRollingPeriodMembershipTypeWithMonthDurationUnit() {
    $membershipType = $this->mockRollingMembershipType('month');
    $params = ['membership_type_id' => $membershipType['id']];
    $options = $this->callAPI($params, 'getScheduleOptionsByMembershipType');
    $expectedOptions = ['monthly' => 'Monthly'];
    $this->assertEquals($expectedOptions, $options);
  }

  /**
   * Tests get schedule options for rolling period membership type with life time duration unit
   */
  public function testGetScheduleOptionsForRollingPeriodMembershipTypeWithLifeTimeDurationUnit() {
    $membershipType = $this->mockRollingMembershipType('lifetime');
    $params = ['membership_type_id' => $membershipType['id']];
    $expectedOptions = ['monthly' => 'Monthly'];
    $options = $this->callAPI($params, 'getScheduleOptionsByMembershipType');
    $this->assertEquals($expectedOptions, $options);
  }

  public function testGetScheduleOptionsWhenSelectPriceSet() {
    $priceFieldValues = $this->mockPriceFieldValues();
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
    $options = $this->callAPI($params, 'getScheduleOptionsByPriceFieldValues');
    $expectedOptions = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'];
    $this->assertEquals($expectedOptions, $options);
  }

  /**
   * @param string $durationUnit
   *
   * @return CRM_Member_BAO_MembershipType|mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockRollingMembershipType($durationUnit = 'year') {
    return MembershipTypeFabricator::fabricate([
      'name' => 'Rolling Membership Type',
      'period_type' => 'rolling',
      'duration_unit' => $durationUnit,
      'duration_interval' => 1,
      'minimum_fee' => 120,
    ]);
  }

  /**
   * @param $params
   * @param $action
   *
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function callAPI($params, $action) {
    return civicrm_api3('PaymentSchedule', $action, $params)['values'];
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
