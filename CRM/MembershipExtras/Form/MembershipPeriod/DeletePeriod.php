<?php

use CRM_MembershipExtras_BAO_MembershipPeriod as MembershipPeriod;

class CRM_MembershipExtras_Form_MembershipPeriod_DeletePeriod extends CRM_Core_Form {

  private $id;

  public function preProcess() {
    $this->id = CRM_Utils_Request::retrieve('id', 'String', $this, TRUE);

    parent::preProcess();
  }

  public function buildQuickForm() {
    $this->assign('isTheOnlyPeriodOfMembership', MembershipPeriod::isTheOnlyPeriodOfMembership($this->id));

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

  public function postProcess() {
    try {
      MembershipPeriod::deleteById($this->id);

      $message = 'The membership period is removed successfully.';
      CRM_Core_Session::setStatus($message, '', 'success');
    }
    catch (CRM_Core_Exception $exception) {
      CRM_Core_Session::setStatus($exception->getMessage(), '', 'error');
    }
  }

}
