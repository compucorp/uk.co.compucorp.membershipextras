<?php

use CRM_MembershipExtras_SettingsManager as MembershipTypeSettings;

class CRM_MembershipExtras_Hook_PageRun_MembershipTypePageColourUpdate implements CRM_MembershipExtras_Hook_PageRun_PageRunInterface {

  /**
   * Modifies the membership type background colour.
   *
   * @param CRM_Core_Page $page
   */
  public function handle($page) {
    if (!$this->shouldHandle($page)) {
      return;
    }

    $this->includeColourPickerFiles();
    $this->setMembershipTypeColourStyle();
  }

  /**
   * Sets the background and text colour for the membership type table column on the
   * membership type listing page.
   */
  private function setMembershipTypeColourStyle() {
    $membershipTypeColourSettings = Civi::settings()->get(MembershipTypeSettings::COLOUR_SETTINGS_KEY);
    $css = '';

    foreach ($membershipTypeColourSettings as $membershipTypeId => $settings) {
      $backgroundColour = $settings['membership_colour'];
      if (empty($settings['set_membership_colour'])) {
        $backgroundColour = 'inherit';
      }
      $identifier = '#membership_type-' . $membershipTypeId . '  > td.crmf-name';
      $textColour = MembershipTypeSettings::computeTextColor($backgroundColour);
      $css .= "$identifier" . "{background-color: {$backgroundColour} !important; color: {$textColour}}";
    }

    CRM_Core_Resources::singleton()->addStyle($css, 10, 'page-header');
  }

  /**
   * Checks if this is the right page
   *
   * @param CRM_Core_Page $page
   *
   * @return bool
   */
  private function shouldHandle($page) {
    return $page instanceof CRM_Member_Page_MembershipType;
  }

  /**
   * We need to include this files here also because the membership type create/edit form is loaded
   * via a modal on the membership type listing page and if the files are not added here, the colour
   * picker will not work as expected.
   */
  public function includeColourPickerFiles() {
    CRM_Core_Resources::singleton()->addScriptFile('uk.co.compucorp.membershipextras', 'js/vendor/spectrum/spectrum.min.js');
    CRM_Core_Resources::singleton()->addStyleFile('uk.co.compucorp.membershipextras', 'js/vendor/spectrum/spectrum.css');
  }

}
