<?php

class CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessor {

  /**
   * @var \CRM_Member_Form
   *
   * Form object that is being altered.
   */
  private $form;

  /**
   * @var int
   *
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
   * Processes the membership offline auto-renewal.
   */
  public function postProcess() {
    if (!$this->isUsingPaymentPlanOption() ||  $this->isMembershipAlreadySetToAutoRenew()) {
      return;
    }

    $recurContributionID = $this->getMembershipLastRecurContributionID();
    $this->setMembershipToAutoRenew($recurContributionID);
    $this->setRecurContributionAutoRenew($recurContributionID);
    $this->setRecurringLineItemsAsAutoRenew($recurContributionID);

  }

  /**
   * Determines if the membership is
   * already set to be auto renewed or not.
   *
   * @return mixed
   */
  private function isMembershipAlreadySetToAutoRenew() {
    $isAlreadyAutoRenew = FALSE;
    if (!empty($this->form->_id)) {
      $membership = civicrm_api3('Membership', 'get', [
        'sequential' => 1,
        'return' => ['contribution_recur_id'],
        'id' => $this->form->_id,
      ]);
      if (!empty($membership['values'][0]['contribution_recur_id'])) {
        $isAlreadyAutoRenew = TRUE;
      }
    }

    return $isAlreadyAutoRenew;
  }

  /**
   * Detects if the membership is paid for
   * using payment plan option.
   *
   * @return bool
   */
  private function isUsingPaymentPlanOption() {
    $paymentPlanSchdule = $this->formSubmittedValues['payment_plan_schedule'];
    $isSavingContribution = $this->formSubmittedValues['record_contribution'];

    if ($isSavingContribution && !empty($paymentPlanSchdule)) {
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
   * Sets the created memberships
   * contribution_recur_id fielda to refer to
   * the created auto-renew recurring contribution.
   * (Which means that the memberships
   * are now set to be auto-renewed).
   *
   * @param $recurContributionID
   */
  private function setMembershipToAutoRenew($recurContributionID) {
    foreach ($this->membershipIDs as $membershipID) {
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
    $formReflection = new ReflectionObject($this->form);
    $propertyReflection = $formReflection->getProperty($propertyName);
    $propertyReflection->setAccessible(TRUE);
    return $propertyReflection->getValue($this->form);
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
