<?php

/**
 * Implements membership creation and renewal form changes that allow
 * users to auto-renew offline memberships.
 */
class CRM_MembershipExtras_Hook_BuildForm_MembershipOfflineAutoRenew {
  /**
   * @var string
   *   Path where the template for the auto renew section is soted.
   */
  private $templatePath;

  /**
   * @var \CRM_Member_Form_Membership
   *   Form object that is being altered.
   */
  private $form;

  /**
   * CRM_MembershipExtras_Hook_BuildForm_Membership constructor.
   *
   * @param \CRM_Member_Form $form
   */
  public function __construct(CRM_Member_Form &$form) {
    $this->form = $form;
    $this->templatePath = CRM_MembershipExtras_ExtensionUtil::path() . '/templates';
  }


  /**
   * Implements changes to form loaded in $this->form
   */
  public function buildForm() {
    $this->addOfflineAutoRenewSection();
  }

  /**
   * Adds the offline auto-renew section to the form.
   */
  private function addOfflineAutoRenewSection() {
    $element = $this->form->add('checkbox', 'offline_auto_renew', ts('Auto-renew Offline?'));

    if ($this->isMembershipAlreadySetToAutoRenew()) {
      $element->_flagFrozen = TRUE;
      $this->form->add('hidden', 'membership_is_already_autorenew');
      $this->form->setDefaults(['offline_auto_renew' => 1, 'membership_is_already_autorenew' => 1]);
    }

    CRM_Core_Region::instance('page-body')->add([
      'template' => "{$this->templatePath}/CRM/Member/Form/OfflineAutoRenewSection.tpl"
    ]);
  }

  /**
   * Determines if the membership (if
   * the user is on membership renew form) is
   * already configured to be auto-renewed
   * or not.
   *
   * @return bool
   */
  private function isMembershipAlreadySetToAutoRenew() {
    $isAlreadyAutoRenew = FALSE;
    if (!empty($this->form->_id)) {
      $membership = civicrm_api3('Membership', 'get', [
        'sequential' => 1,
        'return' => ['contribution_recur_id'],
        'id' => $this->form->_id,
      ]);

      if (!empty($membership['values'][0]['contribution_recur_id'])) {
        $isAlreadyAutoRenew = TRUE;
      }
    }

    return $isAlreadyAutoRenew;
  }

}
