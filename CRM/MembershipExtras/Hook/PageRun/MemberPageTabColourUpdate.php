<?php

use CRM_MembershipExtras_Helper_MembershipTypeSettings as MembershipTypeSettings;

class CRM_MembershipExtras_Hook_PageRun_MemberPageTabColourUpdate {

  /**
   * Modifies the membership type background colour on the member page tab
   * for a contact.
   *
   * @param CRM_Core_Page $page
   */
  public function handle($page) {
    if (!$this->shouldHandle($page)) {
      return;
    }

    $this->setMembershipTypeColourStyle($page);
  }

  /**
   * Checks if this is the right page
   *
   * @param CRM_Core_Page $page
   *
   * @return bool
   */
  private function shouldHandle($page) {
    return $page instanceof CRM_Member_Page_Tab;
  }

  /**
   *  Sets background and text colour for membership types on member tab
   * for contact.
   *
   * @param CRM_Core_Page $page
   */
  private function setMembershipTypeColourStyle($page) {
    $inactiveMembers = $page->get_template_vars('inActiveMembers');
    $activeMembers = $page->get_template_vars('activeMembers');
    $allMemberships = array_merge($inactiveMembers, $activeMembers);
    $css = '';
    $membershipTypeColourSettings = Civi::settings()->get(MembershipTypeSettings::COLOUR_SETTINGS_KEY);
    $membershipTypeSettings = new MembershipTypeSettings();

    foreach ($allMemberships as $membership) {
      $membershipTypeId = $membership['membership_type_id'];
      if (!empty($membershipTypeColourSettings[$membershipTypeId])) {
        if (empty($membershipTypeColourSettings[$membershipTypeId]['set_membership_colour'])) {
          continue;
        }
        $identifier = '#crm-membership_' . $membership['membership_id'] . '  > td.crm-membership-membership_type';
        $backgroundColour = $membershipTypeColourSettings[$membershipTypeId]['membership_colour'];
        $textColour = $membershipTypeSettings->computeTextColor($backgroundColour);
        $css .= "$identifier" . "{background-color: {$backgroundColour} !important; color: {$textColour}}";
      }
    }
    CRM_Core_Resources::singleton()->addStyle($css, 10);
  }
}
