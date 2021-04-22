<?php

use CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_AbstractProcessor as AbstractProcessor;
use CRM_MembershipExtras_Hook_CustomDispatch_CalculateContributionReceiveDate as CalculateContributionReceiveDateDispatcher;

/**
 * Class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_Contribution
 */
class CRM_MembershipExtras_Hook_Pre_MembershipPaymentPlanProcessor_Contribution extends AbstractProcessor {

  /**
   * Stores the newly created recurring contributing data
   *
   * @var array
   */
  private $recurringContribution;

  /**
   * @var array
   */
  private $membershipTypeLineItems = [];

  /**
   * @var array
   */
  private $nonMembershipTypeLineItems = [];

  /**
   * @var CRM_MembershipExtras_DTO_ScheduleInstalmentAmount
   */
  private $instalmentAmount;

  /**
   * @var string
   */
  private $periodType;

  /**
   * @var void
   */
  private $instalmentAmountCalculator;

  /**
   * @throws CiviCRM_API3_Exception
   * @throws CRM_Core_Exception
   */
  public function __construct(&$params) {
    $this->params = &$params;
    $this->setMembershipId();
    $this->assignInstalmentDetails();
    $this->handleContributionLineItems();
    $this->instalmentAmountCalculator = $this->getInstalmentAmountCalculator($this->membershipTypeLineItems, $this->periodType);
  }

  /**
   * Sets membership ID from line item.
   *
   * Since CiviCRM 5.35.1, membership ID will not be passed
   * to contribution param, as we would need the membership dates from
   * membership object to calculate instalment details, in order to
   * do this, we need to get membership ID from line item entity.
   */
  public function setMembershipId() {
    $lineItems = $this->params['line_item'];
    array_walk($lineItems, function (&$lineItem, $key) {
      array_walk($lineItem, function (&$line, $key) {
        if ($line['entity_table'] == 'civicrm_membership') {
          self::$membership_id = $line['entity_id'];
        }
      });
    });
  }

  /**
   * Returns the created recurring contribution.
   *
   * @return array
   */
  public function getRecurringContribution() {
    return $this->recurringContribution;
  }

  /**
   * Creates the payment plan for the membership
   * if its paid using payment plan option.
   *
   * For now, it creates the recurring contribution
   * and update the first contribution amount
   * depending on the instalments count.
   *
   * @throws Exception
   */
  public function createPaymentPlan() {
    $this->calculateInstalmentAmount();
    $this->createRecurringContribution();
    $this->alterFirstContributionParameters();
  }

  /**
   * Calculates Instalment Amount from the actual instalment count
   *
   * @throws Exception
   */
  private function calculateInstalmentAmount() {
    $actualInstalmentCount = $this->instalmentsCount;

    if ($this->periodType == 'fixed') {
      $actualInstalmentCount = $this->getInstalmentCount($this->membershipTypeLineItems[0]);
    }

    $this->instalmentAmount = $this->instalmentAmountCalculator->calculateInstalmentAmount($actualInstalmentCount);
    $this->applyNonMembershipPriceValues($this->instalmentAmount, $actualInstalmentCount);
  }

