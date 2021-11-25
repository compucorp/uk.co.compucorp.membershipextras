<?php


interface CRM_MembershipExtras_Service_MembershipPeriodType_PeriodTypeCalculatorInterface {

  public function calculate();

  public function getAmount();

  public function getTaxAmount();

  public function getTotalAmount();

  public function getLineItems();

}
