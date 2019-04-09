<?php

class CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessor{

  /**
   * @var \CRM_Member_Form
   *
   * Form object that is being altered.
   */
  private $form;

  /**
   * The membership IDs that are created/renewed
   */
  private  $membershipIDs;

  /***
   * @var array
   *
   * Form submitted data values.
   */
  private $formSubmittedValues;

  public function __construct(&$form) {
    $this->form = $form;
    $this->formSubmittedValues = $this->form->exportValues();
    $this->membershipIDs = $this->getMembershipFormProtectedPropertyValue('_membershipIDs');
  }

  /**
   * Processes the membership new/renew form
   * to handle offline auto-renewal.
   */
  public function postProcess() {
    if (!$this->isOfflineAutoRenewMembership()) {
      return;
    }

    if ($this->membershipIsAlreadyAutoRenew()) {
      $recurContributionID = $this->getMembershipLastRecurContributionID();
      $this->updateContributionRecurringContribution($recurContributionID);
      return;
    }

    $isPaymentPlanPayment = $this->isPaymentPlanPayment();
    if ($isPaymentPlanPayment) {
      $recurContributionID = $this->getMembershipLastRecurContributionID();
    }
    else {
      $recurContributionID = $this->createAutoRenewRecurContribution();
      $this->updateContributionRecurringContribution($recurContributionID);
    }

    $this->setMembershipToAutoRenew($recurContributionID);

    if ($isPaymentPlanPayment) {
      $this->setRecurContributionAutoRenew($recurContributionID);
      $this->setRecurringLineItemsAsAutoRenew($recurContributionID);
    }
  }

  /**
   * Determines if the user selected
   * offline auto-renew option or not.
   *
   * @return mixed
   */
  private function isOfflineAutoRenewMembership() {
    return CRM_Utils_Request::retrieve('offline_auto_renew', 'Int');
  }

  /**
   * Determines if the membership is
   * already set to be auto renewed or not.
   *
   * @return mixed
   */
  private function membershipIsAlreadyAutoRenew() {
    return CRM_Utils_Request::retrieve('membership_is_already_autorenew', 'Int');
  }

  /**
   * Detects if the membership is paid for
   * using payment plan option.
   *
   * @return bool
   */
  private function isPaymentPlanPayment() {
    $installmentsCount = CRM_Utils_Request::retrieve('installments', 'Int');
    $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($isSavingContribution && $contributionIsPaymentPlan && $installmentsCount > 0) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the membership last recurring contribution ID.
   * (For Payment Plan payments)
   *
   * @return mixed
   */
  private function getMembershipLastRecurContributionID() {
    $recurContributionID = civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'return' => ['contribution_id.contribution_recur_id'],
      'membership_id' => $this->form->_id,
      'options' => ['limit' => 1, 'sort' => 'contribution_id.contribution_recur_id DESC'],
    ])['values'][0]['contribution_id.contribution_recur_id'];

