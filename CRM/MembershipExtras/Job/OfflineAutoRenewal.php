<?php

class CRM_MembershipExtras_Job_OfflineAutoRenewal {

  /**
   * Mapping between financial types IDs and Machine Names.
   *
   * @var array
   */
  private $financialTypesIDMap = [];


  /**
   * The ID of the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentMembershipID;

  /**
   * The ID of the recurring Contribution linked
   * with the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentRecurContributionID;

  /**
   * The data of the last Contribution related to
   * the membership to-be-renewed recurring
   * contribution.
   *
   * We use it as a base for creating the
   * new contribution for the auto-renewed
   * membership.
   *
   * @var int
   */
  private $lastContribution = [];

  /**
   * The ID of contact who his
   * membership is to be auto-renewed.
   *
   * @var int
   */
  private $contactID;

  public function __construct() {
    $this->setFinancialTypesIDMap();
  }

  /**
   * Sets $financialTypesIDMap
   */
  private function setFinancialTypesIDMap() {
    $financialTypes = civicrm_api3('FinancialType', 'get', [
      'sequential' => 1,
      'return' => ['id', 'name'],
      'options' => ['limit' => 0],
    ])['values'];

    foreach ($financialTypes as $type) {
      $this->financialTypesIDMap[$type['id']] = $type['name'];
    }
  }

  /**
   * Starts the scheduled job for renewing offline
   * auto-renewal memberships.
   *
   * @return True
   */
  public function run() {
   $membershipsToRenew = $this->getOfflineAutoRenewalMemberships();
   foreach ($membershipsToRenew as $membership) {
     $this->currentMembershipID = $membership['membership_id'];
     $this->currentRecurContributionID = $membership['contribution_recur_id'];
     $this->setLastContribution();

     if ($membership['installments']) {
       $this->renewWithInstallmentsMembership($membership['installments']);
     }
     else {
       $this->renewNoInstallmentsMembership();
     }
   }

   return TRUE;
  }

  /**
   * Gets the list of offline auto-renewal memberships
   * to be renewed, the membership should satisfy the following
   * conditions for it to be auto-renewed :
   * 1- the membership is set to auto renew (has a linked recurring contribution)
   *   along with the linked recurring contribution.
   * 2- the payment processor used is pay later (aka : no payment processor used)
   *   or an equivalent payment processor that behaves as pay later.
   * 3- The linked recurring contribution is not cancelled or refunded.
   * 4- The membership end date is less or equal than today.
   *
   * @return array
   *   Each membership row Contains :
   *   1- The membership ID (membership_id)
   *   2- The linked  recurring contribution (contribution_recur_id)
   *   3- The number of recurring contribution installments (installments)
   */
  private function getOfflineAutoRenewalMemberships() {
    $membershipsList = [];

    $getContributionStatusesNameMap = $this->getContributionStatusesNameMap();
    $cancelledStatusID = $getContributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $getContributionStatusesNameMap['Refunded'];

    $query = 'SELECT cm.id as membership_id, ccr.id as contribution_recur_id, ccr.installments
                FROM civicrm_membership cm
              INNER JOIN civicrm_contribution_recur ccr
                ON cm.contribution_recur_id = ccr.id
              WHERE ccr.auto_renew = 1 
                AND (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id = 0)
                AND (ccr.contribution_status_id != ' . $cancelledStatusID . ' OR  ccr.contribution_status_id != ' . $refundedStatusID . ')
                AND cm.end_date <= CURDATE()';
    $memberships = CRM_Core_DAO::executeQuery($query);

    while ($memberships->fetch()) {
      $membershipsList['membership_id'] = $memberships['membership_id'];
      $membershipsList['contribution_recur_id'] = $memberships['contribution_recur_id'];
      $membershipsList['installments'] = $memberships['installments'];
    }

    return $membershipsList;
  }

