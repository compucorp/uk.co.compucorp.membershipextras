<?php

use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Hook_ValidateForm_UpdateSubscription as UpdateSubscription;
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;

/**
 * Class CRM_MembershipExtras_Hook_ValidateForm_UpdateSubscriptionTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_ValidateForm_UpdateSubscriptionTest extends BaseHeadlessTest {

  /**
   * Form object that is being validated.
   *
   * @var \CRM_Contribute_Form_UpdateSubscription
   */
  private $form = NULL;

  /**
   * Array of errors.
   *
   * @var array
   */
  private $errors;

  public function setUp() {
    $this->setupUpdateSubscriptionForm();
    $this->errors = [];
  }

  public function testErrorIsThrownWhenNextContribDateDayIsAbove28ForMonthlyMembership() {
    $fields = [];
    $recurringContribution = $this->createRecurContribution();
    $this->form->setVar('contributionRecurID', $recurringContribution['id']);
    $fields['next_sched_contribution_date'] = '2020-12-31';
    $fields['cycle_day'] = $recurringContribution['cycle_day'];

    $updateSubscriptionValidation = new UpdateSubscription($this->form, $fields, $this->errors);
    $updateSubscriptionValidation->validate();

    $this->assertArrayHasKey('next_sched_contribution_date', $this->errors);
    $this->assertEquals($this->errors['next_sched_contribution_date'], UpdateSubscription::INVALID_NEXT_CONTRIBUTION_DATE_MONTH);
  }

  public function testErrorIsNotThrownWhenNextContribDateDayIsBelow29ForMonthlyMembership() {
    $fields = [];
    $recurringContribution = $this->createRecurContribution();
    $this->form->setVar('contributionRecurID', $recurringContribution['id']);
    $fields['next_sched_contribution_date'] = '2020-12-28';
    $fields['cycle_day'] = $recurringContribution['cycle_day'];

    $updateSubscriptionValidation = new UpdateSubscription($this->form, $fields, $this->errors);
    $updateSubscriptionValidation->validate();

    $this->assertArrayNotHasKey('next_sched_contribution_date', $this->errors);
  }

  public function testErrorIsThrownWhenNextContribDateIsLeapDateForAnnualMembership() {
    $fields = [];
    $recurringContribution = $this->createRecurContribution(['frequency_unit' => 'year']);
    $this->form->setVar('contributionRecurID', $recurringContribution['id']);
    $fields['next_sched_contribution_date'] = '2020-02-29';
    $fields['cycle_day'] = $recurringContribution['cycle_day'];

    $updateSubscriptionValidation = new UpdateSubscription($this->form, $fields, $this->errors);
    $updateSubscriptionValidation->validate();

    $this->assertArrayHasKey('next_sched_contribution_date', $this->errors);
    $this->assertEquals($this->errors['next_sched_contribution_date'], UpdateSubscription::INVALID_NEXT_CONTRIBUTION_DATE_YEAR);
  }

  public function testErrorIsThrownWhenCycleDayIsAbove28ForMonthlyMembership() {
    $fields = [];
    $recurringContribution = $this->createRecurContribution(['frequency_unit' => 'month']);
    $this->form->setVar('contributionRecurID', $recurringContribution['id']);
    $fields['next_sched_contribution_date'] = $recurringContribution['next_sched_contribution_date'];
    $fields['cycle_day'] = 29;

    $updateSubscriptionValidation = new UpdateSubscription($this->form, $fields, $this->errors);
    $updateSubscriptionValidation->validate();

    $this->assertArrayHasKey('cycle_day', $this->errors);
    $this->assertEquals($this->errors['cycle_day'], UpdateSubscription::INVALID_NEXT_CONTRIBUTION_DATE_MONTH);
  }

  public function setUpUpdateSubscriptionForm() {
    $controller = new CRM_Core_Controller();
    $this->form = new CRM_Contribute_Form_UpdateSubscription();
    $this->form->controller = $controller;
  }

  public function createRecurContribution($params = []) {
    $contact = ContactFabricator::fabricate();
    $params = array_merge([
      'sequential' => 1,
      'contact_id' => $contact['id'],
      'amount' => 0,
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 12,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'financial_type_id' => 'Member Dues',
      'payment_instrument_id' => 'EFT',
      'start_date' => date('Y-m-d'),
    ], $params);

    $recurringContribution = RecurringContributionFabricator::fabricate($params);

    return $recurringContribution;
  }

  public function tearDown() {
    $this->form = NULL;
    $this->errors = [];
  }

}
