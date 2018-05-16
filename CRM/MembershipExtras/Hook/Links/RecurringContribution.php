<?php

/**
 * Alters action links for recurring contributions.
 */
class CRM_MembershipExtras_Hook_Links_RecurringContribution {

  /**
   * Alters 'Cancel' action link to use a custom form, instead of CiviCRM's
   * default enable/disable weird control.
   *
   * @param array $links
   */
  public function alterLinks(&$links) {
    foreach ($links as &$actionLink) {
      if ($actionLink['name'] == 'Cancel') {
        unset($actionLink['ref']);
        $actionLink['url'] = 'civicrm/recurring-contribution/cancel';
        $actionLink['qs'] = 'reset=1&crid=%%crid%%&cid=%%cid%%&context=contribution';
      }
    }
  }

}
