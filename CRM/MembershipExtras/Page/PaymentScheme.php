<?php
use CRM_MembershipExtras_ExtensionUtil as E;

class CRM_MembershipExtras_Page_PaymentScheme extends CRM_Core_Page {

  public $useLivePageJS = TRUE;

  public function run() {
    CRM_Utils_System::setTitle(E::ts('Payment Schemes'));
    $schemes = CRM_MembershipExtras_BAO_PaymentScheme::getAll();
    $this->assign('rows', $schemes);
    parent::run();
  }

}
