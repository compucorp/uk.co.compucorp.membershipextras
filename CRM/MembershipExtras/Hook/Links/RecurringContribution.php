<?php

/**
 * Alters action links for recurring contributions.
 */
class CRM_MembershipExtras_Hook_Links_RecurringContribution {

  private $links;

  /**
   * CRM_MembershipExtras_Hook_Links_RecurringContribution constructor.
   *
   * @param array $links
   *   Array with the action links for the recurring contribution
   */
  public function __construct(&$links) {
    $this->links = &$links;
  }

  /**
   * Alters 'Cancel' action link to use a custom form, instead of CiviCRM's
   * default enable/disable weird control.
   */
  public function alterLinks() {
    foreach ($this->links as &$actionLink) {
      if ($actionLink['name'] == 'Cancel') {
        unset($actionLink['ref']);
        $actionLink['url'] = 'civicrm/recurring-contribution/cancel';
        $actionLink['qs'] = 'reset=1&crid=%%crid%%&cid=%%cid%%&context=contribution';
      }
    }
  }

}
