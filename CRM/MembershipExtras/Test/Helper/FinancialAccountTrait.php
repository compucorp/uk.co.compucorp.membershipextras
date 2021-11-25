<?php


trait CRM_MembershipExtras_Test_Helper_FinancialAccountTrait {

  /**
   * This function helps to mock Sale Tax financial account
   * for simulating the financial tax for given financial type
   *
   * @param int $taxRate
   * @param string $financialTypeName
   *
   * @throws CiviCRM_API3_Exception
   */
  protected function mockSalesTaxFinancialAccount($taxRate = 20, $financialTypeName = 'Member Dues') {
    $existingRecordResponse = civicrm_api3('FinancialAccount', 'get', [
      'sequential' => 1,
      'options' => ['limit' => 1],
      'name' => 'Sales Tax',
    ]);

    if (empty($existingRecordResponse['id'])) {
      $financialAccount = CRM_MembershipExtras_Test_Fabricator_FinancialAccount::fabricate([
        'name' => 'Sales Tax',
        'contact_id' => 1,
        'financial_account_type_id' => 'Liability',
        'accounting_code' => 5500,
        'is_header_account' => 0,
        'is_deductible' => 1,
        'is_tax' => 1,
        'tax_rate' => $taxRate,
        'is_active' => 1,
        'is_default' => 0,
      ]);

      CRM_MembershipExtras_Test_Fabricator_EntityFinancialAccount::fabricate([
        'entity_table' => 'civicrm_financial_type',
        'account_relationship' => 'Sales Tax Account is',
        'financial_account_id' => $financialAccount['id'],
        'entity_id' => $this->getFinancialTypeID($financialTypeName),
      ]);
    }

  }

  /**
   * Gets financial type ID from given financial type name.
   *
   * @param $financialTypeName
   *
   * @return mixed
   * @throws CiviCRM_API3_Exception
   */
  protected function getFinancialTypeID($financialTypeName) {
    $financialType = civicrm_api3('FinancialType', 'get', [
      'name' => $financialTypeName,
      'sequential' => 1,
    ]);

    if ($financialType['count'] == 0) {
      return CRM_MembershipExtras_Test_Fabricator_FinancialType::fabricate([
        'sequential' => 1,
        'name' => $financialTypeName,
      ])['id'];
    }

    return $financialType['values'][0]['id'];
  }

}
