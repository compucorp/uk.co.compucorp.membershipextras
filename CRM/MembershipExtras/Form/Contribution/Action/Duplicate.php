<?php

use CRM_MembershipExtras_Service_PaymentPlanStatusCalculator as PaymentPlanStatusCalculator;

class CRM_MembershipExtras_Form_Contribution_Action_Duplicate extends CRM_Core_Form {

  /**
   * ID of the contribution that we want to duplicate
   *
   * @var int
   */
  private $contributionId;

  /**
   * The created duplicate contribution
   *
   * @var CRM_Contribute_BAO_Contribution
   */
  private $duplicateContribution;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $this->contributionId = CRM_Utils_Request::retrieve('crid', 'Positive', $this);
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    CRM_Utils_System::setTitle(ts('Duplicate As New Pending Contribution Confirmation'));

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => ts('Yes'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => ts('No'),
      ],
    ]);
  }

  /**
   * @@inheritdoc
   */
  public function postProcess() {
    $this->createDuplicatePendingContribution();
    $this->showNotification();
  }

  private function createDuplicatePendingContribution() {
    $transaction = new CRM_Core_Transaction();

    try {
      $this->createContributionRecord();
      $this->createSoftContribution();
      $this->createMembershipPaymentRecord();
      $this->copyCustomFields();
      $this->createLineItems();
      $this->updateRecurContributionStatus();

      $transaction->commit();
    }
    catch (Exception $e) {
      $transaction->rollback();
    }
  }

  private function createContributionRecord() {
    $duplicateContributionParams = $this->getContributionDuplicationParams();
    $this->duplicateContribution = CRM_Contribute_BAO_Contribution::create($duplicateContributionParams);
  }

  private function getContributionDuplicationParams() {
    $contribution = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'id' => $this->contributionId,
      'return' => ['currency', 'contact_id',  'total_amount', 'receive_date',
        'payment_instrument_id', 'financial_type_id', 'is_test',
        'contribution_recur_id', 'tax_amount',
        'contribution_page_id', 'campaign_id'],
    ])['values'][0];

    $params =  [
      'currency' => $contribution['currency'],
      'source' => 'Duplicate As Pending Contribution Action',
      'contact_id' => $contribution['contact_id'],
      'fee_amount' => 0,
      'net_amount' => $contribution['total_amount'],
      'total_amount' => $contribution['total_amount'],
      'receive_date' =>$contribution['receive_date'],
      'payment_instrument_id' => $contribution['payment_instrument_id'],
      'financial_type_id' => $contribution['financial_type_id'],
      'is_test' => $contribution['is_test'],
      'contribution_status_id' => $this->getPendingStatusId(),
      'is_pay_later' => 1,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'contribution_recur_id' => CRM_Utils_Array::value('contribution_recur_id', $contribution),
      'contribution_page_id' => CRM_Utils_Array::value('contribution_page_id', $contribution),
      'campaign_id' => CRM_Utils_Array::value('contribution_campaign_id', $contribution),
    ];

    if (!empty($contribution['tax_amount'])) {
      $params['tax_amount'] = $contribution['tax_amount'];
    }

    return $params;
  }

  private function getPendingStatusId() {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  private function createSoftContribution() {
    $softContribution = civicrm_api3('ContributionSoft', 'get', [
      'sequential' => 1,
      'return' => ['contact_id', 'soft_credit_type_id'],
      'contribution_id' => $this->contributionId,
    ]);
    if (!empty($softContribution['values'])) {
      $softContribution = $softContribution['values'][0];

      $contributionSoftParams = [
        'soft_credit_type_id' => $softContribution['soft_credit_type_id'],
        'contact_id' => $softContribution['contact_id'],
        'contribution_id' => $this->duplicateContribution->id,
        'currency' => $this->duplicateContribution->currency,
        'amount' => $this->duplicateContribution->total_amount,
      ];
      CRM_Contribute_BAO_ContributionSoft::add($contributionSoftParams);
    }
  }

  private function createMembershipPaymentRecord() {
    $membershipPayments = civicrm_api3('MembershipPayment', 'get', [
      'return' => 'membership_id',
      'contribution_id' => $this->contributionId,
      'options' => ['limit' => 0],
    ])['values'];
    foreach ($membershipPayments as $membershipPayment) {
      CRM_Member_BAO_MembershipPayment::create([
        'membership_id' => $membershipPayment['membership_id'],
        'contribution_id' => $this->duplicateContribution->id,
      ]);
    }
  }

  private function copyCustomFields() {
    CRM_MembershipExtras_Service_CustomFieldsCopier::copy(
      $this->contributionId,
      $this->duplicateContribution->id,
      'Contribution'
    );
  }

  private function createLineItems() {
    $lineItems = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $this->contributionId,
    ])['values'];

    foreach($lineItems as $lineItem) {
      $entityID = $lineItem['entity_id'];
      if ($lineItem['entity_table'] === 'civicrm_contribution') {
        $entityID = $this->duplicateContribution->id;
      }

      $lineItemParms = [
        'entity_table' => $lineItem['entity_table'],
        'entity_id' => $entityID,
        'contribution_id' => $this->duplicateContribution->id,
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

      CRM_Financial_BAO_FinancialItem::add($newLineItem, $this->duplicateContribution);

      if (!empty((float) $this->duplicateContribution->tax_amount) && !empty($newLineItem->tax_amount)) {
        CRM_Financial_BAO_FinancialItem::add($newLineItem, $this->duplicateContribution, TRUE);
      }
    }
  }

  private function updateRecurContributionStatus() {
    if(empty($this->duplicateContribution->contribution_recur_id)) {
      return;
    }
    $recurContributionId = $this->duplicateContribution->contribution_recur_id;

    $paymentPlanStatusCalculator = new PaymentPlanStatusCalculator($recurContributionId);
    $newStatus = $paymentPlanStatusCalculator->calculate();
    if ($newStatus == NULL) {
      return;
    }

    $updateParams = [
      'id' => $recurContributionId,
      'contribution_status_id' => $newStatus,
    ];

    if ($newStatus != PaymentPlanStatusCalculator::CONTRIBUTION_STATUS_COMPLETED) {
      $updateParams['end_date'] = '';
    }

    civicrm_api3('ContributionRecur', 'create', $updateParams);
  }

  private function showNotification() {
    if (empty($this->duplicateContribution)) {
      $message = ts('Something went wrong! no duplicate contribution is created.');
      $type = 'error';
    } elseif(!empty($this->duplicateContribution->contribution_recur_id)) {
      $message = ts('Duplicate contribution created successfully and attached to recurring contribution');
      $type = 'success';
    } else {
      $message = ts('Duplicate contribution created successfully');
      $type = 'success';
    }

    CRM_Core_Session::setStatus($message, '', $type);
  }

}
