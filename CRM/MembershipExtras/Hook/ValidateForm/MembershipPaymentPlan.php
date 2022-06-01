<?php

use CRM_MembershipExtras_Validate_PaymentPlan_MembershipType as membershipTypeValidator;

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
    $this->validateMembershipTypes();
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
   * Validates selected membermship types for payemnt plan.
   */
  public function validateMembershipTypes() {
    $membershipTypes = [];

    if (!empty($this->fields['membership_type_id']) && $this->fields['membership_type_id'][1]) {
      $errorField = 'membership_type_id';
      $membershipTypes = [CRM_Member_BAO_MembershipType::findById($this->fields['membership_type_id'][1])];
    }
    if (!empty($this->fields['price_set_id'])) {
      $errorField = 'price_set_id';
      $membershipTypes = $this->getMembershipTypesFromPriceFieldValueFields();
    }

    if (empty($membershipTypes)) {
      return;
    }

    $validator = new membershipTypeValidator($membershipTypes);

    if (!$validator->passes()) {
      $this->errors[$errorField] = $validator->lastError();
    }
  }

  /**
   * Gets price field values and membership types from the submitted form fields.
   */
  private function getMembershipTypesFromPriceFieldValueFields() {
    $matchedPriceFields = $this->getMatchedPriceFields();
    $selectedPriceFieldIdValues = [];
    foreach ($matchedPriceFields as $matchedField) {
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

  /***
   * Finds price fields from submitted form and return only membership price field
   */
  private function getMatchedPriceFields() {
    $indexes = array_keys($this->fields);
    //Search for field starting with price_ follwoing by arbitrary numbers
    $matchedPriceFieldFields = preg_grep('/^price_(\d+)/', $indexes);
    foreach ($matchedPriceFieldFields as $key => $matchedPriceFieldField) {
      $explodedField = explode('_', $matchedPriceFieldField);
      $pricFieldId = $explodedField[1];
      if ($this->isTextTypePriceField($pricFieldId)) {
        //Unset any price field that is input text as they are not membership price field
        unset($matchedPriceFieldFields[$key]);
      };
    }

    return $matchedPriceFieldFields;
  }

  /**
   * Checks if price field is using Text field
   */
  private function isTextTypePriceField($id) {
    $priceField = civicrm_api3('PriceField', 'getsingle', [
      'id' => $id,
    ]);

    if ($priceField['html_type'] == 'Text') {
      return TRUE;
    }

    return FALSE;
  }

}
