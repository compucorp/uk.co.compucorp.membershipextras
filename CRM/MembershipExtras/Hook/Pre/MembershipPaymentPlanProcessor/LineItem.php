<?php

use CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_AbstractProcessor as AbstractProcessor;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeAnnualCalculator as FixedPeriodTypeAnnualCalculator;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeMonthlyCalculator as FixedPeriodTypeMonthlyCalculator;
use CRM_Member_BAO_MembershipType as MembershipType;
use CRM_MembershipExtras_Service_MembershipInstalmentAmount as InstalmentAmount;

class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_LineItem extends AbstractProcessor {

  public function __construct(&$params) {
    $this->params = &$params;
    $this->assignInstalmentDetails();
  }

  /**
   * Alters the contribution 'to be created' line item parameters
   * before saving it.
   *
   * We here adjust the line total, unit price and tax amount
   * of the line item to be inline with the new contribution amount.
   */
  public function alterLineItemParameters() {
    if (isset($this->params['membership_type_id'])) {
      $lineItemMembershipType = CRM_Member_BAO_MembershipType::findById($this->params['membership_type_id']);
      //We only pro rate line item if price set is used and price field membership period type is fixed
      if ($this->isUsingPriceSet() && $lineItemMembershipType->period_type == 'fixed') {
        $this->calculateProRataLineItemAmounts($lineItemMembershipType, $this->membershipId);
      }
      else {
        $this->calculateLineItemAmounts();
      }
    }
    else {
      $this->calculateLineItemAmounts();
    }
  }

  /**
   * Calcuclates line item amounts and assign amounts to line item.
   */
  private function calculateLineItemAmounts() {
    $this->params['line_total'] = $this->calculateSingleInstalmentAmount($this->params['line_total']);
    $this->params['unit_price'] = $this->calculateSingleInstalmentAmount($this->params['unit_price']);
    if (!empty($this->params['tax_amount'])) {
      $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($this->params['tax_amount']);
    }
  }

  /**
   * Calculates pro rata amounts for line item for fixed period membership type
   *
   * @param \CRM_Member_BAO_MembershipType $membershipType
   * @param int $membershipId
   *
   */
  private function calculateProRataLineItemAmounts(MembershipType $membershipType, int $membershipId) {
    //Make sure we pro rated using line item total amount
    $membershipType->minimum_fee = $this->params['unit_price'];
    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'id' => $membershipId,
    ])['values'][0];
    if ($this->paymentPlanSchedule == CRM_MembershipExtras_Service_MembershipInstalmentsSchedule::MONTHLY) {
      $fixedPeriodTypeMonthlyCalculator = new FixedPeriodTypeMonthlyCalculator([$membershipType]);
      $fixedPeriodTypeMonthlyCalculator->setStartDate(new DateTime($membership['start_date']));
      $instalmentAmount = new InstalmentAmount($fixedPeriodTypeMonthlyCalculator);
    }
    else {
      $fixedPeriodTypeAnnualCalculator = new FixedPeriodTypeAnnualCalculator([$membershipType]);
      $fixedPeriodTypeAnnualCalculator->setStartDate(new DateTime($membership['start_date']));
      $instalmentAmount = new InstalmentAmount($fixedPeriodTypeAnnualCalculator);
    }

    $instalmentAmount->getCalculator()->calculate();
    $this->params['line_total'] = $this->calculateSingleInstalmentAmount($instalmentAmount->getCalculator()->getAmount());
    $this->params['unit_price'] = $this->calculateSingleInstalmentAmount($instalmentAmount->getCalculator()->getAmount());
    $this->params['tax_amount'] = $this->calculateSingleInstalmentAmount($instalmentAmount->getCalculator()->getTaxAmount());
  }

}
