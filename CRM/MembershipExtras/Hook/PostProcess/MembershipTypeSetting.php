<?php

use CRM_MembershipExtras_SettingsManager as SettingsManager;

/**
 * Class CRM_MembershipExtras_Hook_PostProcess_MembershipTypeSetting
 */
class CRM_MembershipExtras_Hook_PostProcess_MembershipTypeSetting {

  /***
   * The form submitted data.
   *
   * @var CRM_Core_Form
   */
  private $form;

  /**
   * @var array
   */
  private $settings;

  /**
   *
   * @param \CRM_Member_Form_MembershipType $form
   */
  public function __construct(CRM_Member_Form_MembershipType &$form) {
    $this->form = $form;
    $this->settings = Civi::settings()->get(SettingsManager::MEMBERSHIP_TYPE_SETTINGS_KEY);
  }

  /**
   * Post Processing form based on form action
   */
  public function process() {
    if ($this->form->getAction() & CRM_Core_Action::DELETE) {
      $this->deleteMembershipTypeSettings();
    }
    else {
      $this->updateMembershipTypeSettings();
    }
  }

  /**
   * Update settings from submit values
   */
  private function updateMembershipTypeSettings() {
    if (!$this->settings) {
      $this->settings = [];
    }

    if (!empty($this->form->_id)) {
      $annualProRataCalculationValue = $this->form->_submitValues['membership_type_annual_pro_rata_calculation'];
      $this->settings[$this->form->_id]  = [
        'membership_type_annual_pro_rata_calculation' => $annualProRataCalculationValue,
      ];
      Civi::settings()->set(SettingsManager::MEMBERSHIP_TYPE_SETTINGS_KEY, $this->settings);
    }
  }

  /**
   * Delete settings
   */
  private function deleteMembershipTypeSettings() {
    $membershipTypeId = $this->form->_id;

    if ($membershipTypeId) {
      unset($this->settings[$membershipTypeId]);
      Civi::settings()->set(SettingsManager::MEMBERSHIP_TYPE_SETTINGS_KEY, $this->settings);
    }
  }

}
