<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;
use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment as InvalidMembershipTypeInstalment;

/**
 * Class CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlanTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlanTest extends BaseHeadlessTest {

  private $form;
  private $errors;

  public function setUp() {
    $this->form = new CRM_Member_Form();
    $this->errors = ['price_set_id' => NULL];
  }

  /**
   * Tests error when mixed period membership type price field values are submitted.
   */
  public function testErrorWhenMixedPeriodMembershipTypePriceFieldValuesAreSubmitted() {
    $priceSet = $this->mockPriceSet();
    $priceField = $this->mockPriceField($priceSet['id'], 'Test Field Set 1');
    $memType1 = $this->mockMembershipType('rolling', 'year');
    $memType2 = $this->mockMembershipType('fixed', 'year');
    $priceFieldValue1 = $this->mockPriceFieldValue($priceField['id'], $memType1['id']);
    $priceFieldValue2 = $this->mockPriceFieldValue($priceField['id'], $memType2['id']);

    $fields = [];
    $fields['price_set_id'] = $priceSet['id'];
    $mockedPriceFieldKey = 'price_' . (string) $priceField['id'];
    //Simulate check boxes with different period membership period type attach to price field values
    $fields[$mockedPriceFieldKey] = [
      $priceFieldValue1['id'] => 1,
      $priceFieldValue2['id'] => 1,
    ];
    $paymentPlanValidation = new CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan($this->form, $fields, $this->errors);
    $paymentPlanValidation->validate();
    $this->assertEquals(InvalidMembershipTypeInstalment::SAME_PERIOD_AND_DURATION, $this->errors['price_set_id']);
  }

  /**
   * Tests error when mixed period membership type price field values are submitted.
   */
  public function testErrorWhenMixedDurationUnitMembershipTypePriceFieldValuesAreSubmitted() {
    $priceSet = $this->mockPriceSet();
    $priceField = $this->mockPriceField($priceSet['id'], 'Test Field Set 1');
    $memType1 = $this->mockMembershipType('rolling', 'year');
    $memType2 = $this->mockMembershipType('rolling', 'month');
    $priceFieldValue1 = $this->mockPriceFieldValue($priceField['id'], $memType1['id']);
    $priceFieldValue2 = $this->mockPriceFieldValue($priceField['id'], $memType2['id']);

    $fields = [];
    $fields['price_set_id'] = $priceSet['id'];
    $mockedPriceFieldKey = 'price_' . (string) $priceField['id'];
    //Simulate check boxes with different period membership period type attach to price field values
    $fields[$mockedPriceFieldKey] = [
      $priceFieldValue1['id'] => 1,
      $priceFieldValue2['id'] => 1,
    ];
    $paymentPlanValidation = new CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan($this->form, $fields, $this->errors);
    $paymentPlanValidation->validate();
    $this->assertEquals(InvalidMembershipTypeInstalment::SAME_PERIOD_AND_DURATION, $this->errors['price_set_id']);
  }

  /**
   * Tests error when mixed period membership type price field values are submitted.
   */
  public function testErrorWhenMixedDurationUnitAndPeriodMembershipTypePriceFieldValuesAreSubmitted() {
    $priceSet = $this->mockPriceSet();
    $priceField = $this->mockPriceField($priceSet['id'], 'Test Field Set 1');
    $memType1 = $this->mockMembershipType('rolling', 'year');
    $memType2 = $this->mockMembershipType('fixed', 'month');
    $priceFieldValue1 = $this->mockPriceFieldValue($priceField['id'], $memType1['id']);
    $priceFieldValue2 = $this->mockPriceFieldValue($priceField['id'], $memType2['id']);

    $fields = [];
    $fields['price_set_id'] = $priceSet['id'];
    $mockedPriceFieldKey = 'price_' . (string) $priceField['id'];
    //Simulate check boxes with different period membership period type attach to price field values
    $fields[$mockedPriceFieldKey] = [
      $priceFieldValue1['id'] => 1,
      $priceFieldValue2['id'] => 1,
    ];
    $paymentPlanValidation = new CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan($this->form, $fields, $this->errors);
    $paymentPlanValidation->validate();
    $this->assertEquals(InvalidMembershipTypeInstalment::SAME_PERIOD_AND_DURATION, $this->errors['price_set_id']);
  }

  /**
   * Tests error when mixed period start days membership type for price field values are submitted.
   */
  public function testErrorWhenMixedFixedPeriodStartDaysMembershipTypePriceFieldValuesAreSubmitted() {
    $priceSet = $this->mockPriceSet();
    $priceField1 = $this->mockPriceField($priceSet['id'], 'Test Price Field 1');
    $priceField2 = $this->mockPriceField($priceSet['id'], 'Test Price Field 1');

    $memType1 = $this->mockMembershipType('fixed', 'year', 101);
    $memType2 = $this->mockMembershipType('fixed', 'year', 1001);

    $priceFieldValue2 = $this->mockPriceFieldValue($priceField2['id'], $memType2['id']);
    $priceFieldValue1 = $this->mockPriceFieldValue($priceField1['id'], $memType1['id']);

    $fields = [];
    $fields['price_set_id'] = $priceSet['id'];
    $mockedPriceFieldKey1 = 'price_' . (string) $priceField1['id'];
    //Simulate field when price field is check box
    $fields[$mockedPriceFieldKey1] = [
      $priceFieldValue1['id'] => 1,
    ];
    //Simulate field when price field is radio button
    $mockedPriceFieldKey2 = 'price_' . (string) $priceField2['id'];
    $fields[$mockedPriceFieldKey2] = $priceFieldValue2['id'];

    $paymentPlanValidation = new CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan($this->form, $fields, $this->errors);
    $paymentPlanValidation->validate();
    $this->assertEquals(InvalidMembershipTypeInstalment::SAME_PERIOD_START_DAY, $this->errors['price_set_id']);
  }

  /**
   * Tests no error when price set
   */
  public function testNoErrorWhenPriceSetRollingMembershipTypeIsSubmitted() {
    $priceSet = $this->mockPriceSet();
    $priceField = $this->mockPriceField($priceSet['id'], 'Test Field Set 1');
    $memType1 = $this->mockMembershipType('rolling', 'year');
    $memType2 = $this->mockMembershipType('rolling', 'year');
    $priceFieldValue1 = $this->mockPriceFieldValue($priceField['id'], $memType1['id']);
    $priceFieldValue2 = $this->mockPriceFieldValue($priceField['id'], $memType2['id']);

    $fields = [];
    $fields['price_set_id'] = $priceSet['id'];
    $mockedPriceFieldKey = 'price_' . (string) $priceField['id'];
    //Simulate check boxes with different period membership period type attach to price field values
    $fields[$mockedPriceFieldKey] = [
      $priceFieldValue1['id'] => 1,
      $priceFieldValue2['id'] => 1,
    ];

    $paymentPlanValidation = new CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan($this->form, $fields, $this->errors);
    $paymentPlanValidation->validate();
    $this->assertNull($this->errors['price_set_id']);
  }

  /**
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceSet() {
    $priceSetParams = [
      'name' => "test_price_set",
      'extends' => "CiviMember",
      'financial_type_id' => "Member Dues",
      'is_active' => 1,
    ];

    return PriceSetFabricator::fabricate($priceSetParams);
  }

  /**
   * @param $priceSetId
   * @param string $priceFieldLabel
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceField($priceSetId, $priceFieldLabel = 'Membership Amount') {
    return PriceFieldFabricator::fabricate([
      'price_set_id' => $priceSetId,
      'label' => "$priceFieldLabel",
      'name' => "price_field_1",
      'html_type' => "Radio",
    ]);
  }

  /**
   *
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceFieldValue($priceFieldId, $membershipTypeId) {
    return PriceFieldValueFabricator::fabricate([
      'price_field_id' => $priceFieldId,
      'label' => "Price Field Value with Membership Type " . (string) $membershipTypeId,
      'amount' => 240,
      'membership_type_id' => $membershipTypeId,
      'financial_type_id' => "Member Dues",
    ]);
  }

  /**
   * @param $membershipPeriodType
   * @param $durationUnit
   * @param $setting
   * @param $fixedPeriodStartDate
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  private function mockMembershipType($membershipPeriodType, $durationUnit, $fixedPeriodStartDate = 101) {
    $memType = MembershipTypeFabricator::fabricate([
      'name' => 'Mock Membership type',
      'period_type' => $membershipPeriodType,
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => $durationUnit,
      //01 Oct
      'fixed_period_start_day' => $fixedPeriodStartDate,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
    ]);

    return $memType;
  }

}
