<?php

/**
 * Class CRM_MembershipExtras_Hook_ValidateForm_MembershipTypeTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_ValidateForm_MembershipTypeTest extends BaseHeadlessTest {

  public function testValidateFixedPeriodWithInValidDuration() {
    $fields = [
      'period_type' => 'fixed',
      'duration_interval' => '1',
      'duration_unit' => 'month',
      'membership_type_annual_pro_rata_calculation' => '1',
    ];
    $errors = [];
    $membeshipType = new CRM_MembershipExtras_Hook_ValidateForm_MembershipType($fields, $errors);
    $membeshipType->validate();
    $this->assertArrayHasKey('duration_unit', $errors);
  }

  public function testValidateFixedPeriodWithInValidInterval() {
    $fields = [
      'period_type' => 'fixed',
      'duration_interval' => '2',
      'duration_unit' => 'year',
      'membership_type_annual_pro_rata_calculation' => '1',
    ];
    $errors = [];
    $membeshipType = new CRM_MembershipExtras_Hook_ValidateForm_MembershipType($fields, $errors);
    $membeshipType->validate();
    $this->assertArrayHasKey('duration_unit', $errors);
  }

  public function testValidateFixedPeriodWithInValidIntervalAndDuration() {
    $fields = [
      'period_type' => 'fixed',
      'duration_interval' => '12',
      'duration_unit' => 'month',
      'membership_type_annual_pro_rata_calculation' => '1',
    ];
    $errors = [];
    $membeshipType = new CRM_MembershipExtras_Hook_ValidateForm_MembershipType($fields, $errors);
    $membeshipType->validate();
    $this->assertArrayHasKey('duration_unit', $errors);
  }

  public function testValidateFixedPeriodWithValidIntervalAndDuration() {
    $fields = [
      'period_type' => 'fixed',
      'duration_interval' => '1',
      'duration_unit' => 'year',
      'membership_type_annual_pro_rata_calculation' => '1',
    ];
    $errors = [];
    $membeshipType = new CRM_MembershipExtras_Hook_ValidateForm_MembershipType($fields, $errors);
    $membeshipType->validate();
    $this->assertEmpty($errors);
  }

  public function testNotToValidateRolling() {
    $fields = [
      'period_type' => 'rolling',
    ];
    $errors = [];
    $membeshipType = new CRM_MembershipExtras_Hook_ValidateForm_MembershipType($fields, $errors);
    $membeshipType->validate();
    $this->assertEmpty($errors);
  }

  public function testValidateAnnualProrataCalculationIsRequired() {
    $fields = [
      'period_type' => 'fixed',
      'duration_interval' => '1',
      'duration_unit' => 'year',
    ];
    $errors = [];
    $membeshipType = new CRM_MembershipExtras_Hook_ValidateForm_MembershipType($fields, $errors);
    $membeshipType->validate();
    $this->assertArrayHasKey('membership_type_annual_pro_rata_calculation', $errors);
  }

}
