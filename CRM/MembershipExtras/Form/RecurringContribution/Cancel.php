<?php

/**
 * QuickForm used to cancel memberships.
 */
class CRM_MembershipExtras_Form_RecurringContribution_Cancel extends CRM_Core_Form {

  /**
   * ID of recurring contribution to be cancelled.
   *
   * @var int
   */
  private $id;

  /**
   * The recurring contribution to be cancelled data.
   * @var array
   */
  private $recurContribution;

  /**
   * ID of contact from where contributions is being cancelled.
   *
   * @var int
   */
  private $contactID;

  /**
   * @var bool
   */
  private $isOfflinePaymentProcessor;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->id = CRM_Utils_Request::retrieve('crid', 'Positive', $this);
    $this->contactID = CRM_Utils_Request::retrieve('cid', 'Positive', $this);

    $this->recurContribution = \Civi\Api4\ContributionRecur::get()
      ->addSelect('payment_processor_id', 'payment_processor_id:name', 'custom.*')
      ->addWhere('id', '=', $this->id)
      ->addOrderBy('id', 'DESC')
      ->execute()
      ->first();

    // These two payment processors are special case given they are both are not external payment processors.
    $isOfflinePaymentProcessor = in_array($this->recurContribution['payment_processor_id:name'], ['Offline Recurring Contribution', 'Direct Debit']);
    $this->assign('isOfflinePaymentProcessor', $isOfflinePaymentProcessor);
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Payment Plan Settings'));

    $this->add(
      'checkbox',
      'cancel_pending_installments',
      ts('Do you wish to cancel any pending instalment contribution?'),
      '',
      FALSE
    );

    $this->add(
      'checkbox',
      'cancel_memberships',
      ts('Do you wish to cancel any linked membership?'),
      '',
      FALSE
    );

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Yes'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('No'),
      ],
    ]);
  }

  /**
   * @@inheritdoc
   */
  public function postProcess() {
    $submittedValues = $this->controller->exportValues($this->_name);

    if (!$this->isOfflinePaymentProcessor) {
      $isProcessedExternallySuccessfully = $this->invokePreRecurContributionCancellationHook();
      if ($isProcessedExternallySuccessfully === FALSE) {
        CRM_Core_Session::setStatus(ts('An error occurred while trying to cancel this recurring contribution.'), ts('Cancellation Failed'), 'error');
        return;
      }
    }

    $transaction = new CRM_Core_Transaction();
    try {
      if ($submittedValues['cancel_memberships']) {
        $this->cancelMemberships();
      }

      if ($submittedValues['cancel_pending_installments']) {
        $this->cancelPendingInstallments();
      }

      $this->cancelRecurringContribution();

      $transaction->commit();
    }
    catch (Exception $e) {
      $transaction->rollback();
      CRM_Core_Session::setStatus(ts('An error occurred while trying to cancel this recurring contribution: ') . ':' . $e->getMessage(), ts('Cancellation Failed'), 'error');
    }
  }

  private function invokePreRecurContributionCancellationHook() {
    $nullObject = CRM_Utils_Hook::$_nullObject;
    $isProcessedExternallySuccessfully = FALSE;
    CRM_Utils_Hook::singleton()->invoke(
      ['recurContribution', 'isProcessedExternallySuccessfully'],
      $this->recurContribution, $isProcessedExternallySuccessfully,
      $nullObject, $nullObject, $nullObject, $nullObject,
      'membershipextras_preRecurContributionCancellation'
    );

    return $isProcessedExternallySuccessfully;
  }

  /**
   * Cancels memberships being payed for with the current recurring
   * contribution.
   */
  private function cancelMemberships() {
    civicrm_api3('MembershipPayment', 'get', [
      'sequential' => 1,
      'contribution_id.contribution_recur_id.id' => $this->id,
      'options' => ['limit' => 0],
      'api.Membership.create' => [
        'id' => '$value.membership_id',
        'is_override' => 1,
        'status_override_end_date' => '',
        'status_id' => 'Cancelled',
      ],
    ]);
  }

  /**
   * Cancels pending contributions associated to current recurring contribution.
   */
  private function cancelPendingInstallments() {
    civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->id,
      'contact_id' => $this->contactId,
      'contribution_status_id' => 'Pending',
      'options' => ['limit' => 0],
      'api.Contribution.create' => array(
        'id' => '$value.id',
        'contribution_status_id' => 'Cancelled',
        'cancel_date' => date('Y-m-d H:i:s'),
        'cancel_reason' => 'Cancelled because related recurring contribution was cancelled.',
      ),
    ]);
  }

  /**
   * Cancels current recurring contribution.
   */
  private function cancelRecurringContribution() {
    civicrm_api3('ContributionRecur', 'cancel', array(
      'id' => $this->id,
    ));
  }

}
