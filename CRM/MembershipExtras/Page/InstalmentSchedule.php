<?php

class CRM_MembershipExtras_Page_InstalmentSchedule extends CRM_Core_Page {

  public function run() {
    $this->assignInstalments();
    $this->assignCurrencySymbol();
    $this->assignTaxTerm();

    parent::run();
  }

  private function assignInstalments() {
    $membershipTypeId = CRM_Utils_Request::retrieve('membership_type_id', 'Int');
    $priceFieldValues = CRM_Utils_Request::retrieve('price_field_values', 'String');

    $params = [];
    $action = 'getbymembershiptype';
    if (isset($membershipTypeId)) {
      $params['membership_type_id'] = $membershipTypeId;
    }
    elseif (isset($priceFieldValues)) {
      $params['price_field_values'] = ['IN' => $priceFieldValues];
      $action = 'getbypricefieldvalues';
    }

    $params['schedule'] = CRM_Utils_Request::retrieve('schedule', 'String');
    $params['payment_method'] = CRM_Utils_Request::retrieve('payment_method', 'Int');
    $params['start_date'] = CRM_Utils_Request::retrieve('start_date', 'String');
    $params['join_date'] = CRM_Utils_Request::retrieve('join_date', 'String');

    try {
      $result = civicrm_api3('PaymentSchedule', $action, $params);

      $this->assign('instalments', $result['values']['instalments']);
      $this->assign('sub_total', $result['values']['sub_total']);
      $this->assign('tax_amount', $result['values']['tax_amount']);
      $this->assign('total_amount', $result['values']['total_amount']);
      $this->assign('membership_start_date', $result['values']['membership_start_date']);
      $this->assign('membership_end_date', $result['values']['membership_end_date']);

      if (isset($result['values']['prorated_number']) && isset($result['values']['prorated_unit'])) {
        $this->assign('prorated_number', $result['values']['prorated_number']);
        if ($result['values']['prorated_unit'] == CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator::BY_DAYS) {
          $this->assign('prorated_unit', ts('days'));
        }
        else {
          $this->assign('prorated_unit', ts('months'));
        }
      }
    }
    catch (CiviCRM_API3_Exception $e) {
      $errorResponse = [
        'is_error' => TRUE,
        'error_message' => $e->getMessage(),
      ];
      CRM_Core_Page_AJAX::returnJsonResponse($errorResponse);
    }
  }

  private function assignCurrencySymbol() {
    $currencySymbol = CRM_Core_BAO_Country::defaultCurrencySymbol();
    $this->assign('currency_symbol', $currencySymbol);
  }

  private function assignTaxTerm() {
    $taxTerm = civicrm_api3('Setting', 'get', [
      'sequential' => 1,
      'return' => ["tax_term"],
    ])['values'][0]['tax_term'];
    $this->assign('tax_term', $taxTerm);
  }

}
