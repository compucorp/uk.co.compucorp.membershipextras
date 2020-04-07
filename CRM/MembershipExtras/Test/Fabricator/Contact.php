<?php

/**
 * Class CRM_MembershipExtras_Test_Fabricator_Contact.
 */
class CRM_MembershipExtras_Test_Fabricator_Contact {
  /**
   * Fabricates a contact with the given parameters.
   *
   * @param $params
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function fabricate($params = NULL) {
    return civicrm_api3('Contacts', 'create', $params)['values'][0];
  }

}
