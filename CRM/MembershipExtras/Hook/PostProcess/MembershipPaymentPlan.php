<?php
require_once('api/class.api.php');

/**
 * Post processes Membership Forms
 */
abstract class CRM_MembershipExtras_Hook_PostProcess_MembershipPaymentPlan {
  /**
   * @var CRM_Member_Form_Membership
   *   Form object submitted to create a new membership.
   */
  protected $form;

  /**
   * @var object
   *   Object with the created membership's data.
   */
  protected $membership;

  /**
   * @var object
   *   Object with the contribution associated to the membership with full
   *   payment info.
   */
  protected $membershipContribution;

  /**
   * @var object
   *   Recurring contribution created for the payment plan.
   */
  protected $recurringContribution;

  /**
   * @var array
   *   Array mapping status names to corresponding ID's.
   */
  protected static $contributionStatusValueMap = [];

  /**
   * CRM_MembershipExtras_Hook_PostProcess_Membership constructor.
   *
   * @param \CRM_Member_Form $form
   */
  public function __construct(CRM_Member_Form $form) {
    $this->form = $form;
  }

  /**
   * This method should check if the given action is the proper operation so
   * that the form is post-processed.
   *
   * @param int $action
   *   Constant value for the action, as defined in CRM_Core_Action class
   *
   * @return mixed
   */
  protected abstract function isCorrectOperation($action);

  /**
   * Post-processes form loaded in $this->form.
   */
  public function postProcess() {
    $this->postProcessPaymentPlan();
  }

  /**
   * Checks if membership is going to be payed for with a payment plan and makes
   * the necessary adjustments.
   */
  protected function postProcessPaymentPlan() {
    $isCorrectOperation = $this->isCorrectOperation($this->form->getAction());
    $isSavingContribution = $this->form->getSubmitValue('record_contribution');
    $contributionIsPaymentPlan = $this->form->getSubmitValue('contribution_type_toggle') == 'payment_plan';

    if ($isCorrectOperation && $isSavingContribution && $contributionIsPaymentPlan) {
      $this->loadCurrentMembership();
      $this->loadCurrentContribution();
      $this->createRecurringContribution();
      $this->createInstallmentContributions();
      $this->deleteOldContribution();
    }
  }

  /**
   * Loads information for created membership into class property.
   */
  protected function loadCurrentMembership() {
    $this->membership = $this->getMembership($this->form->_id);
  }

  /**
   * Loads information for created contribution into class property.
   */
  protected function loadCurrentContribution() {
    $this->membershipContribution = $this->getLastContributionForMembership($this->form->_id);
  }

  /**
   * Gets membership data from given membership ID.
   *
   * @param $membershipID
   *
   * @return object
   *   Standard object with membership's data
   */
  protected function getMembership($membershipID) {
    $api = new civicrm_api3();
    $api->Membership->getsingle(['id' => $membershipID]);

    return $api->result;
  }

  /**
   * Obtains contribution object for given membership ID.
   *
   * @param int $membershipID
   *
   * @return object
   *   Standard object with contribution's data
   */
  protected function getLastContributionForMembership($membershipID) {
    $api = new civicrm_api3();
    $api->MembershipPayment->getsingle([
      'membership_id' => $membershipID,
      'options' => ['limit' => 1, 'sort' => 'contribution_id DESC'],
    ]);

    $contributionID = $api->result()->contribution_id;

    $api = new civicrm_api3();
    $api->Contribution->getsingle(['id' => $contributionID]);

    return $api->result();
  }

