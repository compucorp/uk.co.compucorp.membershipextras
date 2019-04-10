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
  public function postProcess() {
    $transaction = new CRM_Core_Transaction();
    try {
      CRM_MembershipExtras_BAO_MembershipPeriod::create([
        'id' => $this->id,
        'is_active' => 0,
      ]);
    }
    catch (Exception $exception) {
      $transaction->rollback();
      throw $exception;
    }
    $transaction->commit();
  }

}
