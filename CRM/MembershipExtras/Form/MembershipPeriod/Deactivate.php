<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Membership period activate form controller class.
 */
class CRM_MembershipExtras_Form_MembershipPeriod_Deactivate extends CRM_MembershipExtras_Form_MembershipPeriod_Base {

  /**
   * @inheritdoc
   */
  protected function setFormTitle() {
    CRM_Utils_System::setTitle(E::ts('Deactivate Membership Period?'));
  }

  /**
   * @inheritdoc
   */
  protected function getFormButtons() {
    return [
      [
        'type' => 'submit',
        'name' => E::ts('Deactivate'),
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
   */
  public function postProcess() {
    $transaction = new CRM_Core_Transaction();
    try {
      CRM_MembershipExtras_BAO_MembershipPeriod::updatePeriodAndMembership([
        'id' => $this->id,
        'is_active' => 0,
      ]);
    } catch (Exception $exception) {
      $transaction->rollback();
      CRM_Core_Session::setStatus($exception->getMessage(), 'Period Deactivation', 'error');

      return;
    }

    $transaction->commit();
    CRM_Core_Session::setStatus(ts('Membership period has been deactivated.'), 'Membership Period Deactivation', 'success');
  }

}