  /**
   * Creates recurring contribution from existing membership data.
   */
  protected function createRecurringContribution() {
    $totalAmount = $this->form->getSubmitValue('total_amount');
    $installments = $this->form->getSubmitValue('installments');
    $installmentsFrequency = $this->form->getSubmitValue('installments_frequency');
    $installmentsFrequencyUnit = $this->form->getSubmitValue('installments_frequency_unit');

    $contributionRecurParams = [
      'contact_id' => $this->form->_contactID,
      'frequency_interval' => $installmentsFrequency,
      'frequency_unit' => $installmentsFrequencyUnit,
      'installments' => $installments,
      'amount' => $totalAmount,
      'contribution_status_id' => 'In Progress',
      'currency' => $this->membershipContribution->currency,
      'payment_processor_id' => $this->membershipContribution->payment_processor_id,
      'payment_instrument_id' => $this->membershipContribution->payment_instrument_id,
      'financial_type_id' =>  $this->membershipContribution->financial_type_id,
    ];

    $api = new civicrm_api3();
    $api->ContributionRecur->create($contributionRecurParams);

    $this->recurringContribution = array_shift($api->result()->values);
  }

  /**
   * Creates installments as contributions for the membership created when
   * processing the form.
   */
  protected function createInstallmentContributions() {
    $totalAmount = floatval($this->recurringContribution->amount);
    $installments = intval($this->recurringContribution->installments);
    $amountPerInstallment = $this->calculateSingleInstallmentAmount($totalAmount, $installments);
    $installmentPercentage = $this->calculateSingleInstallmentPercentage($amountPerInstallment, $totalAmount);

    for ($i = 0; $i < $installments; $i++) {
      $params = $this->buildContributionParams($i, $amountPerInstallment);
      $contribution = CRM_Member_BAO_Membership::recordMembershipContribution($params);

      $label = $this->membership->membership_name;
      if ($installments > 1) {
        $label .= " ({$installmentPercentage}%), " . CRM_Utils_Date::customFormat($contribution->receive_date);
      }

      $this->createLineItem($contribution, $label);
    }
  }

  /**
   * Builds an array with all required parameters to create a contribution.
   *
   * @param $installmentNumber
   *   The number for the n-th installment to create
   * @param $amountPerInstallment
   *   Total amount for the single installment
   *
   * @return array
   */
  protected function buildContributionParams($installmentNumber, $amountPerInstallment) {
    $firstDate = $this->membershipContribution->receive_date;
    $intervalFrequency = $this->recurringContribution->frequency_interval;
    $frequencyUnit = $this->recurringContribution->frequency_unit;

    $params = $this->getDefaultContributionParameters();
    $params['total_amount'] = $amountPerInstallment;

    if ($installmentNumber == 0) {
      $receiveDate = $firstDate;
    } else {
      $receiveDate = $this->calculateInstallmentReceiveDate($installmentNumber, $intervalFrequency, $frequencyUnit, $firstDate);
      $params['contribution_status_id'] = $this->getContributionStatusID('Pending');
    }

    $params['receive_date'] = $receiveDate;

    $this->injectSoftCreditParams($params);

    return $params;
  }

  /**
   * Creates line items for the membership contribution to be auto-renewed.
   *
   * @param \CRM_Contribute_BAO_Contribution $contribution
   * @param $label
   */
  protected function createLineItem(CRM_Contribute_BAO_Contribution $contribution, $label) {
    $api = new civicrm_api3();

    $api->LineItem->create([
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->membership->id,
      'contribution_id' => $contribution->id,
      'label' => $label,
      'qty' => 1,
      'unit_price' => $contribution->total_amount,
      'line_total' => $contribution->total_amount,
      'financial_type_id' => $contribution->financial_type_id,
    ]);
    $lineItem = array_shift($api->result()->values);

    $api->FinancialItem->create([
      'contact_id' => $this->form->_contactID,
      'description' => $label,
      'amount' => $contribution->total_amount,
      'currency' => $contribution->currency,
      'financial_type_id' => $contribution->financial_type_id,
      'status_id' => 'Unpaid',
      'entity_table' => 'civicrm_line_item',
      'entity_id' => $lineItem->id,
      'transaction_date' => date('Y-m-d H:i:s'),
    ]);
  }

  /**
   * Given a status name, returns it's corresponding ID.
   *
   * @param $statusName
   *
   * @return int
   */
  protected function getContributionStatusID($statusName) {
    if (count(self::$contributionStatusValueMap) == 0) {
      $api = new civicrm_api3();
      $api->OptionValue->get([
        'option_group_id' => "contribution_status",
      ]);

      foreach ($api->result->values as $currentStatus) {
        self::$contributionStatusValueMap[$currentStatus->name] = $currentStatus->value;
      }
    }

    return CRM_Utils_Array::value($statusName, self::$contributionStatusValueMap, 0);
  }

