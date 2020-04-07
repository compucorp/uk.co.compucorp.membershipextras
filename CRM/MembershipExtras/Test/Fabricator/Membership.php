<?php

/**
 * Class CRM_MembershipExtras_Test_Fabricator_Membership.
 */
class CRM_MembershipExtras_Test_Fabricator_Membership {
  /**
   * Fabricates a membership with the given parameters.
   *
   * @param array $params
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function fabricate($params) {
    return civicrm_api3('Membership', 'create', $params)['values'][0];
  }

}
