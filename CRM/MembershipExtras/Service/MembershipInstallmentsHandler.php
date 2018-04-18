<?php

use CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator as InstallmentReceiveDateCalculator;

class CRM_MembershipExtras_Service_MembershipInstallmentsHandler {

  /**
   * The ID of the previous recurring
   * contribution if applicable for the membership.
   * Used to create the first installment contribution if
   * there is no contribution under the new
   * recurring contribution to be used as a template.
   *
   * @var int
   */
  private $previousRecurContributionId;

  /**
   * The data of the current recurring
   * contribution for the membership.
   *
   * @var array
   */
  private $currentRecurContribution;

  /**
   * The data of the last contribution
   * for the current recurring contribution.
   * If no contribution exist under the current
   * recurring contribution, then the this will
   * contain the data of the last contribution
   * for the previous recurring contribution.
   *
   * @var array
   */
  private $lastContribution;

  /**
   * The option value "value" for the "pending"
   * contribution status.
   *
   * @var int
   */
  private $contributionPendingStatusValue;

  /**
   * @var InstallmentReceiveDateCalculator
   */
  private $receiveDateCalculator;

  /**
   * The contribution amount to be used for the new
   * contribution, otherwise the last contribution
   * amount will be used.
   *
   * @var float
   */
  private $contributionAmount = 0;

  /**
   * The contribution tax amount to be used for the new
   * contribution, otherwise the last contribution
   * tax amount will be used.
   *
   * @var float
   */
  private $taxAmount = 0;

  public function __construct($currentRecurContributionId, $previousRecurContributionId = NULL) {
    $this->setCurrentRecurContribution($currentRecurContributionId);
    $this->previousRecurContributionId = $previousRecurContributionId;

    $this->receiveDateCalculator = new InstallmentReceiveDateCalculator($this->currentRecurContribution);

    $this->setContributionPendingStatusValue();
  }

