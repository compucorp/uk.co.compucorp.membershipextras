<?php

/**
 * Implements hook to be run before a membership is created/edited.
 */
class CRM_MembershipExtras_Hook_Pre_Membership {

  /**
   * Parameters that will be used to create the membership.
   *
   * @var array
   */
  private $params;

  /**
   * Action being performed on the membership.
   *
   * @var string
   */
  private $op;

  /**
   * ID of the membership.
   *
   * @var int
   */
  private $id;

  /**
   * ID of the contribution that was created before the action was called for
   * the membership.
   *
   * @var int
   */
  private $contributionID;

  public function __construct($op, $id, &$params, $contributionID) {
    $this->op = $op;
    $this->id = $id;
    $this->params = &$params;
    $this->contributionID = $contributionID;
  }

  /**
   * Preprocesses parameters used for Membership operations.
   */
  public function preProcess() {
    switch ($this->op) {
      case 'create':
        $this->preProcessCreation();
        break;

      case 'edit':
        $this->preProcessEdit();
        break;
    }
  }

  /**
   * Fixes parameters used to create a membership.
   */
  private function preProcessCreation() {
    $this->fixTaxAmount();
    $this->fixLineItems();
  }

  /**
   * Recalculates tax amount tax rate according to selected financial type, as
   * this can be broken if paying using payment plan or altering total value.
   */
  private function fixTaxAmount() {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = CRM_Utils_Array::value($this->params['financial_type_id'], $taxRates, 0);

    $this->params['tax_amount'] = ($this->params['total_amount'] * ($rate / 100)) / (1 + ($rate / 100));
  }

  /**
   * Recalculates line total, unit price and tax amount for each line item, as
   * these can get broken when paying with a pyment plan or using a custom total
   * value.
   */
  private function fixLineItems() {
    foreach ($this->params['lineItems'] as $types) {
      foreach ($types as &$line) {
        $total = $line['line_total'] + $line['tax_amount'];
        $line['tax_amount'] = ($total * ($line['tax_rate'] / 100)) / (1 + ($line['tax_rate'] / 100));
        $line['line_total'] = $total - $line['tax_amount'];
        $line['unit_price'] = $line['line_total'] / $line['qty'];
      }
    }
  }

  /**
   * Preprocesses parameters used to edit a membership.
   */
  private function preProcessEdit() {
    if ($this->contributionID) {
      $preEditMembershipHook = new CRM_MembershipExtras_Hook_PreEdit_Membership($this->id, $this->contributionID, $this->params);
      $preEditMembershipHook->preventExtendingPaymentPlanMembership();
    }

    $isPaymentPlanPayment = $this->isPaymentPlanWithMoreThanOneInstallment();
    $isMembershipRenewal = CRM_Utils_Request::retrieve('action', 'String') & CRM_Core_Action::RENEW;

    if ($isMembershipRenewal && $isPaymentPlanPayment) {
      $preEditMembershipHook = new CRM_MembershipExtras_Hook_PreEdit_Membership($this->id, $this->contributionID, $this->params);
      $preEditMembershipHook->extendPendingPaymentPlanMembershipOnRenewal();
    }
  }

  /**
   * Determines if the membership is paid using payment plan option using more
   * than one installment or not.
   *
   * @return bool
   */
  private function isPaymentPlanWithMoreThanOneInstallment() {
    $installmentsCount = CRM_Utils_Request::retrieve('installments', 'Int');
    $isSavingContribution = CRM_Utils_Request::retrieve('record_contribution', 'Int');
    $contributionIsPaymentPlan = CRM_Utils_Request::retrieve('contribution_type_toggle', 'String') === 'payment_plan';

    if ($isSavingContribution && $contributionIsPaymentPlan && $installmentsCount > 1) {
      return TRUE;
    }

    return FALSE;
  }

}
