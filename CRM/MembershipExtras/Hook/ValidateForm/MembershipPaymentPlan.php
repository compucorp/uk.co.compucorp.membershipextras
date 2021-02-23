<?php

use CRM_MembershipExtras_Exception_InvalidMembershipTypeInstalment as InvalidMembershipTypeInstalment;

/**
 * Form Validation on payment plan submission.
 */
class CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan {

  /**
   * @var \CRM_Member_Form_Membership
   *   Form object that is being validated.
   */
  private $form;

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
   * CRM_MembershipExtras_Hook_ValidateForm_MembershipPaymentPlan constructor.
   *
   * @param CRM_Member_Form $form
   * @param $fields
   * @param $errors
   */
  public function __construct(CRM_Member_Form &$form, &$fields, &$errors) {
    $this->form = $form;
    $this->fields = &$fields;
    $this->errors = &$errors;
  }

  /**
   * Validates the payment plan form submission
   * fields when creating a membership.
   */
  public function validate() {
    $this->validateMembershipStartDate();
    $this->validatePriceSet();
  }

  /**
   * Validates that membership start date is entered.
   */
  private function validateMembershipStartDate() {
    if (empty($this->fields['start_date'])) {
      $this->errors['start_date'] = ts('Start date is required');
    }
  }

  /**
   * Validates selected fixed membermship types when using price set
   * have that same period type and same period start day.
   */
  private function validatePriceSet() {
    $fixedPeriodStartDays = [];
    $periodTypes = [];
    $priceSetID = $this->fields['price_set_id'];
    if (empty($priceSetID)) {
      return;
    }

    $membershipTypes = $this->getMembershipTypesFromPriceFieldValueFields();

    $fixedPeriodStartDays = [];
    $periodTypes = [];
    foreach ($membershipTypes as $membershipType) {
      $periodTypes[] = $membershipType['period_type'];
      if ($membershipType['period_type'] == 'fixed') {
        $fixedPeriodStartDays[] = $membershipType['fixed_period_start_day'];
      }
    }

    $periodTypes = array_unique($periodTypes);

    if (!empty($periodTypes)) {
      if (!empty($periodTypes) && count($periodTypes) != 1) {
        $this->errors['price_set_id'] = InvalidMembershipTypeInstalment::PERIOD_TYPE;
      }
    }

    $fixedPeriodStartDays = array_unique($fixedPeriodStartDays);
    if (!empty($fixedPeriodStartDays) && count($fixedPeriodStartDays) != 1) {
      $this->errors['price_set_id'] = InvalidMembershipTypeInstalment::SAME_PERIOD_START_DAY;
    }
  }

  /**
   * Gets price field values and membership types from the submitted form fields.
   */
  private function getMembershipTypesFromPriceFieldValueFields() {
    $indexes = array_keys($this->fields);
    //Search for field starting with price_ follwoing by arbitrary numbers
    $matchedPriceFieldFields = preg_grep('/^price_(\d+)/', $indexes);
    $selectedPriceFieldIdValues = [];
    foreach ($matchedPriceFieldFields as $matchedField) {
      if ((is_array($this->fields[$matchedField]))) {
        foreach ($this->fields[$matchedField] as $priceFieldValueId => $value) {
          $selectedPriceFieldIdValues[] = $priceFieldValueId;
        }
      }
      else {
        $selectedPriceFieldIdValues[] = $this->fields[$matchedField];
      }
    }

    if (empty($selectedPriceFieldIdValues)) {
      return;
    }

    $priceFieldValues = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'id' => ['IN' => $selectedPriceFieldIdValues],
      'api.MembershipType.get' => [],
    ])['values'];

    $membershipTypes = [];
    foreach ($priceFieldValues as $priceFieldValue) {
      $membershipType = $priceFieldValue['api.MembershipType.get']['values'][0];
      if (!empty($membershipType)) {
        $membershipTypes[] = $membershipType;
      }
    }

    return $membershipTypes;
  }

}
