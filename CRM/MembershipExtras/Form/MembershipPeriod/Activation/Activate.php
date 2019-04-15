<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Membership period activate form controller class.
 */
class CRM_MembershipExtras_Form_MembershipPeriod_Activation_Activate extends CRM_MembershipExtras_Form_MembershipPeriod_Activation_Base {

  /**
   * @inheritdoc
   */
  protected function setFormTitle() {
    CRM_Utils_System::setTitle(E::ts('Activate Membership Period?'));
  }

  /**
   * @inheritdoc
   */
  protected function getFormButtons() {
    return [
      [
        'type' => 'submit',
        'name' => E::ts('Activate'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
      ],
    ];
  }

  /**
   * @inheritdoc
   *
   * @throws \Exception
   */
  public function postProcess() {
    $transaction = new CRM_Core_Transaction();
    try {
      CRM_MembershipExtras_BAO_MembershipPeriod::updatePeriodAndMembership([
        'id' => $this->id,
        'is_active' => 1,
      ]);
    } catch (Exception $exception) {
      $transaction->rollback();
      CRM_Core_Session::setStatus($exception->getMessage(), 'Period Activation', 'error');

      return;
    }

    $transaction->commit();
    CRM_Core_Session::setStatus(ts('Membership period has been activated.'), 'Membership Period Activation', 'success');
  }

}