  /**
   * Creates the recurring contribution.
   */
  private function createRecurringContribution() {
    $amountPerInstalment = $this->instalmentAmount->getTotalAmount();

    $paymentInstrument = civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'name',
      'option_group_id' => 'payment_instrument',
      'value' => $this->params['payment_instrument_id'],
    ]);

    $financialType = civicrm_api3('FinancialType', 'getvalue', [
      'return' => 'name',
      'id' => $this->params['financial_type_id'],
    ]);

    $payLaterPaymentProcessorId = CRM_MembershipExtras_SettingsManager::getDefaultProcessorID();
    $cycleDay = CRM_MembershipExtras_Service_CycleDayCalculator::calculate($this->params['receive_date'], $this->instalmentsFrequencyUnit);

    $contributionReceiveDateParam = [
      'membership_id' => $this->getMembership()['id'],
      'membership_start_date' => $this->getMembership()['start_date'],
      'contribution_recur_id' => NULL,
      'previous_instalment_date' => NULL,
      'payment_schedule' => $this->paymentPlanSchedule,
      'payment_instrument_id' => $paymentInstrument,
      'frequency_interval' => $this->instalmentsFrequency,
      'frequency_unit' => $this->instalmentsFrequencyUnit,
    ];
    $this->dispatchReceiveDateCalculationHook($this->params['receive_date'], $contributionReceiveDateParam);

    $contributionRecurParams = [
      'sequential' => 1,
      'contact_id' => $this->params['contact_id'],
      'amount' => $amountPerInstalment,
      'currency' => $this->params['currency'],
      'frequency_unit' => $this->instalmentsFrequencyUnit,
      'frequency_interval' => $this->instalmentsFrequency,
      'installments' => $this->instalmentsCount,
      'start_date' => $this->params['receive_date'],
      'contribution_status_id' => 'Pending',
      'is_test' => $this->params['is_test'],
      'cycle_day' => $cycleDay,
      'payment_processor_id' => $payLaterPaymentProcessorId,
      'financial_type_id' => $financialType,
      'payment_instrument_id' => $paymentInstrument,
      'campaign_id' => $this->params['campaign_id'],
    ];

    $this->recurringContribution = civicrm_api3('ContributionRecur', 'create', $contributionRecurParams)['values'][0];
  }

  /**
   * Applies non membership price values to the instalment amount
   *
   * @param $instalmentAmount
   * @param $instalmentCount
   * @throws Exception
   */
  private function applyNonMembershipPriceValues($instalmentAmount, $instalmentCount) {
    if (empty($this->nonMembershipTypeLineItems)) {
      return;
    }

    $nonPriceFieldValues = [];
    foreach ($this->nonMembershipTypeLineItems as $item) {
      $values = [
        'id' => $item['price_field_value_id'],
        'amount' => $item['unit_price'],
        'financial_type_id' => $item['financial_type_id'],
      ];
      $quantity = $item['qty'];
      $nonPriceFieldValues[$item['price_field_value_id']]['values'] = $values;
      $nonPriceFieldValues[$item['price_field_value_id']]['quantity'] = $quantity;
    }

    $this->instalmentAmountCalculator->setNonMembershipPriceFieldValueAmount(
      $instalmentAmount,
      $nonPriceFieldValues,
      new CRM_MembershipExtras_Service_MembershipInstalmentTaxAmountCalculator(),
      $instalmentCount
    );
  }

  /**
   * Dispatches the membershipextras_calculateContributionReceiveDate.
   *
   * This allows other extensions to change the payment plan's first instalment
   * receive date.
   */
  private function dispatchReceiveDateCalculationHook($receiveDate, $contributionReceiveDateParams) {
    $dispatcher = new CalculateContributionReceiveDateDispatcher(1, $receiveDate, $contributionReceiveDateParams);
    $dispatcher->dispatch();

    $this->params['receive_date'] = $receiveDate;
  }

  /**
   * Alters the contribution 'to be created' parameters
   * before saving it.
   *
   * We here adjust the total, net tax amounts of
   * contribution depending on the installments number.
   * We also link the contribution with the newly created
   * recurring contribution.
   */
  private function alterFirstContributionParameters() {
    $this->params['contribution_recur_id'] = $this->recurringContribution['id'];
    $this->params['total_amount'] = $this->recurringContribution['amount'];
    $this->params['net_amount'] = $this->recurringContribution['amount'];
    $this->params['tax_amount'] = $this->instalmentAmount->getTaxAmount();
  }

  /**
   * Handles contribution line items
   *
   * @throws CRM_Core_Exception
   */
  private function handleContributionLineItems() {
    foreach (CRM_Utils_Array::value('line_item', $this->params, []) as $types) {
      foreach ($types as $line) {
        if (!empty($line['membership_type_id'])) {
          $this->handleMembershipTypeLineItems($line);
        }
        else {
          $this->handleNonMembershipTypeLineItems($line);
        }
      }
    }
  }

  /***
   * Handles membership type line item
   *
   * We adjust membership fee and set period type based on line item
   *
   * @param array $line
   *
   * @throws CRM_Core_Exception
   */
  private function handleMembershipTypeLineItems(array $line) {
    $membershipType = CRM_Member_BAO_MembershipType::findById($line['membership_type_id']);
    $this->adjustMembershipFee($membershipType, $line['line_total']);
    $this->setPeriodType($membershipType->period_type);

    array_push($this->membershipTypeLineItems, $membershipType);
  }

  /**
   * Sets period type for the contribution object.
   *
   * @param string $periodType
   */
  private function setPeriodType(string $periodType) {
    if (isset($this->periodType)) {
      return;
    }

    $this->periodType = $periodType;
  }

  /**
   * Adjusts membership fee
   *
   * When using price set, membership fee may be different
   * from membership type minimum fee, we need to adjust
   * it before calculate instalment amount before price set is used.
   *
   * @param CRM_Member_DAO_MembershipType $membershipType
   * @param float $lineTotal
   *
   * @throws CRM_Core_Exception
   */
  private function adjustMembershipFee(CRM_Member_DAO_MembershipType $membershipType, float $lineTotal) {
    if (!$this->isUsingPriceSet()) {
      return;
    }

    $membershipType->minimum_fee = $lineTotal;
  }

  /**
   * Sets non membership type line items
   *
   * @param array $line
   */
  private function handleNonMembershipTypeLineItems(array $line) {
    array_push($this->nonMembershipTypeLineItems, $line);
  }

  /**
   * Sets Contribution status to pending and set it to pay later
   */
  public function setContributionToPayLater() {
    $statusId = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ["value"],
      'option_group_id' => "contribution_status",
      'label' => "Pending",
    ])['values'][0]['value'];
    if (!empty($statusId)) {
      $this->params['contribution_status_id'] = $statusId;
      $this->params['is_pay_later'] = TRUE;
    }
  }

}