  /**
   * Gets contribution Statuses Name to value Mapping
   *
   * @return array $contributionStatusesNameMap
   */
  private function getContributionStatusesNameMap() {
    $contributionStatuses = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ['name', 'value'],
      'option_group_id' => 'contribution_status',
      'options' => ['limit' => 0],
    ])['values'];

    $contributionStatusesNameMap = [];
    foreach ($contributionStatuses as $status) {
      $contributionStatusesNameMap[$status['name']] = $status['value'];
    }

    return $contributionStatusesNameMap;
  }

  /**
   * Sets $lastContribution to contain last
   * contribution data related to the membership
   * to be renewed. It will contain the following data :
   *
   * - id
   * - financial_type_id
   * - total_amount
   * - contact_id
   * - currency
   * - fee_amount
   * - net_amount
   * - non_deductible_amount
   * - tax_amount
   * - campaign_id
   * - source
   * - is_test
   */
  private function setLastContribution() {
    $this->lastContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['id', 'financial_type_id',
        'total_amount', 'contact_id', 'currency', 'fee_amount',
        'net_amount', 'non_deductible_amount', 'tax_amount', 'campaign_id',
        'source', 'is_test'],
      'contribution_recur_id' => $this->currentRecurContributionID,
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0];
  }

  /**
   * Renews the membership if
   * it paid by installments.
   *
   * Paid by installments membership require
   * creating new recurring contribution and
   * creating a number of contributions equals the number
   * of installments and where all these contributions
   * are linked to the new recurring contribution.
   *
   * @param int $installmentsNumber
   *   The number of installments
   */
  private function renewWithInstallmentsMembership($installmentsNumber) {
    $this->renewCurrentRecurringContribution();

    for($i=1; $i <= $installmentsNumber; $i++) {
      $newContributionID = $this->createContribution();

      $this->createMembershipPayment($newContributionID);

      $this->createLineItem($newContributionID);
    }


    $this->renewMembership();
  }

  /**
   * Renews the current membership recurring contribution
   * by creating a new one based on its data.
   *
   * Then new recurring contribution will then
   * be set to be the current recurring contribution.
   */
  private function renewCurrentRecurringContribution() {
    $currentRecurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $this->currentRecurContributionID,
    ])['values'][0];

    $paymentProcessorID = !empty($currentRecurContribution['payment_processor_id']) ? $currentRecurContribution['payment_processor_id'] : NULL;
    $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => $currentRecurContribution['contact_id'],
      'frequency_interval' => $currentRecurContribution['frequency_interval'],
      'amount' => $currentRecurContribution['amount'],
      'currency' => $currentRecurContribution['currency'],
      'frequency_unit' => $currentRecurContribution['frequency_unit'],
      'installments' => $currentRecurContribution['installments'],
      'contribution_status_id' => 'Pending',
      'is_test' => $currentRecurContribution['is_test'],
      'auto_renew' => 1,
      'campaign_id' => $currentRecurContribution['campaign_id'],
      'financial_type_id' => $this->financialTypesIDMap[$currentRecurContribution['financial_type_id']],
      'payment_instrument_id' =>'EFT',
      'payment_processor_id' => $paymentProcessorID,
    ])['values'][0];

    $this->currentRecurContributionID = $newRecurringContribution['id'];
  }

  /**
   * Renews the membership if
   * it paid by once and not using installments.
   *
   * Paid by once (no installments) membership
   * get renewed by creating single pending contribution
   * that links to the already existing recurring
   * contribution.
   *
   */
  private function renewNoInstallmentsMembership() {
    $newContributionID = $this->createContribution();

    $this->createMembershipPayment($newContributionID);

    $this->createLineItem($newContributionID);

    $this->renewMembership();
  }

  /**
   * Creates membership offline auto-renew
   * contribution record based on the last contribution
   * data, the contribution is created in 'pending' status'.
   *
   * @return int
   *   The created Contribution ID
   */
  private function createContribution() {
    $newContributionParams = [
      'financial_type_id'=> $this->financialTypesIDMap[$this->lastContribution['financial_type_id']],
      'total_amount'=> $this->lastContribution['total_amount'],
      'contact_id' => $this->lastContribution['contact_id'],
      'currency' => $this->lastContribution['currency'],
      'payment_instrument_id'=> 'EFT',
      'contribution_status_id' => 'Pending',
      'non_deductible_amount' => $this->lastContribution['non_deductible_amount'],
      'fee_amount' => $this->lastContribution['fee_amount'],
      'net_amount' => $this->lastContribution['net_amount'],
      'contribution_recur_id'=> $this->currentRecurContributionID,
      'is_test'=> $this->lastContribution['is_test'],
      'is_pay_later' => 1,
      'tax_amount'=> $this->lastContribution['tax_amount'],
      'source' => $this->lastContribution['source'],
      'campaign_id' => $this->lastContribution['campaign_id'],
      'skipRecentView' => 1,
      'skipLineItem' => 0, // TODO : Not sure of this flag, need to be tested
      'membership_id'=> $this->currentMembershipID, // TODO : Not sure of this flag, need to be tested
      'invoice_id' => md5(uniqid(rand(), TRUE))
    ];

    return civicrm_api3('Contribution', 'create', $newContributionParams)['id'];
  }

  /**
   * Creates the membership offline auto-renew
   * payment record.
   *
   * @param $contributionID
   */
  private function createMembershipPayment($contributionID) {
    civicrm_api3('MembershipPayment', 'create', [
      'membership_id' => $this->currentMembershipID,
      'contribution_id' => $contributionID,
    ]);
  }

  /**
   * Creates line items for the membership
   * contribution to be auto-renewed.
   *
   * @param $contributionID
   */
  private function createLineItem($contributionID) {
    $lastContributionLineItem = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'return' => ['id', 'qty', 'unit_price', 'line_total', 'non_deductible_amount', 'tax_amount'],
      'contribution_id' => $this->lastContribution['id'],
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0];

    $lineItemID = civicrm_api3('LineItem', 'create', [
      'sequential' => 1,
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->currentMembershipID,
      'contribution_id' => $contributionID,
      //'label' => '' TODO : Student (33.33%)March 19th, 2018,
      'qty' => $lastContributionLineItem['qty'],
      'unit_price' => $lastContributionLineItem['unit_price'],
      'line_total' => $lastContributionLineItem['line_total'],
      'financial_type_id' => $this->financialTypesIDMap[$lastContributionLineItem['financial_type_id']],
      'non_deductible_amount' => $lastContributionLineItem['non_deductible_amount'],
      'tax_amount' => $lastContributionLineItem['tax_amount'],
    ])['id'];


    $lastContributionFinancialItem = civicrm_api3('FinancialItem', 'get', [
      'sequential' => 1,
      'return' => ['id', 'amount', 'currency'],
      'entity_id' => $lastContributionLineItem['id'],
      'entity_table' => 'civicrm_line_item',
      'options' => ['limit' => 1, 'sort' => 'id DESC'],
    ])['values'][0];

    civicrm_api3('FinancialItem', 'create', [
      'contact_id' => $this->contactID,
      //'description' => '' TODO : Student (33.33%)March 19th, 2018,
      'amount' => $lastContributionFinancialItem['amount'],
      'currency' => $lastContributionFinancialItem['currency'],
      'financial_type_id' => $this->financialTypesIDMap[$lastContributionLineItem['financial_type_id']],
      'status_id' => 'Unpaid',
      'entity_table' => 'civicrm_line_item',
      'entity_id' => $lineItemID,
      'transaction_date' => date('Y-m-d H:i:s'),
    ]);
  }

  /**
   * Renews/Extend the membership to be auto-renewed
   * by one term.
   */
  private function renewMembership() {
    $membershipDetails = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ['membership_type_id', 'is_test', 'campaign_id'],
      'id' => $this->currentMembershipID,
    ])['values'][0];

    CRM_Member_BAO_Membership::processMembership(
      $this->contactID, $membershipDetails['membership_type_id'], $membershipDetails['is_test'],
      NULL, NULL, NULL, 1, $this->currentMembershipID,
      FALSE,
      $this->currentRecurContributionID, NULL, TRUE, $membershipDetails['campaign_id']
    );
  }

}
