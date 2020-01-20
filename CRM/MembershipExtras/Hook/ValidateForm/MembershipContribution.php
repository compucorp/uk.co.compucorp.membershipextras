<?php

/**
 * Class CRM_MembershipExtras_Hook_ValidateForm_MembershipContribution.
 *
 * Implements ValidateForm hook for membership creation with a single
 * contribution.
 */
class CRM_MembershipExtras_Hook_ValidateForm_MembershipContribution {

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
   * CRM_MembershipExtras_Hook_ValidateForm_MembershipContribution constructor.
   *
   * @param \CRM_Member_Form $form
   *   Form that is being validated.
   * @param array $fields
   *   List of fields for the form with their current values.
   * @param array $errors
   *   List of validation errors that have been found on the form.
   */
  public function __construct(CRM_Member_Form &$form, &$fields, &$errors) {
    $this->form = $form;
    $this->fields = &$fields;
    $this->errors = &$errors;
  }

  /**
   * Validates the form.
   */
  public function validate() {
    $isOfflineAutoRenew = CRM_Utils_Array::value('offline_auto_renew', $this->fields, FALSE);
    if ($isOfflineAutoRenew) {
      $this->errors['offline_auto_renew'] = ts(
        '"Auto-renew offline" is only available for memberships paid with a
         payment plan. Please choose a payment plan, or uncheck the auto-renew 
         option if the membership will be paid for with a single contribution.'
      );
    }
  }

}
