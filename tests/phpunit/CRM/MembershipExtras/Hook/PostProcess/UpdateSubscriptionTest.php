<?php
use CRM_MembershipExtras_Test_Fabricator_PaymentPlan as PaymentPlanFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;

class CRM_MembershipExtras_Hook_PostProcess_UpdateSubscriptionTest extends BaseHeadlessTest {

  /**
   * Contact for the payment plan.
   *
   * @var
   */
  private $contact;

  /**
   * Parameters for the recurring contribution.
   *
   * @var array
   */
  private $recurringContributionParams = [];

  /**
   * Parameters for line items.
   *
   * @var array
   */
  private $lineItemsParams = [];

  /**
   * Parameters for first installment.
   *
   * @var array
   */
  private $contributionParams = [];

  public function setUp() {
    /**
    [
    'sequential' => 1,
    'contact_id' => $recurringContributionParams['contact_id'],
    'amount' => 0,
    'currency' => $recurringContributionParams['currency'],
    'frequency_unit' => $recurringContributionParams['frequency_unit'],
    'frequency_interval' => $recurringContributionParams['frequency_interval'],
    'installments' => $recurringContributionParams['installments'],
    'contribution_status_id' => 'Pending',
    'is_test' => $recurringContributionParams['is_test'],
    'auto_renew' => 1,
    'cycle_day' => $recurringContributionParams['cycle_day'],
    'payment_processor_id' => $recurringContributionParams['payment_processor_id'],
    'financial_type_id' => $recurringContributionParams['financial_type_id'],
    'payment_instrument_id' => $recurringContributionParams['payment_instrument_id'],
    'start_date' => $recurringContributionParams['start_date'],
    ]

    $params =  [
    'currency' => $contribution['currency'],
    'source' => $contribution['contribution_source'],
    'contact_id' => $this->lastContribution['contact_id'],
    'fee_amount' => $this->lastContribution['fee_amount'],
    'net_amount' =>  $this->totalAmount - $this->lastContribution['fee_amount'],
    'total_amount' => $this->totalAmount,
    'receive_date' => $this->paymentPlanStartDate,
    'payment_instrument_id' => $this->lastContribution['payment_instrument_id'],
    'financial_type_id' => $this->lastContribution['financial_type_id'],
    'is_test' => $this->lastContribution['is_test'],
    'contribution_status_id' => $this->contributionPendingStatusValue,
    'contribution_recur_id' => $this->newRecurringContributionID,
    ];
     */

    $this->contact = ContactFabricator::fabricate();
    $this->recurringContributionParams = [
      'sequential' => 1,
      'contact_id' => $this->contact['id'],
      'amount' => 0,
      'currency' => $this->getDefaultCurrency(),
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 12,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => $this->getPayLaterProcessorID(),
      'financial_type_id' => $this->getMembershipDuesFinancialType(),
      'payment_instrument_id' => $this->getPaymentInstrument(),
      'start_date' => date('Y-m-d'),
    ];

    $this->lineItemsParams[] = [
      'entity_table' => 'civicrm_membership',
      'contribution_id' => 'null',
      'price_field_id' => $this->getDefaultPriceFieldID(),
      'label' => 'Membership subscription',
      'qty' => 1,
      'unit_price' => 120,
      'line_total' => 120,
      'price_field_value_id' => $this->getDefaultPriceFieldValueID(),
      'financial_type_id' => $this->getMembershipDuesFinancialType(),
      'non_deductible_amount' => 0,
    ];
  }

  public function testUpdatingCycleDayUpdatesReceiveDatesOfContributionsInFuture() {
    $paymentPlan = PaymentPlanFabricator::fabricate();

    $controller = new CRM_Core_Controller();
    $form = new CRM_Contribute_Form_UpdateSubscription();
    $form->controller = $controller;

    $updateHook = new CRM_MembershipExtras_Hook_PostProcess_UpdateSubscription($form);
    $updateHook->postProcess();
  }

}
