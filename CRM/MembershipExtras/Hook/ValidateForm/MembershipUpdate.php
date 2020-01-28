<?php
use CRM_MembershipExtras_Service_ManualPaymentProcessors as ManualPaymentProcessors;

/**
 * Class CRM_MembershipExtras_Hook_ValidateForm_MembershipUpdate.
 *
 * Implements hook to validate the membership update form.
 */
class CRM_MembershipExtras_Hook_ValidateForm_MembershipUpdate {

  /**
   * Form object that is being validated.
   *
   * @var \CRM_Member_Form_Membership
   */
  private $form;

  /**
   * List of the submitted fields and their values passed from the hook.
   *
   * @var array
   */
  private $fields;

  /**
   * List of form validation errors passed from the hook.
   *
   * @var array
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
   * Validates the form.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function validate() {
    if ($this->isPaymentPlan() && $this->isMembershipTypeChanged()) {
      $this->errors['membership_type_id'] = ts(
        'This membership is part of an ongoing payment plan and cannot be 
        edited directly. To modify the active memberships in this payment plan 
        please go to the manage future instalments screen which can be found as 
        an action on the most recent recurring contribution record. There you 
        can add or remove memberships.'
      );
    }
  }

  /**
   * Checks if the membership was paid for with a payment plan.
   *
   * @return bool
   *   TRUE if membership is part of a payment plan, FALSE otherwise.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function isPaymentPlan() {
    $contribution = $this->getLastMembershipPayment();

    if (empty($contribution['contribution_id.contribution_recur_id'])) {
      return FALSE;
    }

    $recurringContribution = $this->getRecurringContribution($contribution['contribution_id.contribution_recur_id']);
    $processorID = CRM_Utils_Array::value('payment_processor_id', $recurringContribution);
    $isManualPaymentPlan = ManualPaymentProcessors::isManualPaymentProcessor($processorID);

    if ($isManualPaymentPlan) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Obtains last payment used for the membership.
   *
   * @return array
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getLastMembershipPayment() {
    $contribution = civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'membership_id' => $this->form->_id,
      'options' => [
        'limit' => 1,
        'sort' => 'contribution_id DESC',
      ],
      'return' => [
        'contribution_id',
        'contribution_id.contribution_recur_id',
      ],
    ]);

    if ($contribution['count'] > 0) {
      return $contribution['values'][0];
    }

    return [];
  }

  /**
   * Obtains information for the given recurring contribution ID.
   *
   * @param int $recurringContributionID
   *   ID of the recurring contribution.
   *
   * @return array
   *   Data for the recurring contribution.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function getRecurringContribution($recurringContributionID) {
    if (empty($recurringContributionID)) {
      return [];
    }

    return civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $recurringContributionID,
    ]);
  }

  /**
   * Checks if the membership type was changed by the user.
   *
   * @return bool
   *   TRUE if the membershipt ype changed, FALSE otherwise.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function isMembershipTypeChanged() {
    $membership = civicrm_api3('Membership', 'getsingle', [
      'id' => $this->form->_id,
    ]);

    if ($membership['membership_type_id'] != $this->fields['membership_type_id'][1]) {
      return TRUE;
    }

    return FALSE;
  }

}
