<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator as MembershipInstalmentTaxAmountCalculator;

abstract class CRM_MembershipExtras_Service_MembershipPeriodType_AbstractBaseTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;

  protected function fabricateMembeshipType($params = []) {
    $defaultMembershipTypeParams = [
      'duration_unit' => 'year',
      'period_type' => 'fixed',
      'duration_interval' => 1,
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
      'domain_id' => 1,
      'member_of_contact_id' => 1,
      'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      'minimum_fee' => 120,
    ];
    $params = array_merge($defaultMembershipTypeParams, $params);
    $membershipType = MembershipTypeFabricator::fabricate($params);
    return CRM_Member_BAO_MembershipType::findById($membershipType['id']);
  }

  protected function getTaxAmount($membershipType, $amount = NULL) {
    $taxCalculator = new MembershipInstalmentTaxAmountCalculator();

    return $taxCalculator->calculateByMembershipType($membershipType, $amount);
  }

}
