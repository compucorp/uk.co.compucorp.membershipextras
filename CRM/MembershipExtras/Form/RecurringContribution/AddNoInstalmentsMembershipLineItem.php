<?php

class CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsMembershipLineItem extends CRM_Core_Form {

  const PAYMENT_TYPE_NO_PAYMENT = 1;

  const PAYMENT_TYPE_ONE_OFF_PAYMENT = 2;

  private $recurringContribution;

  private $membershipType;

  private $lineItemParams;

  private $submittedValues;

  private $prorataDaysCount;

  private $membershipDurationCalculator;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $recurringContributionID = CRM_Utils_Request::retrieve('contribution_recur_id', 'Positive', $this);
    $this->recurringContribution = $this->getRecurringContribution($recurringContributionID);
    $this->lineItemParams = CRM_Utils_Request::retrieve('line_item', 'Text', $this);

    $this->membershipType = CRM_Member_BAO_MembershipType::findById($this->lineItemParams['membership_type_id']);
    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $this->membershipDurationCalculator = new CRM_MembershipExtras_Service_MembershipTypeDurationCalculator($this->membershipType, $membershipTypeDatesCalculator);
    $this->prorataDaysCount = $this->calculateProRataDaysCount();
  }

  private function getRecurringContribution($id) {
    return civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $id,
    ]);
  }

  /**
   * Calculates the pro data days count,
   * which is the number of days between
   * the selected membership start and end
   * dates.
   *
   * @return string
   */
  private function calculateProRataDaysCount() {
    $membershipStartDate = new DateTime($this->lineItemParams['start_date']);
    $membershipEndDate = new DateTime($this->lineItemParams['end_date']);

    return $this->membershipDurationCalculator->calculateDaysBasedOnDates($membershipStartDate, $membershipEndDate);
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Add %1', ['1' => $this->membershipType->name]));

    $this->addRadio('payment_type', '', [
      self::PAYMENT_TYPE_NO_PAYMENT => ts('No Payment'),
      self::PAYMENT_TYPE_ONE_OFF_PAYMENT => ts('One off payment'),
    ]);

    $this->add('datepicker', 'scheduled_charge_date', ts('Scheduled Charge Date'), [], TRUE, ['time' => FALSE]);

    $this->addMoney('amount_exc_tax', ts('Amount exc Tax'), TRUE, [], FALSE);

    $financialTypes = CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes();
    $this->add('select', 'noinstalmentline_financial_type_id', ts('Financial Type'), $financialTypes, TRUE);

    $this->addMoney('amount_inc_tax', ts('Amount inc Tax'), FALSE, ['readonly' => TRUE], FALSE);

    $this->add('checkbox', 'noinstalmentline_send_confirmation_email', ts('Send confirmation email?'));

    $this->assign('prorataDaysCount', $this->prorataDaysCount);

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Apply'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('Cancel'),
        'isDefault' => FALSE,
      ],
    ]);
  }

  public function setDefaultValues() {
    return [
      'payment_type' => self::PAYMENT_TYPE_NO_PAYMENT,
      'scheduled_charge_date' => date('Y-m-d'),
      'noinstalmentline_financial_type_id' => $this->membershipType->financial_type_id,
      'amount_exc_tax' => $this->calculateAmountExcTax(),
    ];
  }

  private function calculateAmountExcTax() {
    $proratedAmount = $this->membershipType->minimum_fee;
    if ($this->prorataDaysCount > 1) {
      $membershipTypeDurationInDays = $this->membershipDurationCalculator->calculateOriginalInDays();
      $proratedAmount = ($this->membershipType->minimum_fee / $membershipTypeDurationInDays) * $this->prorataDaysCount;
      $proratedAmount = CRM_MembershipExtras_Service_MoneyUtilities::roundToPrecision($proratedAmount, 2);
    }

    return $proratedAmount;
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $this->submittedValues = $this->exportValues();

    $transaction = new CRM_Core_Transaction();
    try {
      $this->processPostRequest();
      $transaction->commit();
    }
    catch (Exception $e) {
      $transaction->rollback();
      $this->showErrorNotification($e);
    }
  }

  private function processPostRequest() {
    $createdLineItemData = $this->createRecurringLineItem();

    if ($this->submittedValues['payment_type'] == self::PAYMENT_TYPE_ONE_OFF_PAYMENT) {
      $contribution = $this->createOneOffPayment($createdLineItemData);
      $this->sendConfirmationEmail($contribution['id']);
    }

    $this->showOnSuccessNotifications();
  }

  private function createRecurringLineItem() {
    $data['membership'] = $membership = $this->createMembership();
    $priceFieldValue = $this->getDefaultPriceFieldValueForMembershipType($membership['membership_type_id']);

    $lineItemParams = [
      'sequential' => 1,
      'entity_table' => 'civicrm_membership',
      'entity_id' => $membership['id'],
      'price_field_id' => $priceFieldValue['price_field_id'],
      'label' => $this->membershipType->name,
      'qty' => 1,
      'unit_price' => 0,
      'line_total' => 0,
      'price_field_value_id' => $priceFieldValue['id'],
      'financial_type_id' => $this->membershipType->financial_type_id,
    ];
    $data['line_item'] = $lineItemParams = array_merge($lineItemParams, $this->getOneOffPaymentLineItemParams());
    $lineItem = civicrm_api3('LineItem', 'create', $lineItemParams);

    $recurringSubscriptionLineParams = [
      'contribution_recur_id' => $this->recurringContribution['id'],
      'line_item_id' => $lineItem['id'],
      'start_date' => $this->lineItemParams['start_date'],
      'auto_renew' => $this->lineItemParams['auto_renew'],
    ];
    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create($recurringSubscriptionLineParams);

    return $data;
  }

  private function createMembership() {
    $autoRenew = $this->lineItemParams['auto_renew'];
    $result = civicrm_api3('Membership', 'create', [
      'sequential' => 1,
      'contact_id' => $this->recurringContribution['contact_id'],
      'membership_type_id' => $this->membershipType->id,
      'join_date' => $this->lineItemParams['start_date'],
      'start_date' => $this->lineItemParams['start_date'],
      'end_date' => $this->calculateEndDateForMembership(),
      'contribution_recur_id' => $autoRenew ? $this->recurringContribution['id'] : '',
      'source' => 'Manage Instalments form',
    ]);

    return array_shift($result['values']);
  }

  private function calculateEndDateForMembership() {
    if ($this->membershipType->duration_unit == 'lifetime') {
      return NULL;
    }

    return $this->lineItemParams['end_date'];
  }

  private function getDefaultPriceFieldValueForMembershipType($membershipTypeID) {
    $result = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $membershipTypeID,
      'price_field_id.price_set_id.name' => 'default_membership_type_amount',
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Gets the parameters that are required to
   * create one off payment line item.
   *
   * @return array
   */
  private function getOneOffPaymentLineItemParams() {
    if ($this->submittedValues['payment_type'] != self::PAYMENT_TYPE_ONE_OFF_PAYMENT) {
      return [];
    }

    $amountExcTax = $this->submittedValues['amount_exc_tax'] ?? 0;
    $amountIncTax = $this->submittedValues['amount_inc_tax'] ?? 0;
    $taxAmount = $amountIncTax - $amountExcTax;

    return [
      'unit_price' => $amountExcTax,
      'line_total' => $amountExcTax,
      'tax_amount' => $taxAmount,
      'financial_type_id' => $this->submittedValues['noinstalmentline_financial_type_id'],
    ];
  }

  /**
   * Creates the one-off payment (contribution) if such option
   * is selected. It also links it to the created membership.
   *
   * @param array $createdLineItemData
   * @return array
   */
  private function createOneOffPayment($createdLineItemData) {
    $totalIncTax = $this->submittedValues['amount_inc_tax'] ?? 0;

    $contribution = civicrm_api3('Contribution', 'create', [
      'financial_type_id' => $createdLineItemData['line_item']['financial_type_id'],
      'receive_date' => $this->submittedValues['scheduled_charge_date'],
      'total_amount' => $totalIncTax,
      'fee_amount' => 0,
      'net_amount' => $totalIncTax,
      'tax_amount' => $createdLineItemData['line_item']['tax_amount'],
      'contact_id' => $this->recurringContribution['contact_id'],
      'contribution_status_id' => 'Pending',
      'currency' => $this->recurringContribution['currency'],
      'payment_instrument_id' => $this->recurringContribution['payment_instrument_id'],
      'source' => 'Manage Instalments form - One off payment',
      'contribution_recur_id' => $this->recurringContribution['id'],
      'is_pay_later' => TRUE,
    ]);

    civicrm_api3('MembershipPayment', 'create', [
      'membership_id' => $createdLineItemData['membership']['id'],
      'contribution_id' => $contribution['id'],
    ]);

    return array_shift($contribution['values']);
  }

  /**
   * Sends the confirmation email if such option is selected.
   *
   * @param int $contributionId
   * @return void
   */
  private function sendConfirmationEmail($contributionId) {
    if (!empty($this->submittedValues['noinstalmentline_send_confirmation_email'])) {
      civicrm_api3('Contribution', 'sendconfirmation', [
        'id' => $contributionId,
      ]);
    }
  }

  private function showOnSuccessNotifications() {
    $message = 'The membership has been added to payment plan and a payment has been created successfully.';
    if ($this->submittedValues['payment_type'] == self::PAYMENT_TYPE_NO_PAYMENT) {
      $message = 'The membership has been added to payment plan but no payment has been created.';
    }

    CRM_Core_Session::setStatus(
      ts($message),
      ts('Add') . ' ' . $this->membershipType->name,
      'success'
    );
  }

  private function showErrorNotification(Exception $e) {
    CRM_Core_Session::setStatus(
      ts('The membership could not be added to the payment plan. Error reason:') . $e->getMessage(),
      ts('Error Adding') . $this->membershipType->name,
      'error'
    );
  }

}
