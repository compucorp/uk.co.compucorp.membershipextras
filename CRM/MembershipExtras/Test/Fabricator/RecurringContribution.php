<?php

/**
 * Class CRM_MembershipExtras_Test_Fabricator_RecurringContribution.
 *
 */
class CRM_MembershipExtras_Test_Fabricator_RecurringContribution {

  /**
   * Fabricates recurring contribution with given parameters.
   *
   * @param array $params
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  public static function fabricate($params) {
    return civicrm_api3('ContributionRecur', 'create', $params)['values'][0];
  }

}
