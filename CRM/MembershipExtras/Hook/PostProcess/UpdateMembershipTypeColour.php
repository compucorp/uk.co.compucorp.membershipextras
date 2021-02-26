<?php

use CRM_MembershipExtras_SettingsManager as MembershipTypeSettings;

class CRM_MembershipExtras_Hook_PostProcess_UpdateMembershipTypeColour {

  /***
   * The form submitted data.
   *
   * @var CRM_Core_Form
   */
  private $form;

  /**
   * @var array
   */
  private $membershipTypeColourSettings;

  /**
   * CRM_MembershipExtras_Hook_PostProcess_UpdateMembershipTypeColour constructor.
   *
   * @param \CRM_Member_Form_MembershipType $form
   */
  public function __construct(CRM_Member_Form_MembershipType &$form) {
    $this->form = $form;
    $this->membershipTypeColourSettings = Civi::settings()->get(MembershipTypeSettings::COLOUR_SETTINGS_KEY);
  }

  /**
   * Processes the membership type colour related fields after form submission.
   */
  public function process() {
    if ($this->form->getAction() & CRM_Core_Action::DELETE) {
      $this->deleteMembershipTypeColourFields();
    }
    else {
      $this->updateMembershipTypeColourFields();
    }
  }

  /**
   * Updates the membership type colour settings using the civicrm settings
   * functionality to store the data.
   */
  private function updateMembershipTypeColourFields() {
    $membershipTypeColourSettings = $this->membershipTypeColourSettings;
    if (!$membershipTypeColourSettings) {
      $membershipTypeColourSettings = [];
    }

    $membershipTypeId = $this->getMembershipTypeId();
    $setMembershipColour = $this->form->_submitValues['set_membership_colour'];
    $membershipColour = $this->form->_submitValues['membership_colour'];
    $membershipTypeColourSettings[$membershipTypeId] = [
      'set_membership_colour' => $setMembershipColour,
      'membership_colour' => $setMembershipColour ? $membershipColour : '',
    ];

    Civi::settings()->set(MembershipTypeSettings::COLOUR_SETTINGS_KEY, $membershipTypeColourSettings);
  }

  /**
   * @throws \CiviCRM_API3_Exception
   */
  private function deleteMembershipTypeColourFields() {
    $membershipTypeId = $this->form->_id;
    $result = civicrm_api3('MembershipType', 'get', [
      'id' => $membershipTypeId,
    ]);

    if (empty($result['values'])) {
      $membershipTypeColourSettings = $this->membershipTypeColourSettings;
      unset($membershipTypeColourSettings[$membershipTypeId]);
      Civi::settings()->set(MembershipTypeSettings::COLOUR_SETTINGS_KEY, $membershipTypeColourSettings);
    }
  }

  private function getMembershipTypeId() {
    if (!empty($this->form->_id)) {
      return $this->form->_id;
    }

    return civicrm_api3('MembershipType', 'getsingle', [
      'return' => ['id'],
      'name' => $this->form->exportValues()['name'],
    ])['id'];
  }

}
