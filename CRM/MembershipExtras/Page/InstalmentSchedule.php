<?php
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_Page_InstalmentSchedule extends CRM_Core_Page {

  public function run() {
    $this->assignInstalments();
    $this->assignCurrencySymbol();

    parent::run();
  }

  private function assignInstalments() {
    $startDate = CRM_Utils_Request::retrieve('start_date', 'String');
    $joinDate = CRM_Utils_Request::retrieve('join_date', 'String');
    $endDate = CRM_Utils_Request::retrieve('end_date', 'String');
    $schedule = CRM_Utils_Request::retrieve('schedule', 'String');
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

    $params['schedule'] = $schedule;
    $params['start_date'] = $startDate;
    $params['join_date'] = $joinDate;
    $params['end_date'] = $endDate;

    try {
      $result = civicrm_api3('PaymentSchedule', $action, $params);
      $this->assign('instalments', $result['values']['instalments']);
      $this->assign('total_amount', $result['values']['total_amount']);
      $this->assign('membership_start_date', $result['values']['membership_start_date']);
      $this->assign('membership_end_date', $result['values']['membership_end_date']);
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

}