  /**
   * Injects soft credit parameters, if they were selected on original form,
   * into the provided parameters array.
   *
   * @param $params
   */
  protected function injectSoftCreditParams(&$params) {
    $contributorID = $this->form->getSubmitValue('soft_credit_contact_id');
    $creditTypeID = $this->form->getSubmitValue('soft_credit_type_id');

    if (!empty($contributorID) && $contributorID != $this->form->_contactID) {
      $params['contribution_contact_id'] = $contributorID;

      if (!empty($creditTypeID)) {
        $softParams['soft_credit_type_id'] = $creditTypeID;
        $softParams['contact_id'] = $this->form->_contactID;
      }
    }

    $params['soft_credit'] = $softParams;
  }

  /**
   * Builds default parameters that should be used to create each installation's
   * contribution.
   *
   * @return array
   */
  protected function getDefaultContributionParameters() {
    return [
      // Membership
      'membership_id' => $this->membership->id,

      // Contribution
      'contact_id' => $this->membershipContribution->contact_id,
      'financial_type_id' => $this->membershipContribution->financial_type_id,
      'contribution_page_id' => $this->membershipContribution->contribution_page_id,
      'payment_instrument_id' => $this->membershipContribution->payment_instrument_id,
      'payment_processor_id' => $this->membershipContribution->payment_processor_id,
      'tax_amount' => $this->membershipContribution->tax_amount,
      'non_deductible_amount' => $this->membershipContribution->non_deductible_amount,
      'currency' => $this->membershipContribution->currency,
      'contribution_source' => $this->membershipContribution->source,
      'contribution_recur_id' => $this->recurringContribution->id,
      'is_pay_later' => true,
      'is_test' => $this->membershipContribution->is_test,
      'contribution_status_id' => $this->membershipContribution->contribution_status_id,
      'address_id' => $this->membershipContribution->address_id,
      'check_number' => $this->membershipContribution->check_number,
      'campaign_id' => $this->membershipContribution->campaign_id,
      'creditnote_id' => $this->membershipContribution->creditnote_id,
      'card_type_id' => $this->membershipContribution->card_type_id,
      'invoice_id' => md5(uniqid(rand(), TRUE)),

      // Line Items
      'membership_type_id' => $this->form->membership->membership_type_id,
      'skipLineItem' => 1, // Since we're creating line items manually
    ];
  }

  /**
   * Calculate and returns the receive date for a single installment.
   *
   * @param int $contributionNumber
   * @param int $intervalFrequency
   * @param string $frequencyUnit
   * @param string $originalDate
   *
   * @return string
   */
  protected function calculateInstallmentReceiveDate($contributionNumber, $intervalFrequency, $frequencyUnit, $originalDate) {
    $date = new DateTime($originalDate);
    $numberOfIntervals = $contributionNumber * $intervalFrequency;

    switch ($frequencyUnit) {
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
   * Calculates and returns the percentage value of the single installment
   * compared to the total amount.
   *
   * @param float $installmentAmount
   * @param float $totalAmount
   *
   * @return float
   */
  protected function calculateSingleInstallmentPercentage($installmentAmount, $totalAmount) {
    return round(($installmentAmount / $totalAmount) * 100, 2, PHP_ROUND_HALF_DOWN);
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
  protected function calculateSingleInstallmentAmount($totalAmount, $installmentsCount) {
    $amount =  $totalAmount;

    if ($installmentsCount > 1) {
      $amount = floor(($totalAmount / $installmentsCount) * 100) / 100;
    }

    return $amount;
  }

  /**
   * Deletes original contribution for the full amount.
   */
  protected function deleteOldContribution() {
    CRM_Contribute_BAO_Contribution::deleteContribution($this->membershipContribution->id);
  }

}
