<?php

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
   * CRM_MembershipExtras_Hook_BuildForm_Membership constructor.
   *
   * @param \CRM_Member_Form $form
   */
  public function __construct(CRM_Member_Form &$form, &$fields, &$errors) {
    $this->form = $form;
    $this->fields = &$fields;
    $this->errors = &$errors;
  }

  /**
   * Validates the payment plan form submission
   * fields when renewing or creating memberships.
   */
  public function validate() {
    $installmentsCount = CRM_Utils_Array::value('installments', $this->fields);
    if (empty($installmentsCount)) {
      $this->errors['installments'] = ts('"Installments" is a required field');
    }

    $installmentsFrequency = CRM_Utils_Array::value('installments_frequency', $this->fields);
    if (empty($installmentsFrequency)) {
      $this->errors['installments_frequency'] = ts('"Installments Frequency" is a required field');
    }

    $installmentsFrequencyUnit = CRM_Utils_Array::value('installments_frequency_unit', $this->fields);
    if (empty($installmentsFrequencyUnit)) {
      $this->errors['installments_frequency_unit'] = ts('"Installments Frequency Unit" is a required field');
    }
  }

}
