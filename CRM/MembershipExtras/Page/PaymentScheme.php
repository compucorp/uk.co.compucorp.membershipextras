<?php
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_Page_PaymentScheme extends CRM_Core_Page {

  public $useLivePageJS = TRUE;

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Payment Schemes'));
    $schemes = CRM_MembershipExtras_BAO_PaymentScheme::getAll();
    $paymentProcessors = civicrm_api3('PaymentProcessor', 'get', [
      'return' => ["name"],
      'options' => ['limit' => 0],
    ])['values'];
    foreach ($schemes as $key => $value) {
      $schemes[$key]["payment_processor"] = $paymentProcessors[$value["payment_processor"]]['name'];
    }
    $this->assign('rows', $schemes);

    $permissionLabels = [
      'public' => ts('Public'),
      'admin' => ts('Admin'),
    ];
    $this->assign('permissionLabels', $permissionLabels);

    parent::run();
  }

}
