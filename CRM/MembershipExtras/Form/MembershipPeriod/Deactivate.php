<?php

use CRM_MembershipExtras_ExtensionUtil as E;

/**
 * Membership period activate form controller class.
 */
class CRM_MembershipExtras_Form_MembershipPeriod_Deactivate extends CRM_Core_Form {

  /**
   * ID of the period to be activated.
   *
   * @var int
   */
  private $id;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->id = CRM_Utils_Request::retrieve('id', 'String', $this, TRUE);
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(E::ts('Deactivate Membership Period?'));

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Deactivate'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);

    parent::buildQuickForm();
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $period = new CRM_MembershipExtras_BAO_MembershipPeriod();
    $period->id = $this->id;
    $period->is_active = 0;
    $period->save();
  }

}
