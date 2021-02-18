<?php

/**
 * Class CRM_MembershipExtras_Hook_ValidateForm_MembershipType
 *
 * Validates Membership Type Form
 */
class CRM_MembershipExtras_Hook_ValidateForm_MembershipType {

  /**
   * @var array
   *   List of the submitted fields and their values passed from the hook.
   */
  private $fields;

  /**
   * @var array
   *   List of form validation errors passed from the hook.
   */
  private $errors;

  /**
   * CRM_MembershipExtras_Hook_ValidateForm_MembershipType constructor.
   *
   * @param $fields
   * @param $errors
   */
  public function __construct(&$fields, &$errors) {
    $this->fields = &$fields;
    $this->errors = &$errors;
  }

  /**
   * Validates the membership form submission
   */
  public function validate() {
    $membershipPeriodType = $this->fields['period_type'];
    if ($membershipPeriodType != 'fixed') {
      return;
    }

    $this->validateDuration();
    $this->validateAnnualProRataCalculation();
  }

  /**
   * Validates if the annual pro rata calculation is required.
   */
  private function validateAnnualProRataCalculation() {
    if (empty($this->fields['membership_type_annual_pro_rata_calculation'])) {
      $this->errors['membership_type_annual_pro_rata_calculation'] = ts('This field is required.');
    }
  }

  /**
   * Validates that only yearly membership is supported.
   */
  private function validateDuration() {
    $durationInterval = $this->fields['duration_interval'];
    $durationUnit = $this->fields['duration_unit'];
    if ($durationInterval != 1 || $durationUnit != 'year') {
      $this->errors['duration_unit'] = ts('Fixed period membership type only supports 1 year duration.');
    }
  }

}
