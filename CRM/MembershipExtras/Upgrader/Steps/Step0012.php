<?php

class CRM_MembershipExtras_Upgrader_Steps_Step0012 {

  /**
   *
   * @return void
   */
  public function apply() {
    $this->SetDefaultSupportedPaymentProcessor();
  }

  private function SetDefaultSupportedPaymentProcessor() {
    $configureSupportedProcessor = new CRM_MembershipExtras_Setup_Configure_SetDefaultSupportedPaymentProcessor();
    $configureSupportedProcessor->apply();
  }

}
