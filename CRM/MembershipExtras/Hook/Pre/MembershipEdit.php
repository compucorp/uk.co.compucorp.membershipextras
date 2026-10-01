<?php

use CRM_MembershipExtras_Service_MembershipEndDateCalculator as MembershipEndDateCalculator;
use CRM_MembershipExtras_Service_SupportedPaymentProcessors as SupportedPaymentProcessors;
use CRM_MembershipExtras_ExtensionUtil as ExtensionUti;

/**
 * Implements hook to be run before a membership is created/edited.
 */
class CRM_MembershipExtras_Hook_Pre_MembershipEdit {

  /**
   * Parameters that will be used to create the membership.
   *
   * @var array
   */
  private $params;

  /**
   * ID of the membership.
   *
   * @var int
   */
  private $id;

  /**
   * The membership payment contribution ID.
   *
   * @var int
   */
  private $paymentContributionID;

  /**
   * The payment type e.g. refund or owed
   *
   * see CRM_Contribute_Form_AdditionalPayment::getPaymentType
   *
   * @var string
   */
  private $paymentType;

  /**
   * We don't want to extend the same membership
   * more than one time if for whatever reason
   * this hook get called more than one time
   * during the same session, so here
   * we keep the list of already extended
   * memberships to achieve that.
   *
   * @var array
   */
  private static $extendedMemberships = [];

  /**
   * The membership activity status
   *
   * @var string
   */
  private $membershipActivityStatus;

  public function __construct($id, &$params, $contributionID, $paymentType) {
    $this->id = $id;
    $this->params = &$params;
    $this->paymentContributionID = $contributionID;
    $this->paymentType = $paymentType;
  }

  /**
   * Preprocesses parameters used for Membership operations.
   */
  public function preProcess() {
    $preventMembershipDateExtension = (
        $this->paymentContributionID ||
        $this->isRecordingPayment() ||
        $this->isBulkStatusUpdate() ||
        $this->editedFromPaymentAPIContext()
      ) && $this->isSupportedPaymentPlanMembership();

    $null = NULL;
    $contributionId = (int) $this->paymentContributionID;

    CRM_Utils_Hook::singleton()->invoke(
      ['preventMembershipDateExtension', 'contributionId'],
      $preventMembershipDateExtension, $contributionId, $null, $null, $null, $null,
      'membershipextras_preventMembershipDateExtension'
    );

    if ($preventMembershipDateExtension) {
      $this->preventExtendingPaymentPlanMembership();
    }

    if (!in_array($this->id, self::$extendedMemberships)) {
      $isPaymentPlan = $this->isPaymentPlanBeingRecordedOnForm();
      $isMembershipRenewal = CRM_Utils_Request::retrieve('action', 'String') & CRM_Core_Action::RENEW;
      if ($isMembershipRenewal && $isPaymentPlan) {
        self::$extendedMemberships[] = $this->id;
        $this->extendPendingPaymentPlanMembershipOnRenewal();
      }
    }

    if ($this->isSupportedPaymentPlanMembership()) {
      $this->verifyMembershipStartDate();
    }
  }

