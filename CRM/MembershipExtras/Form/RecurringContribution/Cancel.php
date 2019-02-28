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
   * ID of contact from where contributions is being cancellsd.
   *
   * @var int
   */
  private $contactID;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->id = CRM_Utils_Request::retrieve('crid', 'Positive', $this);
    $this->contactID = CRM_Utils_Request::retrieve('cid', 'Positive', $this);
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

    if ($submittedValues['cancel_memberships']) {
      $this->cancelMemberships();
    }

    if ($submittedValues['cancel_pending_installments']) {
      $this->cancelPendingInstallments();
    }

    $this->cancelRecurringContribution();
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
