<?php

/**
 * Class CRM_MembershipExtras_Hook_ValidateForm_UpdateSubscription
 *
 * Validates Membership Type Form
 */
class CRM_MembershipExtras_Hook_ValidateForm_UpdateSubscription {

  /**
   * Form object that is being validated.
   *
   * @var \CRM_Member_Form_Membership
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
   * Array with the data of the recurring contribution that is being updated.
   *
   * @var array
   */
  private $recurringContribution;

  const INVALID_NEXT_CONTRIBUTION_DATE_MONTH = 'Not all months have more than 28 days. As such monthly or quarterly payment plans must renew on or before the 28th of the month. Please select another date on or before the 28th of the month.';
  const INVALID_NEXT_CONTRIBUTION_DATE_YEAR = 'Not all years have a 29th of Feb. As such annual payment plans must renew on any other day of the year!';

  /**
   * CRM_MembershipExtras_Hook_ValidateForm_UpdateSubscription constructor.
   *
   * @param \CRM_Contribute_Form_UpdateSubscription $form
   * @param $fields
   * @param $errors
   */
  public function __construct($form, &$fields, &$errors) {
    $this->form = $form;
    $this->fields = &$fields;
    $this->errors = &$errors;
    $this->setRecurringContribution();
  }

  /**
   * Validates the subscription form submission
   */
  public function validate() {
    $this->validateCycleDay();
    $this->validateNextContributionDate();
  }

  public function validateCycleDay() {
    $cycleDay = $this->fields['cycle_day'];
    $frequency = $this->recurringContribution['frequency_unit'];

    if ($cycleDay > 28 && $frequency === 'month') {
      $this->errors['cycle_day'] = ts(self::INVALID_NEXT_CONTRIBUTION_DATE_MONTH);
    }
  }

  private function validateNextContributionDate() {
    $frequency = $this->recurringContribution['frequency_unit'];
    $nextContributionDate = $this->fields['next_sched_contribution_date'];

    $this->validateNextContributionDateIsNotLeapYear($nextContributionDate, $frequency);
    $this->validateNextContributionDateDayIsNotBeyond28($nextContributionDate, $frequency);
  }

  private function validateNextContributionDateIsNotLeapYear(string $nextContributionDate, string $frequency) {
    $nextContribDay = date('j', strtotime($nextContributionDate));
    $nextContibMonth = date('n', strtotime($nextContributionDate));

    if ($nextContribDay > 28 && $nextContibMonth == '2' && $frequency === 'year') {
      $this->errors['next_sched_contribution_date'] = ts(self::INVALID_NEXT_CONTRIBUTION_DATE_YEAR);
    }
  }

  private function validateNextContributionDateDayIsNotBeyond28(string $nextContributionDate, string $frequency) {
    $nextContribDay = date('j', strtotime($nextContributionDate));

    if ($nextContribDay > 28 && $frequency === 'month') {
      $this->errors['next_sched_contribution_date'] = ts(self::INVALID_NEXT_CONTRIBUTION_DATE_MONTH);
    }
  }

  /**
   * Loads data for the currently edited recurring contribution.
   */
  private function setRecurringContribution() {
    $recurringContributionID = $this->form->getVar('contributionRecurID');

    $this->recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recurringContributionID,
    ])['values'][0];
  }

}
