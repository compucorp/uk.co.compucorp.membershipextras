<?php

/**
 * Class CRM_MembershipExtras_Test_Fabricator_RecurringLineItems.
 */
class CRM_MembershipExtras_Test_Fabricator_RecurringLineItem {

  /**
   * Fabricates a recurring line item with the given parameters.
   *
   * @param array $params
   *
   * @return \CRM_MembershipExtras_DAO_ContributionRecurLineItem|NULL
   */
  public static function fabricate($params) {
    return CRM_MembershipExtras_BAO_ContributionRecurLineItem::create($params);
  }

}
