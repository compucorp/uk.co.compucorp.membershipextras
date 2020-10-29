<?php

class CRM_MembershipExtras_Form_AutomatedUpgradeRuleDelete extends CRM_Core_Form {

  /**
   * Upgrade rule id
   *
   * @var int
   */
  private $id;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    CRM_Utils_System::setTitle(ts('Delete Automated Membership Upgrade Rule'));

    $this->id = CRM_Utils_Request::retrieve('id', 'Positive', $this);

    $url = CRM_Utils_System::url('civicrm/admin/member/automated-upgrade-rules', 'reset=1');
    $session = CRM_Core_Session::singleton();
    $session->replaceUserContext($url);
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Delete'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ],
    ]);
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    if (!empty($this->id)) {
      CRM_MembershipExtras_BAO_AutoMembershipUpgradeRule::deleteById($this->id);

      CRM_Core_Session::setStatus(ts('Selected membership automated upgrade rule has been deleted.'), ts('Record Deleted'), 'success');
      $returnURL = CRM_Utils_System::url('civicrm/admin/member/automated-upgrade-rules', 'reset=1');
      CRM_Utils_System::redirect($returnURL);
    }
  }

}