  private function isRecordingPayment() {
    $paymentRecordingDetails = $this->parsePaymentRecordingInformation();

    if (empty($paymentRecordingDetails)) {
      return FALSE;
    }

    $isAddAction = FALSE;
    if (!empty($paymentRecordingDetails['action']) && $paymentRecordingDetails['action'] == 'add') {
      $isAddAction = TRUE;
    }

    $contributionId = NULL;
    if (!empty($paymentRecordingDetails['id'])) {
      $contributionId = $paymentRecordingDetails['id'];
    }

    $isRecordPayment = $this->paymentType === 'owed';

    if ($isAddAction && $contributionId && $isRecordPayment) {
      $this->paymentContributionID = $contributionId;

      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks the request to see if a bulk status update is being done.
   *
   * @return bool
   * @throws \CRM_Core_Exception
   */
  private function isBulkStatusUpdate() {
    $statusNext = CRM_Utils_Request::retrieve('_qf_Status_next', 'String');
    $contributionStatusID = CRM_Utils_Request::retrieve('contribution_status_id', 'String');
    $currentPath = CRM_Utils_System::currentPath();

    if (stripos($currentPath, 'civicrm/contribute/search') !== FALSE && $statusNext === 'Update Pending Status' && !empty($contributionStatusID)) {
      return TRUE;
    }

    return FALSE;
  }

  private function parsePaymentRecordingInformation() {
    $recordPaymentEntryURL = CRM_Utils_Request::retrieve('entryURL', 'String');
    $recordPaymentEntryURL = html_entity_decode($recordPaymentEntryURL);

    $urlParts = parse_url($recordPaymentEntryURL);

    if (!empty($urlParts['query'])) {
      parse_str($urlParts['query'], $urlParams);
      return $urlParams;
    }

    return [];
  }

  /**
   * Is this membership is being edited from
   * Payment.create API context ?
   *
   * This method works with this class
   * CRM_MembershipExtras_Hook_Config_APIWrapper_PaymentAPI
   * that sets `paymentApiCalled` flag if Payment.Create is being
   * called, and if it is the case then we prevent extending
   * the membership.
   *
   * @return bool
   */
  private function editedFromPaymentAPIContext() {
    if (!empty(Civi::$statics[ExtensionUti::LONG_NAME]['paymentApiCalled'])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Prevents extending supported payment plan Membership.
   *
   * If a membership price will be paid using
   * payment plan then each time an installment get
   * paid the membership will get extended.
   * For example if you have 12 installments for
   * a 1 year membership, then each time an
   * installment get paid the membership will get extended
   * by one year, this method prevent civicrm from doing that
   * so the membership gets only extended once when you renew it.
   */
  public function preventExtendingPaymentPlanMembership() {
    unset($this->params['end_date']);
    $this->preventCreatingRenewalActivity();
  }

  /**
   * Prevents creating membership renew activity.
   *
   * If we are not extending the payment plan membership
   * we should also prevent CiviCRM from
   * creating renewal activity.
   */
  public function preventCreatingRenewalActivity() {
    if (isset($this->params['membership_activity_status'])) {
      $this->membershipActivityStatus = $this->params['membership_activity_status'];
      unset($this->params['membership_activity_status']);
    }

  }

  /**
   * Determines if the payment for a membership
   * subscription is paid by a supported payment
   * processor.
   *
   * @return bool
   */
  private function isSupportedPaymentPlanMembership() {
    $recContributionID = $this->getMembershipRecurringContributionID();

    if ($recContributionID === NULL) {
      return FALSE;
    }

    $recurringContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $recContributionID,
    ])['values'][0];

    $isSupportedPaymentProcessor = SupportedPaymentProcessors::isSupportedPaymentProcessor($recurringContribution['payment_processor_id']);
    if ($isSupportedPaymentProcessor) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Gets the associated recurring contribution ID for
   * the membership payment(contribution) if it does exist.
   *
   * @return int|null
   *   The recurring contribution ID or NULL
   *   if no recurring contribution exist.
   */
  private function getMembershipRecurringContributionID() {
    if (!empty($this->paymentContributionID)) {
      return $this->getRecurringContributionIDFromContributionID($this->paymentContributionID);
    }

    return $this->getRecurringContributionFromMembership($this->id);
  }

  /**
   * Obtains recurring contribution from the membership.
   *
   * @param $membershipID
   *
   * @return mixed|null
   * @throws \CRM_Core_Exception
   */
  private function getRecurringContributionFromMembership($membershipID) {
    $membership = civicrm_api3('Membership', 'getsingle', [
      'sequential' => 1,
      'id' => $membershipID,
      'options' => ['limit' => 0],
    ]);
    $recurringContributionID = CRM_Utils_Array::value('contribution_recur_id', $membership, NULL);
    if (!empty($recurringContributionID)) {
      return $recurringContributionID;
    }

    $result = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'entity_id' => $membershipID,
      'contribution_id' => ['IS NULL' => 1],
      'options' => ['limit' => 0],
      'api.ContributionRecurLineItem.get' => [
        'sequential' => 1,
        'line_item_id' => '$value.id',
        'options' => ['limit' => 0],
      ],
    ]);

    if ($result['count'] < 1) {
      return NULL;
    }

    $line = $result['values'][0];
    if ($line['api.ContributionRecurLineItem.get']['count'] < 1) {
      return NULL;
    }

    $recurringLine = $line['api.ContributionRecurLineItem.get']['values'][0];

    return $recurringLine['contribution_recur_id'];
  }

  /**
   * Obtains recurring contribution ID from the contribution.
   *
   * @param int $contributionID
   *
   * @return int|null
   * @throws \CRM_Core_Exception
   */
  private function getRecurringContributionIDFromContributionID($contributionID) {
    $paymentContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $contributionID,
      'return' => ['id', 'contribution_recur_id'],
    ]);

    if (empty($paymentContribution['values'][0]['contribution_recur_id'])) {
      return NULL;
    }

    return $paymentContribution['values'][0]['contribution_recur_id'];
  }

  /**
   * Determines if the membership is paid using payment plan option.
   *
   * @return bool
   * @throws \CRM_Core_Exception
   */
  private function isPaymentPlanBeingRecordedOnForm() {
    $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($isSavingContribution && $contributionIsPaymentPlan) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   *
   * Extends the payment plan membership
   * for renewal.
   *
   */
  public function extendPendingPaymentPlanMembershipOnRenewal() {
    $pendingStatusValue = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
    $isPaymentPending = (CRM_Utils_Request::retrieve('contribution_status_id', 'String') === $pendingStatusValue);
    if (!$isPaymentPending) {
      return;
    }

    $this->params['end_date'] = MembershipEndDateCalculator::calculate($this->id);
    if ($this->membershipActivityStatus) {
      $this->params['membership_activity_status'] = $this->membershipActivityStatus;
    }
  }

  /**
   * Checks if the start_date set in $params array is changed and if so, if it
   * makes sense within payment plan. If not, it corrects it.
   */
  private function verifyMembershipStartDate() {
    if (empty($this->params['start_date']) || $this->startDateSetInForm()) {
      return;
    }

    $membershipStartDate = $this->getCurrentMembershipStartDate();
    if ($this->params['start_date'] == $membershipStartDate) {
      return;
    }

    $this->params['start_date'] = $membershipStartDate;
  }

  /**
   * Checks if start_date has been sent on a form by checkin if the value is on
   * the request.
   *
   * @return bool
   */
  private function startDateSetInForm() {
    try {
      $startDateFromForm = CRM_Utils_Request::retrieve('start_date', 'Date');
    }
    catch (CRM_Core_Exception $e) {
      return FALSE;
    }

    if (empty($startDateFromForm)) {
      return FALSE;
    }

    $formDate = new Date($startDateFromForm);
    $paramsDate = new Date($this->params['start_date']);

    if ($formDate === $paramsDate) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Returns start_date of membership to be updated.
   *
   * @return string
   */
  private function getCurrentMembershipStartDate() {
    try {
      $membership = civicrm_api3('Membership', 'getsingle', [
        'sequential' => 1,
        'id' => $this->id,
      ]);
    }
    catch (Exception $e) {
      return '';
    }

    return $membership['start_date'];
  }

}
