<?php


trait CRM_MembershipExtras_Test_Helper_PaymentMethodTrait {

  private function getPaymentMethodValue($name = 'EFT') {
    return civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => "payment_instrument",
      'name' => $name,
    ])['values'][0]['value'];
  }

}
