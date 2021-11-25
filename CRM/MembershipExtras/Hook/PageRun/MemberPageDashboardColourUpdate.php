<?php

use CRM_MembershipExtras_SettingsManager as MembershipTypeSettings;

class CRM_MembershipExtras_Hook_PageRun_MemberPageDashboardColourUpdate implements CRM_MembershipExtras_Hook_PageRun_PageRunInterface {

  /**
   * Modifies the membership type background colour on the member dashboard page
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
    return $page instanceof CRM_Member_Page_DashBoard;
  }

  /**
   * Sets membership typ colour styles on member dashboard page.
   *
   * @param CRM_Core_Page $page
   */
  private function setMembershipTypeColourStyle($page) {
    $rows = $page->get_template_vars('rows');

    if (empty($rows)) {
      return;
    }
    $membershipIds = array_column($rows, 'membership_id');
    $result = civicrm_api3('Membership', 'get', [
      'id' => ['IN' => $membershipIds],
      'return' => ['membership_type_id', 'id'],
    ]);

    $values = $result['values'];
    $memberships = array_column($values, 'membership_type_id', 'id');
    $membershipTypeColourSettings = Civi::settings()->get(MembershipTypeSettings::COLOUR_SETTINGS_KEY);
    $css = '';

    foreach ($memberships as $membershipId => $membershipTypeId) {
      if (!empty($membershipTypeColourSettings[$membershipTypeId])) {
        if (empty($membershipTypeColourSettings[$membershipTypeId]['set_membership_colour'])) {
          continue;
        }
        $identifier = '#rowid' . $membershipId . '  > td.crm-membership-type';
        $backgroundColour = $membershipTypeColourSettings[$membershipTypeId]['membership_colour'];
        $textColour = MembershipTypeSettings::computeTextColor($backgroundColour);
        $css .= "$identifier" . "{background-color: {$backgroundColour} !important; color: {$textColour}}";
      }
    }
    CRM_Core_Resources::singleton()->addStyle($css);
  }

}
