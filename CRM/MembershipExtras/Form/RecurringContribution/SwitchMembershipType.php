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
    $tomorrowsDate = date('Y-m-d', strtotime('+1 day'));
    return [
      'payment_type' => MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS,
      'switch_date' => $tomorrowsDate,
      'scheduled_charge_date' => $tomorrowsDate,
    ];
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Upgrade Membership Type'));

    $currentMembershipType = $this->getCurrentMembershipType();
    $this->assign('current_membership_type_name', $currentMembershipType['name']);
    $this->assign('current_membership_type_id', $currentMembershipType['id']);

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

    $this->addOneOffFeeFields();

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

  private function getCurrentMembershipType() {
    $query = "
      SELECT mt.id, mt.name FROM civicrm_line_item li
      INNER JOIN civicrm_membership cm ON li.entity_id = cm.id AND li.entity_table = 'civicrm_membership'
      INNER JOIN civicrm_membership_type mt ON cm.membership_type_id = mt.id
      WHERE li.id = %1
    ";
    $result = CRM_Core_DAO::executeQuery($query, [
      1 => [$this->lineItemID, 'Integer'],
    ]);

    if (!$result->fetch()) {
      throw new CRM_Core_Exception(ts('Cannot find the membership type of the selected line item.'));
    }

    return $result->toArray();
  }

  private function addOneOffFeeFields() {
    $this->add('datepicker', 'scheduled_charge_date', ts('Scheduled Charge Date'), [], TRUE, ['time' => FALSE]);

    $this->addMoney('amount_exc_tax', ts('Amount exc Tax'), TRUE, [], FALSE);

    $financialTypes = CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes();
    $this->add('select', 'switchmembership_financial_type_id', ts('Financial Type'), $financialTypes, TRUE);

    $this->addMoney('amount_inc_tax', ts('Amount inc Tax'), FALSE, ['readonly' => TRUE], FALSE);

    $this->add('checkbox', 'switchmembership_send_confirmation_email', ts('Send confirmation email?'));
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $submittedValues = $this->exportValues();
    $oneOffFeeParams = $this->getOneOffFeeParams($submittedValues);

    try {
      $membershipTypeSwitcher = new MembershipTypeSwitcher($this->lineItemID, $submittedValues['new_membership_type'], $submittedValues['switch_date'], $submittedValues['payment_type'], $oneOffFeeParams);
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

  private function getOneOffFeeParams($submittedValues) {
    if ($submittedValues['payment_type'] == MembershipTypeSwitcher::PAYMENT_TYPE_UPDATE_PENDING_INSTALMENTS) {
      return NULL;
    }

    $sendConfirmation = CRM_Utils_Array::value('switchmembership_send_confirmation_email', $submittedValues, 0);
    return [
      'scheduled_charge_date' => $submittedValues['scheduled_charge_date'],
      'amount_exc_tax' => $submittedValues['amount_exc_tax'],
      'amount_inc_tax' => $submittedValues['amount_inc_tax'],
      'financial_type_id' => $submittedValues['switchmembership_financial_type_id'],
      'send_confirmation_email' => $sendConfirmation,
    ];
  }

}