  /**
   * Sets $currentRecurContribution
   *
   * @param int $currentRecurContributionId
   */
  private function setCurrentRecurContribution($currentRecurContributionId) {
    $this->currentRecurContribution =  civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $currentRecurContributionId,
    ])['values'][0];
  }

  /**
   * Sets $currentRecurContribution
   */
  private function setContributionPendingStatusValue() {
    $this->contributionPendingStatusValue =  civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  /**
   * Creates the first installment contribution for
   * the membership new recurring contribution.
   *
   * @param float $contributionAmount
   *   The amount of the contribution to be created.
   *   If not set then the recurring contribution
   *   last contribution amount price will be used.
   *
   * @param float $taxAmount
   *   The tax amount of the contribution to be createad
   *   if applicable.
   *
   */
  public function createFirstInstallmentContribution($contributionAmount = 0, $taxAmount = 0) {
    $this->contributionAmount = $contributionAmount;
    $this->taxAmount = $taxAmount;

    $recurContributionId = $this->currentRecurContribution['id'];
    if (!empty($this->previousRecurContributionId)) {
      $recurContributionId = $this->previousRecurContributionId;
    }
    $this->setLastContribution($recurContributionId);

    $this->createContribution();
  }

  /**
   * Creates the Remaining installments contributions for
   * the membership new recurring contribution.
   */
  public function createRemainingInstalmentContributionsUpfront() {
    $this->contributionAmount = NULL;
    $this->taxAmount = NULL;
    $this->setLastContribution($this->currentRecurContribution['id']);

    $installmentsCount = (int) $this->currentRecurContribution['installments'];
    for($contributionNumber = 2; $contributionNumber <= $installmentsCount; $contributionNumber++) {
      $this->createContribution($contributionNumber);
    }
  }

  /**
   * Sets $lastContribution
   *
   * @param int $recurContributionId
   *
   */
  private function setLastContribution($recurContributionId) {
    $contribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['currency', 'contribution_source', 'net_amount',
        'contact_id', 'fee_amount', 'total_amount', 'payment_instrument_id',
        'is_test', 'tax_amount', 'contribution_recur_id', 'financial_type_id'],
      'contribution_recur_id' => $recurContributionId,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0];

    $contribution['membership_id'] = civicrm_api3('MembershipPayment', 'getvalue', [
      'return' => 'membership_id',
      'contribution_id' => $contribution['id'],
    ]);

    $softContribution = civicrm_api3('ContributionSoft', 'get', [
      'sequential' => 1,
      'return' => ['contact_id', 'soft_credit_type_id'],
      'contribution_id' => $contribution['id'],
    ]);
    if (!empty($softContribution['values'][0])) {
      $softContribution = $softContribution['values'][0];
      $contribution['soft_credit'] = [
        'soft_credit_type_id' => $softContribution['soft_credit_type_id'],
        'contact_id' => $softContribution['contact_id'],
      ];
    }

    $lineItem = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'return' => ['label', 'price_field_id', 'price_field_value_id'],
      'contribution_id' => $contribution['id'],
    ]);
    if (!empty($lineItem['values'][0])) {
      $lineItem = $lineItem['values'][0];
      $contribution['line_item_label'] = $lineItem['label'];
      $contribution['line_item_price_field_id'] = $lineItem['price_field_id'];
      $contribution['line_item_price_value_id'] = $lineItem['price_field_value_id'];
    }

    $this->lastContribution = $contribution;
  }

  /**
   * Creates the installment contribution.
   *
   * @param int $contributionNumber
   *   The installment number (index), if for example
   *   the recurring contribution has 3 installments, then
   *   the first contribution number will be 1, the 2nd will be 2
   *   .. etc.
   */
  private function createContribution($contributionNumber = 1) {
    $params = $this->buildContributionParams($contributionNumber);
    $contribution = $this->recordMembershipContribution($params);

    $this->createLineItem($contribution);
  }

  /**
   * Builds the installment contribution to be created parameters.
   *
   * @param int $contributionNumber
   *
   * @return array
   */
  private function buildContributionParams($contributionNumber) {
    $params =  [
      'currency' => $this->lastContribution['currency'],
      'source' => $this->lastContribution['contribution_source'],
      'contact_id' => $this->lastContribution['contact_id'],
      'fee_amount' => $this->lastContribution['fee_amount'],
      'net_amount' => $this->lastContribution['net_amount'],
      'total_amount' => $this->lastContribution['total_amount'],
      'receive_date' => $this->receiveDateCalculator->calculate($contributionNumber),
      'payment_instrument_id' => $this->lastContribution['payment_instrument_id'],
      'financial_type_id' => $this->lastContribution['financial_type_id'],
      'is_test' => $this->lastContribution['is_test'],
      'contribution_status_id' => $this->contributionPendingStatusValue,
      'is_pay_later' => TRUE,
      'membership_id' => $this->lastContribution['membership_id'],
      'tax_amount' => $this->lastContribution['tax_amount'],
      'skipLineItem' => 1,
      'contribution_recur_id' => $this->currentRecurContribution['id'],
    ];

    if (!empty($this->lastContribution['soft_credit'])) {
      $params['soft_credit'] = $this->lastContribution['soft_credit'];
    }

    if (!empty($this->contributionAmount)) {
      $params['total_amount'] = $this->contributionAmount + $this->taxAmount;
      $params['net_amount'] = $params['total_amount'] - $params['fee_amount'];
      $params['tax_amount'] = $this->taxAmount;
    }



    return $params;
  }

  /**
   * Records the membership contribution and its
   * related entities using the specified parameters
   *
   * @param array $params
   *
   * @return CRM_Contribute_BAO_Contribution
   */
  private function recordMembershipContribution($params) {
    $contribution = CRM_Contribute_BAO_Contribution::create($params);

    $contributionSoftParams = CRM_Utils_Array::value('soft_credit', $params);
    if (!empty($contributionSoftParams)) {
      $contributionSoftParams['contribution_id'] = $contribution->id;
      $contributionSoftParams['currency'] = $contribution->currency;
      $contributionSoftParams['amount'] = $contribution->total_amount;
      CRM_Contribute_BAO_ContributionSoft::add($contributionSoftParams);
    }

    CRM_Member_BAO_MembershipPayment::create(array(
      'membership_id' => $params['membership_id'],
      'contribution_id' => $contribution->id,
    ));

    return $contribution;
  }


  /**
   * Creates the contribution line item.
   *
   * @param CRM_Contribute_BAO_Contribution $contribution
   *   The contribution that we need to build the line items for.
   */
  private function createLineItem(CRM_Contribute_BAO_Contribution $contribution) {
    $lineItemAmount = $contribution->total_amount;
    if (!empty($contribution->tax_amount)) {
      $lineItemAmount -= $contribution->tax_amount;
    }

    $lineItemParms = [
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->lastContribution['membership_id'],
      'contribution_id' => $contribution->id,
      'label' => $this->lastContribution['line_item_label'],
      'price_field_id' => $this->lastContribution['line_item_price_field_id'],
      'price_field_value_id' => $this->lastContribution['line_item_price_value_id'],
      'qty' => 1,
      'unit_price' => $lineItemAmount,
      'line_total' => $lineItemAmount,
      'financial_type_id' => $contribution->financial_type_id,
      'tax_amount' => $contribution->tax_amount,
    ];

    $lineItem = CRM_Price_BAO_LineItem::create($lineItemParms);

    CRM_Financial_BAO_FinancialItem::add($lineItem, $contribution);
    if (!empty($contribution->tax_amount)) {
      CRM_Financial_BAO_FinancialItem::add($lineItem, $contribution, TRUE);
    }
  }

}
