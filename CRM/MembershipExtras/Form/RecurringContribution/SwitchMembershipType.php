<?php

use CRM_MembershipExtras_Service_MembershipTypeSwitcher as MembershipTypeSwitcher;

/**
 * Form controller class to allow switching membership line items from
 * one type to another.
 */
class CRM_MembershipExtras_Form_RecurringContribution_SwitchMembershipType extends CRM_Core_Form {

  private $lineItemID;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->lineItemID = CRM_Utils_Request::retrieve('line_item_id', 'Positive', $this);
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    return [
      'payment_type' => MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS,
      'switch_date' => date('Y-m-d', strtotime('+1 day')),
    ];
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Upgrade Membership Type'));

    $this->assign('current_membership_type_name', $this->getCurrentMembershipTypeName());

    $this->addEntityRef('new_membership_type', ts('New Membership Type'), [
      'entity' => 'membership_type',
      'placeholder' => ts('- Select Membership Type -'),
      'select' => ['minimumInputLength' => 0],
    ], TRUE);

    $this->add('datepicker', 'switch_date', ts('Switch Date'), [], TRUE, ['time' => FALSE]);

    $this->addRadio('payment_type', ts('How should members pay for this upgrade?'), [
      MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS => ts('Charge the member at the new membership type rate for any future unpaid instalments.'),
      MembershipTypeSwitcher::PAYMENT_TYPE_ONE_OFF_PAYMENT => ts('Charge them a one off fee for this change.'),
    ], [], '<br>');

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Apply'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
        'isDefault' => FALSE,
      ],
    ]);
  }

  private function getCurrentMembershipTypeName() {
    return civicrm_api3('LineItem', 'getvalue', [
      'return' => 'label',
      'id' => $this->lineItemID,
    ]);
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $submittedValues = $this->exportValues();
    try {
      $membershipTypeSwitcher = new MembershipTypeSwitcher($this->lineItemID, $submittedValues['new_membership_type'], $submittedValues['switch_date'], $submittedValues['payment_type']);
      $membershipTypeSwitcher->switchType();

      CRM_Core_Session::setStatus(
        ts('Membership type has been changed successfully.'),
        ts('Changing Membership Type'),
        'success'
      );
    }
    catch (Exception $e) {
      CRM_Core_Session::setStatus(
        ts('The membership type could not be changed: ') . $e->getMessage(),
        ts('Changing Membership Type'),
        'error'
      );
    }
  }

}
