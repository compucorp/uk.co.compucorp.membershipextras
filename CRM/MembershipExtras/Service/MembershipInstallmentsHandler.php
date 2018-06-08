<?php

use CRM_MembershipExtras_Service_InstallmentReceiveDateCalculator as InstallmentReceiveDateCalculator;

class CRM_MembershipExtras_Service_MembershipInstallmentsHandler {

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


  public function __construct($currentRecurContributionId) {
    $this->setCurrentRecurContribution($currentRecurContributionId);
    $this->setLastContribution();

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
   * Sets $lastContribution
   */
  private function setLastContribution() {
    $contribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['currency', 'contribution_source', 'net_amount',
        'contact_id', 'fee_amount', 'total_amount', 'payment_instrument_id',
        'is_test', 'tax_amount', 'contribution_recur_id', 'financial_type_id'],
      'contribution_recur_id' => $this->currentRecurContribution['id'],
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0];

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

    $this->lastContribution = $contribution;
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
   * Creates the Remaining installments contributions for
   * the membership new recurring contribution.
   */
  public function createRemainingInstalmentContributionsUpfront() {
    $installmentsCount = (int) $this->currentRecurContribution['installments'];
    for($contributionNumber = 2; $contributionNumber <= $installmentsCount; $contributionNumber++) {
      $this->createContribution($contributionNumber);
    }
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
    $contribution = $this->recordMembershipContribution($contributionNumber);

    $this->createLineItems($contribution);
  }


  /**
   * Records the membership contribution and its
   * related entities using the specified parameters
   *
   * @param int $contributionNumber
   *
   * @return CRM_Contribute_BAO_Contribution
   */
  private function recordMembershipContribution($contributionNumber) {
    $params = $this->buildContributionParams($contributionNumber);
    $contribution = CRM_Contribute_BAO_Contribution::create($params);

    $contributionSoftParams = CRM_Utils_Array::value('soft_credit', $params);
    if (!empty($contributionSoftParams)) {
      $contributionSoftParams['contribution_id'] = $contribution->id;
      $contributionSoftParams['currency'] = $contribution->currency;
      $contributionSoftParams['amount'] = $contribution->total_amount;
      CRM_Contribute_BAO_ContributionSoft::add($contributionSoftParams);
    }

    $membershipPayments = civicrm_api3('MembershipPayment', 'get', [
      'return' => 'membership_id',
      'contribution_id' => $this->lastContribution['id'],
    ])['values'];

    foreach ($membershipPayments as $membershipPayment) {
      CRM_Member_BAO_MembershipPayment::create([
        'membership_id' => $membershipPayment['membership_id'],
        'contribution_id' => $contribution->id,
      ]);
    }

    $this->copyContributionCustomFields($contribution->id);

    return $contribution;
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
      'skipLineItem' => 1,
      'contribution_recur_id' => $this->currentRecurContribution['id'],
    ];

    if (!empty($this->lastContribution['tax_amount'])) {
      $params['tax_amount'] = $this->lastContribution['tax_amount'];
    }

    if (!empty($this->lastContribution['soft_credit'])) {
      $params['soft_credit'] = $this->lastContribution['soft_credit'];
    }

    return $params;
  }

  /**
   * Copies the contribution custom field values from
   * the first contribution to the specified upfront contribution
   *
   * @param $contributionId
   *   The upfront contribution Id
   */
  private function copyContributionCustomFields($contributionId) {
    $customValues = CRM_Core_BAO_CustomValueTable::getEntityValues($this->lastContribution['id'], 'Contribution');
    if (empty($customValues)) {
      return;
    }

    foreach ($customValues as $key => $value) {
      if (!empty($value)) {
        $customParams["custom_{$key}"] = $value;
      }
    }
    $customParams['id'] = $contributionId;

    civicrm_api3('Contribution', 'create', $customParams);
  }


  /**
   * Creates the contribution line items.
   *
   * @param CRM_Contribute_BAO_Contribution $contribution
   *   The contribution that we need to build the line items for.
   */
  private function createLineItems(CRM_Contribute_BAO_Contribution $contribution) {
    $lineItems = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $this->lastContribution['id'],
    ])['values'];

    foreach($lineItems as $lineItem) {
      $entityID = $lineItem['entity_id'];
      if ($lineItem['entity_table'] === 'civicrm_contribution') {
        $entityID = $contribution->id;
      }
      
      $lineItemParms = [
        'entity_table' => $lineItem['entity_table'],
        'entity_id' => $entityID,
        'contribution_id' => $contribution->id,
        'price_field_id' => CRM_Utils_Array::value('price_field_id', $lineItem),
        'label' => $lineItem['label'],
        'qty' => $lineItem['qty'],
        'unit_price' => $lineItem['unit_price'],
        'line_total' => $lineItem['line_total'],
        'price_field_value_id' => CRM_Utils_Array::value('price_field_value_id', $lineItem),
        'financial_type_id' => $lineItem['financial_type_id'],
        'non_deductible_amount' => $lineItem['non_deductible_amount'],
      ];
      if (!empty($lineItem['tax_amount'])) {
        $lineItemParms['tax_amount'] = $lineItem['tax_amount'];
      }
      $newLineItem = CRM_Price_BAO_LineItem::create($lineItemParms);

      CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution);
      if (!empty((float) $contribution->tax_amount)) {
        CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution, TRUE);
      }
    }
  }

}