    return $recurContributionID;
  }

  /**
   * Creates recurring contribution for the auto-renewed
   * membership.
   * (For contribution payment type)
   *
   * @return int
   */
  private function createAutoRenewRecurContribution() {
    $paymentInstrument = 'null';
    if (!empty($this->formSubmittedValues['payment_instrument_id'])) {
      $paymentInstrument = civicrm_api3('OptionValue', 'getvalue', [
        'return' => 'name',
        'option_group_id' => 'payment_instrument',
        'value' => $this->formSubmittedValues['payment_instrument_id'],
      ]);
    }

    $financialType = 'null';
    if (!empty($this->formSubmittedValues['financial_type_id'])) {
      $financialType = civicrm_api3('FinancialType', 'getvalue', [
        'return' => 'name',
        'id' => $this->formSubmittedValues['financial_type_id'],
      ]);
    }

    $payLaterPaymentProcessorId = CRM_MembershipExtras_SettingsManager::getDefaultProcessorID();
    $minimumFrequencyData =  $this->calculateMinimumFrequencyUnitAndInterval();

    $cycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($this->formSubmittedValues['receive_date'], $minimumFrequencyData['unit']);

    $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', [
      'sequential' => 1,
      'contact_id' => $this->form->_contactID,
      'amount' => $this->formSubmittedValues['total_amount'],
      'frequency_unit' => $minimumFrequencyData['unit'],
      'frequency_interval' => $minimumFrequencyData['interval'],
      'installments' => 'null',
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'cycle_day' => $cycleDay,
      'auto_renew' => 1,
      'payment_processor_id' => $payLaterPaymentProcessorId,
      'financial_type_id' => $financialType,
      'payment_instrument_id' => $paymentInstrument,
      'start_date' => $this->formSubmittedValues['receive_date'],
    ])['values'][0];

    return $newRecurringContribution['id'];
  }

  /**
   * Updates the contribution_recur_id field value for the
   * membership contribution if it paid using "contribution" option
   * to point to the auto-renew recurring contribution.
   *
   * @param $recurContributionID
   */
  private function updateContributionRecurringContribution($recurContributionID) {
    $lastMembershipContribution = civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'return' => ['contribution_id'],
      'membership_id' => $this->membershipIDs[0],
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ]);

    if (!empty($lastMembershipContribution['values'][0])){
      civicrm_api3('Contribution', 'create', array(
        'id' => $lastMembershipContribution['values'][0]['contribution_id'],
        'contribution_recur_id' => $recurContributionID,
      ));
    }
  }

  /**
   * If the membership is paid using PriceSet
   * that allows the creation of more than
   * one membership to be associated with
   * the same recurring contribution, Then
   * we use this method to determine the membership
   * with the type that has the minimum frequency
   * unit and interval so we can use it to set
   * the recurring contribution unit and interval.
   *
   * @return array
   *   With two keys, 'unit' and 'interval'
   */
  private function calculateMinimumFrequencyUnitAndInterval() {
    $frequencyUnitOrderMap = [
      'day'   => 1,
      'week'  => 2,
      'month' => 3,
      'year'  => 4,
    ];

    $frequencyUnitsList= [];
    $frequencyIntervalsList= [];
    $allMembershipTypeDetails = CRM_Member_BAO_Membership::buildMembershipTypeValues($this->form, array(), TRUE);
    $membershipsToBeCreatedTypes = $this->getMembershipFormProtectedPropertyValue('_memTypeSelected');
    // in renewal form there is _memType but no _memTypeSelected
    if (!$membershipsToBeCreatedTypes) {
      $membershipsToBeCreatedTypes = [$this->getMembershipFormProtectedPropertyValue('_memType')];
    }

    foreach($membershipsToBeCreatedTypes as $membershipTypeID) {
      $unitName = $allMembershipTypeDetails[$membershipTypeID]['duration_unit'];
      $frequencyUnitsList[] = $frequencyUnitOrderMap[$unitName];
      $frequencyIntervalsList[] = $allMembershipTypeDetails[$membershipTypeID]['duration_interval'];
    }

    array_multisort($frequencyUnitsList, $frequencyIntervalsList);

    return ['unit' => array_search($frequencyUnitsList[0], $frequencyUnitOrderMap), 'interval' => $frequencyIntervalsList[0]];
  }

  /**
   * Sets the created memberships
   * contribution_recur_id fielda to refer to
   * the created auto-renew recurring contribution.
   * (Which means that the memberships
   * are now set to be auto-renewed).
   *
   * @param $recurContributionID
   */
  private function setMembershipToAutoRenew($recurContributionID) {
    foreach($this->membershipIDs as $membershipID) {
      civicrm_api3('Membership', 'create', [
        'sequential' => 1,
        'id' => $membershipID,
        'contribution_recur_id' => $recurContributionID,
      ]);
    }
  }

  /**
   * Gets the value of the specified
   * protected (or private) form object
   * property.
   *
   * Where are using this to get the values
   * of some values that we need like created
   * membership IDs and their types, Without using
   * this method there will be no way to know that
   * and changing the parent class is not an
   * option currently.
   *
   * @param string $propertyName
   *
   * @return mixed
   */
  private function getMembershipFormProtectedPropertyValue($propertyName) {
    try {
      $formReflection = new ReflectionObject($this->form);
      $propertyReflection = $formReflection->getProperty($propertyName);
      $propertyReflection->setAccessible(true);
      return $propertyReflection->getValue($this->form);
    }
    catch (ReflectionException $exception) {
      return NULL;
    }
  }

  /**
   * Sets the recurring contribution auto_renew
   * field to true.
   *
   * @param $recurContributionID
   */
  private function setRecurContributionAutoRenew($recurContributionID) {
    civicrm_api3('ContributionRecur', 'create', [
      'id' => $recurContributionID,
      'auto_renew' => 1,
    ]);
  }

  /**
   * Sets recurring contribution's line items' auto_renew field to true.
   *
   * @param $recurContributionID
   */
  private function setRecurringLineItemsAsAutoRenew($recurContributionID) {
    civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurContributionID,
      'api.ContributionRecurLineItem.create' => ['id' => '$value.id', 'auto_renew' => 1],
    ]);
  }

}
