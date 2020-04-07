<?php

/**
 * Class CRM_MembershipExtras_Test_Fabricator_Contribution.
 */
class CRM_MembershipExtras_Test_Fabricator_Contribution {

  /**
   * Fabricates a contribution with given parameters.
   *
   * @param $params
   *
   * @return \CRM_Contribute_BAO_Contribution
   * @throws \CRM_Core_Exception
   */
  public static function fabricate($params) {
    $contribution = CRM_Contribute_BAO_Contribution::create($params);

    $contributionSoftParams = CRM_Utils_Array::value('soft_credit', $params);
    if (!empty($contributionSoftParams)) {
      $contributionSoftParams['contribution_id'] = $contribution->id;
      $contributionSoftParams['currency'] = $contribution->currency;
      $contributionSoftParams['amount'] = $contribution->total_amount;
      CRM_Contribute_BAO_ContributionSoft::add($contributionSoftParams);
    }

    return $contribution;
  }

}
