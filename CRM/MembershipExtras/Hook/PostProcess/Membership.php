<?php
require_once('api/class.api.php');

/**
 * Post processes Membership create/edit Form.
 */
class CRM_MembershipExtras_Hook_PostProcess_Membership {

  /**
   * @var CRM_Member_Form_Membership
   */
  private $form;

  /**
   * @var object
   */
  private $membership;

  /**
   * @var object
   */
  private $membershipContribution;

  /**
   * @var object
   */
  private $recurringContribution;

  private static $contributionStatusValueMap = array();

  /**
   * CRM_MembershipExtras_Hook_PostProcess_Membership constructor.
   *
   * @param \CRM_Member_Form_Membership $form
   */
  public function __construct(CRM_Member_Form_Membership $form) {
    $this->form = $form;
  }

  /**
   * Post-processes form to check if membership is going to be payed for with a
   * payment plan and makes the necessary adjustments.
   */
  public function postProcess() {
    $isAddingNewMembership = $this->form->getAction() & CRM_Core_Action::ADD;
    $recordingContribution = $this->form->getSubmitValue('record_contribution');
    $contributionIsPaymentPlan = $this->form->getSubmitValue('contribution_type_toggle') == 'payment_plan';

    if ($isAddingNewMembership && $recordingContribution && $contributionIsPaymentPlan) {
      $this->loadCurrentMembershipAndContribution();
      $this->createRecurringContribution();
      $this->createInstallmentContributions();
      $this->deleteOldContribution();
    }
  }

  private function loadCurrentMembershipAndContribution() {
    $this->membership = $this->getMembership($this->form->_id);
    $this->membershipContribution = $this->getContributionForMembership($this->form->_id);
  }

  private function createRecurringContribution() {
    $totalAmount = $this->form->getSubmitValue('total_amount');
    $installments = $this->form->getSubmitValue('installments');
    $installmentsFrequency = $this->form->getSubmitValue('installments_frequency');
    $installmentsFrequencyUnit = $this->form->getSubmitValue('installments_frequency_unit');

    $contributionRecurParams = array(
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
    );

    $api = new civicrm_api3();
    $api->ContributionRecur->create($contributionRecurParams);

    $this->recurringContribution = array_shift($api->result->values);
  }

  private function getMembership($membershipID) {
    $api = new civicrm_api3();
    $api->Membership->getsingle(array('id' => $membershipID));

    return $api->result;
  }

  /**
   * Obtains contribution BAO object for given membership ID.
   *
   * @param int $membershipID
   *
   * @return object
   */
  private function getContributionForMembership($membershipID) {
    $contributionID = CRM_Member_BAO_Membership::getMembershipContributionId($membershipID);

    $api = new civicrm_api3();
    $api->Contribution->getsingle(array('id' => $contributionID));

    return $api->result;
  }

  private function createInstallmentContributions() {
    $totalAmount = floatval($this->recurringContribution->amount);
    $installments = intval($this->recurringContribution->installments);
    $amountPerInstallment = $this->calculateSingleInstallmentAmount($totalAmount, $installments);
    $installmentPercentage = $this->calculateSingleInstallmentPercentage($amountPerInstallment, $totalAmount);

    $firstDate = $this->membershipContribution->receive_date;
    $intervalFrequency = $this->recurringContribution->frequency_interval;
    $frequencyUnit = $this->recurringContribution->frequency_unit;
    $membershipTypeName = $this->membership->membership_name;

    for ($i = 0; $i < $installments; $i++) {
      $params = $this->getDefaultContributionParameters();

      if ($i == 0) {
        $receiveDate = $firstDate;
      } else {
        $receiveDate = $this->calculateInstallmentReceiveDate($i, $intervalFrequency, $frequencyUnit, $firstDate);
        $params['contribution_status_id'] = $this->getContributionStatusID('Pending');
      }

      $params['total_amount'] = $amountPerInstallment;
      $params['receive_date'] = $receiveDate;

      $label = "$membershipTypeName";
      if ($installments > 1) {
        $label .= " ({$installmentPercentage}), " . CRM_Utils_Date::customFormat($receiveDate);
      }
      $this->injectLineItemIntoParams($params, $label);
      $this->injectSoftCreditParams($params);

      CRM_Member_BAO_Membership::recordMembershipContribution($params);
    }
  }

  private function getContributionStatusID($statusName) {
    if (count(self::$contributionStatusValueMap) == 0) {
      $api = new civicrm_api3();
      $api->OptionValue->get(array(
        'option_group_id' => "contribution_status",
      ));

      foreach ($api->result->values as $currentStatus) {
        self::$contributionStatusValueMap[$currentStatus->name] = $currentStatus->value;
      }
    }

    return CRM_Utils_Array::value($statusName, self::$contributionStatusValueMap);
  }

  private function injectSoftCreditParams(&$params) {
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

  private function injectLineItemIntoParams(&$params, $label) {
    CRM_Price_BAO_LineItem::getLineItemArray($params, NULL, 'membership', $params['membership_type_id']);

    foreach ($params['line_item'] as $set => $priceFields) {
      foreach ($priceFields as $fieldID => $lineItem) {
        $params['line_item'][$set][$fieldID]['label'] = $label;
      }
    }
  }

  private function getDefaultContributionParameters() {
    return array(
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

      // Line Items
      'membership_type_id' => $this->form->membership->membership_type_id,
    );
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
  private function calculateInstallmentReceiveDate($contributionNumber, $intervalFrequency, $frequencyUnit, $originalDate) {
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
  private function calculateSingleInstallmentPercentage($installmentAmount, $totalAmount) {
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
  private function calculateSingleInstallmentAmount($totalAmount, $installmentsCount) {
    $amount =  $totalAmount;

    if ($installmentsCount > 1) {
      $amount = floor(($totalAmount / $installmentsCount) * 100) / 100;
    }

    return $amount;
  }

  private function deleteOldContribution() {
    $api = new civicrm_api3();
    $api->Contribution->delete(array('id' => $this->membershipContribution->id));
  }

}
