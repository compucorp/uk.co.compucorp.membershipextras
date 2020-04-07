<?php

/**
 * Class CRM_MembershipExtras_Test_Fabricator_LineItems.
 */
class CRM_MembershipExtras_Test_Fabricator_LineItem {

  /**
   * Fabricat a line item with the given parameters.
   *
   * @param $params
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  public static function fabricate($params) {
    return civicrm_api3('LineItem', 'create', $params)['values'][0];
  }

}
