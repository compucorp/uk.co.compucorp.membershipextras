<?php

class CRM_MembershipExtras_Form_RecurringContribution_AddNoInstalmentsDonationLineItem extends CRM_Core_Form {

  private $recurringContribution;

  private $lineItemParams;

  private $calculatedAmounts;

  private $submittedValues;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $recurringContributionID = CRM_Utils_Request::retrieve('contribution_recur_id', 'Positive', $this);
    $this->recurringContribution = $this->getRecurringContribution($recurringContributionID);
    $this->lineItemParams = CRM_Utils_Request::retrieve('line_item', 'Text', $this);
  }

  private function getRecurringContribution($id) {
    return civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $id,
    ]);
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Add Line Item Confirmation'));

    $this->add('checkbox', 'noinstalmentline_send_confirmation_email', ts('Send confirmation email?'));

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
    $this->calculateAmounts();
    $contribution = $this->createOneOffPayment();
    $this->createRecurringLineItem();
    $this->sendConfirmationEmail($contribution['id']);
    $this->showOnSuccessNotifications();
  }

  private function calculateAmounts() {
    $amountExcTax = CRM_MembershipExtras_Service_MoneyUtilities::roundToPrecision($this->lineItemParams['amount'], 2);
    $amounts = civicrm_api3('ContributionRecurLineItem', 'calculatetaxamount', [
      'amount_exc_tax' => $amountExcTax,
      'financial_type_id' => $this->lineItemParams['financial_type_id'],
    ]);

    $this->calculatedAmounts = [
      'amount_exc_tax' => $amountExcTax,
      'amount_inc_tax' => $amounts['total_amount'],
      'tax_amount' => $amounts['tax_amount'],
    ];
  }

  private function createOneOffPayment() {
    $contribution = civicrm_api3('Contribution', 'create', [
      'financial_type_id' => $this->lineItemParams['financial_type_id'],
      'receive_date' => $this->lineItemParams['start_date'],
      'total_amount' => $this->calculatedAmounts['amount_inc_tax'],
      'fee_amount' => 0,
      'net_amount' => $this->calculatedAmounts['amount_inc_tax'],
      'tax_amount' => $this->calculatedAmounts['tax_amount'],
      'contact_id' => $this->recurringContribution['contact_id'],
      'contribution_status_id' => 'Pending',
      'currency' => $this->recurringContribution['currency'],
      'payment_instrument_id' => $this->recurringContribution['payment_instrument_id'],
      'source' => 'Manage Instalments form - One off payment',
      'contribution_recur_id' => $this->recurringContribution['id'],
      'is_pay_later' => TRUE,
    ]);

    return array_shift($contribution['values']);
  }

  private function createRecurringLineItem() {
    $priceFieldValue = $this->getDefaultPriceFieldValueForContributions();

    $lineItemParams = [
      'sequential' => 1,
      'entity_table' => 'civicrm_contribution_recur',
      'entity_id' => $this->recurringContribution['id'],
      'price_field_id' => $priceFieldValue ? $priceFieldValue['price_field_id'] : NULL,
      'price_field_value_id' => $priceFieldValue ? $priceFieldValue['price_field_value_id'] : NULL,
      'label' => $this->lineItemParams['item'],
      'financial_type_id' => $this->lineItemParams['financial_type_id'],
      'qty' => 1,
      'unit_price' => $this->calculatedAmounts['amount_exc_tax'],
      'line_total' => $this->calculatedAmounts['amount_exc_tax'],
      'tax_amount' => $this->calculatedAmounts['tax_amount'],
    ];
    $lineItem = civicrm_api3('LineItem', 'create', $lineItemParams);

    $recurringSubscriptionLineParams = [
      'contribution_recur_id' => $this->recurringContribution['id'],
      'line_item_id' => $lineItem['id'],
      'start_date' => $this->lineItemParams['start_date'],
      'auto_renew' => $this->lineItemParams['auto_renew'],
    ];
    CRM_MembershipExtras_BAO_ContributionRecurLineItem::create($recurringSubscriptionLineParams);
  }

  private function getDefaultPriceFieldValueForContributions() {
    $priceField = civicrm_api3('PriceField', 'get', [
      'sequential' => 1,
      'return' => ['id'],
      'name' => 'contribution_amount',
      'price_set_id' => 'default_contribution_amount',
      'options' => ['limit' => 1],
    ]);
    if (empty($priceField['id'])) {
      return NULL;
    }

    $priceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'return' => ['id'],
      'price_field_id' => $priceField['id'],
      'options' => ['limit' => 1],
    ]);
    if (empty($priceFieldValue['id'])) {
      return NULL;
    }

    return ['price_field_id' => $priceField['id'], 'price_field_value_id' => $priceFieldValue['id']];
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
    CRM_Core_Session::setStatus(
      ts('The line item has been added.'),
      ts('Adding line item'),
      'success'
    );
  }

  private function showErrorNotification(Exception $e) {
    CRM_Core_Session::setStatus(
      ts('An error occurred while trying to add the line item') . ':' . $e->getMessage(),
      ts('Error Adding Line item'),
      'error'
    );
  }

}
