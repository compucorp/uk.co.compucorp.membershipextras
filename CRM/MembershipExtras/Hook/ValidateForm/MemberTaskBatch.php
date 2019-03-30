<?php


class CRM_MembershipExtras_Hook_ValidateForm_MemberTaskBatch {

  private $form;

  private $errors;

  /**
   * CRM_MembershipExtras_Hook_BuildForm_Membership constructor.
   *
   * @param \CRM_Member_Form $form
   */
  public function __construct(CRM_Member_Form_Task_Batch &$form, &$errors) {
    $this->form = $form;
    $this->errors = &$errors;
  }

  /**
   * Validates the payment plan form submission
   * fields when renewing or creating memberships.
   */
  public function validate() {
    $params = $this->form->exportValues();
    foreach ($params['field'] as $key => $value) {
      if (!empty($value['membership_start_date']) && empty($value['join_date'])) {
        $this->errors['field['.$key.'][membership_start_date]'] = 'Membership join date should be exposed and equal the start date';
      }

      if (empty($value['membership_start_date']) && !empty($value['join_date'])) {
        $this->errors['field['.$key.'][join_date]'] = 'Membership start date should be exposed and equal the join date';
      }

      if (!empty($value['membership_start_date']) && !empty($value['join_date'])) {
        if ($value['join_date'] != $value['membership_start_date']) {
          $this->errors['field['.$key.'][join_date]'] = 'Membership join date should equal membership start date';
        }
      }
    }
  }

}
