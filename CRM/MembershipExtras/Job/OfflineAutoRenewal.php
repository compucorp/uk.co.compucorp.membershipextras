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
   * The "minimum fee"/"price" of the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentMembershipMinimumFee;

  /**
   * The membership type name of the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentMembershipTypeName;

  /**
   * The ID of the recurring Contribution linked
   * with the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentRecurContributionID;

  /**
   * The number of installments of the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentInstallmentsNumber;

  /**
   * The amount of the recurring Contribution linked
   * with the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentContributionRecurAmount;

  /**
   * The frequency unit of the recurring Contribution linked
   * with the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentFrequencyUnit;

  /**
   * The frequency Interval of the recurring Contribution linked
   * with the membership that currently
   * being renewed/processed.
   *
   * @var int
   */
  private $currentFrequencyInterval;


  /**
   * The total amount of all the contributions
   * to be created for the membership renewal.
   *
   * @var int
   */
  private $totalAmount;

  /**
   * The amount of the contribution/recurring contribution
   * to be created.
   *
   * @var int
   */
  private $singleInstallmentAmount;

  /**
   * The percentage of the contributing/recurring contribution
   * to be created against the total number of contributions.
   *
   * @var int
   */
  private $singleInstallmentPercentage;


  /**
   * Should the membership latest price
   * be used for renewal or the old one.
   *
   * @var bool
   */
  private $useMembershipLatestPrice = FALSE;


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
  private $currentContribution = [];


  public function __construct() {
    $this->setFinancialTypesIDMap();
    $this->setUseMembershipLatestPrice();
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
   * Sets $useMembershipLatestPrice
   */
  private function setUseMembershipLatestPrice() {
    $settingFieldName = 'membershipextras_paymentplan_use_membership_latest_price';
    $this->useMembershipLatestPrice = civicrm_api3('Setting', 'get', array(
      'sequential' => 1,
      'return' => [$settingFieldName],
    ))['values'][0][$settingFieldName];
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
     $this->currentMembershipMinimumFee = $membership['membership_minimum_fee'];
     $this->currentMembershipTypeName = $membership['membership_type_name'];
     $this->currentRecurContributionID = $membership['contribution_recur_id'];
     $this->currentInstallmentsNumber = $membership['installments'];
     $this->currentContributionRecurAmount = $membership['contribution_recur_amount'];
     $this->currentFrequencyUnit = $membership['frequency_unit'];
     $this->currentFrequencyInterval = $membership['frequency_interval'];

     $this->totalAmount = $membership['total_amount'];
     if ($this->useMembershipLatestPrice) {
       $this->totalAmount = $this->currentMembershipMinimumFee;
     }

     $this->singleInstallmentAmount = $this->calculateSingleInstallmentAmount(
       $this->totalAmount, $this->currentInstallmentsNumber
     );

     $this->singleInstallmentPercentage = $this->calculateSingleInstallmentPercentage(
       $this->singleInstallmentAmount, $this->totalAmount
     );


     $this->setCurrentContribution();

     if ($this->currentInstallmentsNumber > 1) {
       $this->renewWithInstallmentsMembership();
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
   * 1- the membership is set to auto renew (has a linked recurring contribution).
   * 2- the payment processor used is pay later (aka : no payment processor used)
   *   or an equivalent payment processor.
   * 3- The linked recurring contribution is not cancelled or refunded.
   * 4- The membership end date is less or equal than today.
   *
   * @return array
   *   Each membership row Contains :
   *   - The membership ID (membership_id)
   *   - The membership current "minimum fee"/"price" (membership_minimum_fee)
   *   - The membership type name (membership_type_name)
   *   - The linked  recurring contribution (contribution_recur_id)
   *   - The number of the linked recurring contribution installments (installments)
   *   - The amount of the linked  recurring contribution (contribution_recur_amount)
   *   - The linked recurring contribution frequency unit (frequency_unit)
   *   - The linked recurring contribution frequency interval (frequency_interval)
   *   - The previous membership total paid amount (total_amount)
   */
  private function getOfflineAutoRenewalMemberships() {
    $membershipsList = [];

    $getContributionStatusesNameMap = $this->getContributionStatusesNameMap();
    $cancelledStatusID = $getContributionStatusesNameMap['Cancelled'];
    $refundedStatusID = $getContributionStatusesNameMap['Refunded'];

    $payLaterPaymentProcessors = new CRM_MembershipExtras_PaymentProcessor_OfflineRecurringContribution();
    $payLaterPaymentProcessorsIDs = implode(',', [0, $payLaterPaymentProcessors->get()['id']]);

    $query = 'SELECT cm.id as membership_id, cmt.minimum_fee as membership_minimum_fee,
                cmt.name as membership_type_name, 
                ccr.id as contribution_recur_id, ccr.installments , ccr.amount as contribution_recur_amount,
                ccr.frequency_unit, ccr.frequency_interval 
              FROM civicrm_membership cm
              INNER JOIN civicrm_contribution_recur ccr
                ON cm.contribution_recur_id = ccr.id
              LEFT JOIN civicrm_membership_type cmt 
                ON cm.membership_type_id = cmt.id 
              WHERE ccr.auto_renew = 1 
                AND (ccr.payment_processor_id IS NULL OR ccr.payment_processor_id IN (' . $payLaterPaymentProcessorsIDs . '))
                AND (ccr.contribution_status_id != ' . $cancelledStatusID . ' OR  ccr.contribution_status_id != ' . $refundedStatusID . ')
                AND cm.end_date <= CURDATE()';
    $memberships = CRM_Core_DAO::executeQuery($query);

    while ($memberships->fetch()) {
      $membershipsList['membership_id'] = $memberships['membership_id'];
      $membershipsList['membership_minimum_fee'] = $memberships['membership_minimum_fee'];
      $membershipsList['membership_type_name'] = $memberships['membership_type_name'];
      $membershipsList['contribution_recur_id'] = $memberships['contribution_recur_id'];
      $membershipsList['installments'] = $memberships['installments'];
      $membershipsList['contribution_recur_amount'] = $memberships['contribution_recur_amount'];
      $membershipsList['frequency_unit'] = $memberships['frequency_unit'];
      $membershipsList['frequency_interval'] = $memberships['frequency_interval'];
      $membershipsList['total_amount'] = $memberships['installments'] * $memberships['contribution_recur_amount'];
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
   * Calculates a single installment amount (price) if there is more than one
   * installment.
   *
   * If there is only one installment then its amount will be the total amount.
   *
   * @param float $totalAmount
   * @param int $installmentsCount
   *
   * @return float
   */
  private function calculateSingleInstallmentAmount($totalAmount, $installmentsCount) {
    $amount =  $totalAmount;
    if ($installmentsCount > 1) {
      $amount = floor(($totalAmount / $installmentsCount) * 100) / 100;
    }

    return $amount;
  }

  /**
   * Calculates and returns the percentage value of the single installment
   * compared to the total amount.
   *
   * @param float $installmentAmount
   * @param float $totalAmount
   *
   * @return float
   */
  private function calculateSingleInstallmentPercentage($installmentAmount, $totalAmount) {
    return round(($installmentAmount / $totalAmount) * 100, 2, PHP_ROUND_HALF_DOWN);
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
   * - campaign_id
   * - source
   * - is_test
   */
  private function setCurrentContribution() {
    $this->currentContribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'return' => ['id', 'financial_type_id',
        'total_amount', 'contact_id', 'currency',
        'campaign_id', 'source', 'is_test'],
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
   */
  private function renewWithInstallmentsMembership() {
    $this->createRecurringContribution();
    for($i=1; $i <= $this->currentInstallmentsNumber; $i++) {
      $this->createContribution($i);
      $this->createMembershipPayment();
      $this->createLineItem();
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
  private function createRecurringContribution() {
    $currentRecurContribution = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $this->currentRecurContributionID,
    ])['values'][0];


    $paymentProcessorID = !empty($currentRecurContribution['payment_processor_id']) ? $currentRecurContribution['payment_processor_id'] : NULL;
    $newRecurringContribution = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => $currentRecurContribution['contact_id'],
      'frequency_interval' => $currentRecurContribution['frequency_interval'],
      'amount' => $this->singleInstallmentAmount,
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

    // The new recurring contribution is now the current one.
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
    $this->createContribution();
    $this->createMembershipPayment();
    $this->createLineItem();

    $this->renewMembership();
  }

  /**
   * Creates membership offline auto-renew
   * contribution record based on the last contribution
   * data, the contribution is created in 'pending' status'.
   *
   * @param int $contributionNumber
   *   The number of the contribution to be created, used if there are more than
   *   one contribution (installment) to be created to calculate the receive date.
   */
  private function createContribution($contributionNumber = 1) {
    $newContributionParams = [
      'financial_type_id'=> $this->financialTypesIDMap[$this->currentContribution['financial_type_id']],
      'total_amount'=> $this->singleInstallmentAmount,
      'contact_id' => $this->currentContribution['contact_id'],
      'currency' => $this->currentContribution['currency'],
      'payment_instrument_id'=> 'EFT',
      'contribution_status_id' => 'Pending',
      'contribution_recur_id'=> $this->currentRecurContributionID,
      'is_test'=> $this->currentContribution['is_test'],
      'is_pay_later' => 1,
      'source' => $this->currentContribution['source'],
      'campaign_id' => $this->currentContribution['campaign_id'],
      'skipRecentView' => 1,
      'skipLineItem' => 0,
      'membership_id'=> $this->currentMembershipID,
      'invoice_id' => md5(uniqid(rand(), TRUE)),
      'receive_date' => $this->calculateInstallmentReceiveDate($contributionNumber),
    ];

    // The new contribution is now the current one.
    $this->currentContribution = civicrm_api3('Contribution', 'create', $newContributionParams)['values'];
  }

  /**
   * Calculate and returns the receive date for a single installment
   * based on its order (number)
   *
   * @param int $installmentNumber
   *
   * @return string
   */
  private function calculateInstallmentReceiveDate($installmentNumber) {
    $date = new DateTime();
    $numberOfIntervals = ($installmentNumber - 1) * $this->currentFrequencyInterval;
    switch ($this->currentFrequencyUnit) {
      case 'day':
        $interval = "P{$numberOfIntervals}D";
        break;
      case 'week':
        $interval = "P{$numberOfIntervals}W";
        break;
      case 'month':
        $interval = "P{$numberOfIntervals}M";
        break;
      case 'year':
        $interval = "P{$numberOfIntervals}Y";
        break;
      default:
        $interval = '';
    }

    if (!empty($interval)) {
      $date->add(new DateInterval($interval));
    }

    return $date->format('Y-m-d');
  }


  /**
   * Creates the membership offline auto-renew
   * payment record.
   */
  private function createMembershipPayment() {
    civicrm_api3('MembershipPayment', 'create', [
      'membership_id' => $this->currentMembershipID,
      'contribution_id' => $this->currentContribution['id'],
    ]);
  }

  /**
   * Creates line items for the membership
   * contribution to be auto-renewed.
   */
  private function createLineItem() {
    $label = $this->currentMembershipTypeName;
    if ($this->currentInstallmentsNumber > 1) {
      $label .= " ({$this->singleInstallmentPercentage}%), " .
        CRM_Utils_Date::customFormat($this->currentContribution['receive_date']);
    }

    $financialTypeName = $this->financialTypesIDMap[$this->currentContribution['financial_type_id']];

    $lineItemID = civicrm_api3('LineItem', 'create', [
      'sequential' => 1,
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->currentMembershipID,
      'contribution_id' => $this->currentContribution['id'],
      'label' => $label,
      'qty' => 1,
      'unit_price' => $this->singleInstallmentAmount,
      'line_total' => $this->singleInstallmentAmount,
      'financial_type_id' => $financialTypeName,
    ])['id'];


    civicrm_api3('FinancialItem', 'create', [
      'contact_id' => $this->currentContribution['contact_id'],
      'description' => $label,
      'amount' => $this->singleInstallmentAmount,
      'currency' => $this->currentContribution['currency'],
      'financial_type_id' => $financialTypeName,
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
      'return' => ['contact_id', 'membership_type_id', 'is_test', 'campaign_id'],
      'id' => $this->currentMembershipID,
    ])['values'][0];

    CRM_Member_BAO_Membership::processMembership(
      $membershipDetails['contact_id'], $membershipDetails['membership_type_id'], $membershipDetails['is_test'],
      NULL, NULL, NULL, 1, $this->currentMembershipID,
      FALSE,
      $this->currentRecurContributionID, NULL, TRUE, $membershipDetails['campaign_id']
    );
  }

}
