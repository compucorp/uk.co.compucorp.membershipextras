<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Membership period activate form controller class.
 */
class CRM_MembershipExtras_Form_MembershipPeriod_Activate extends CRM_MembershipExtras_Form_MembershipPeriod_Base {

  /**
   * @inheritdoc
   */
  protected function setFormTitle() {
    CRM_Utils_System::setTitle(E::ts('Activate Membership Period?'));
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $period = new CRM_MembershipExtras_BAO_MembershipPeriod();
    $period->id = $this->id;
    $period->is_active = 1;
    $period->save();
  }
  
}
