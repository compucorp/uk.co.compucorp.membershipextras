<?php

use CRM_MembershipExtras_Setup_Configure_ConfigurerInterface as ConfigurerInterface;

/**
 * Disabling the "Contribution cancel actions" core extension,
 * To prevent CiviCRM from canceling the membership if the user
 * cancels any of its installments.
 *
 */
class CRM_MembershipExtras_Setup_Configure_DisableContributionCancelActionsExtension implements ConfigurerInterface {

  public function apply() {
    $extension = civicrm_api3('Extension', 'get', [
      'full_name' => "contributioncancelactions",
    ]);
    if (!empty($extension['id'])) {
      civicrm_api3('Extension', 'disable', [
        'keys' => 'contributioncancelactions',
      ]);
    }
  }

}
