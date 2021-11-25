<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Service_MoneyUtilities as MoneyUtilities;
use CRM_MembershipExtras_Service_MembershipTypeDatesCalculator as MembershipTypeDatesCalculator;
use CRM_MembershipExtras_Service_InstalmentReceiveDateCalculator as InstalmentReceiveDateCalculator;

/**
 * Abstract class defining methods used to add a new line item to a recurring
 * contribution.
 */
abstract class CRM_MembershipExtras_Form_RecurringContribution_AddLineItem extends CRM_Core_Form {

  /**
   * Recurring contribution's data.
   *
   * @var array
   */
  protected $recurringContribution;

  /**
   * Parameters to be used to create the new line item.
   *
   * @var array
   */
  protected $lineItemParams;

  /**
   * Number of days until next billing cycle.
   *
   * @var int
   */
  protected $daysRemainingUntilNextCycleDate;

  /**
   * @inheritdoc
   */
  public function preProcess() {
    $recurringContributionID = CRM_Utils_Request::retrieve('contribution_recur_id', 'Text', $this);
    $this->recurringContribution = $this->getRecurringContribution($recurringContributionID);
    $this->lineItemParams = CRM_Utils_Request::retrieve('line_item', 'Text', $this);
    $this->daysRemainingUntilNextCycleDate = $this->getDaysRemainingUntilNextCycleDate();
  }

  /**
   * Returns information for the recurring contribution identified by $id.
   *
   * @param int $id
   *
   * @return array
   */
  protected function getRecurringContribution($id) {
    return civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $id,
    ]);
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    return [
      'first_installment_amount' => $this->getProratedFirstInstalmentAmount(),
    ];
  }

  /**
   * @inheritdoc
   */
  public function buildQuickForm() {
    $this->add('checkbox', 'adjust_first_amount', ts('Adjust the amount of the first instalment?'), [], FALSE);
    $this->addMoney('first_installment_amount', ts('First Installment Amount'), FALSE, [], FALSE);
    $this->assign('newLineItem', $this->lineItemParams);
    $this->assign('daysRemainingUntilNextCycleDate', $this->daysRemainingUntilNextCycleDate);

    $this->addButtons([
      [
        'type' => 'submit',
        'name' => E::ts('Apply'),
        'isDefault' => TRUE,
      ],
      [
        'type' => 'cancel',
        'name' => E::ts('Cancel'),
        'isDefault' => FALSE,
      ],
    ]);
  }

  /**
   * @inheritdoc
   */
  public function postProcess() {
    $tx = new CRM_Core_Transaction();

    try {
      $recurringLineItem = $this->createRecurringLineItem();
      $this->updateRecurringContributionAmount();
      $this->addLineItemToPendingContributions($recurringLineItem);
      $this->showOnSuccessNotifications();
    }
    catch (Exception $e) {
      $tx->rollback();
      $this->showErrorNotification($e);
    }
  }

  /**
   * Updates the amount of the recurring contribution checking list of line
   * items associated to it.
   */
  private function updateRecurringContributionAmount() {
    $totalAmount = $this->calculateRecurringContributionTotalAmount();

    civicrm_api3('ContributionRecur', 'create', [
      'sequential' => 1,
      'amount' => $totalAmount,
      'id' => $this->recurringContribution['id'],
    ]);
  }

  /**
   * Calculates amount for current recurring contribution from related line
   * items.
   */
  private function calculateRecurringContributionTotalAmount() {
    $totalAmount = 0;
    $conditions = [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'start_date' => ['IS NOT NULL' => 1],
      'is_removed' => 0,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
    ];

    $installments = CRM_Utils_Array::value('installments', $this->recurringContribution, 0);
    if ($installments <= 1) {
      $conditions['end_date'] = ['IS NULL' => 1];
    }

    $result = civicrm_api3('ContributionRecurLineItem', 'get', $conditions);

    if ($result['count'] > 0) {
      foreach ($result['values'] as $lineItemData) {
        $totalAmount += $lineItemData['api.LineItem.getsingle']['line_total'];
        $totalAmount += $lineItemData['api.LineItem.getsingle']['tax_amount'];
      }
    }

    return MoneyUtilities::roundToCurrencyPrecision($totalAmount);
  }

  /**
   * Returns tax rate used for given financial type ID.
   *
   * @param $financialTypeID
   *
   * @return double
   */
  protected function getTaxRateForFinancialType($financialTypeID) {
    $taxRates = CRM_Core_PseudoConstant::getTaxRates();
    $rate = MoneyUtilities::roundToPrecision(
      CRM_Utils_Array::value($financialTypeID, $taxRates, 0),
      2
    );

    return $rate;
  }

  /**
   * Adds new line item to pending contributions and updates their amounts,
   * recording appropriate financial transactions.
   *
   * @param $recurringLineItem
   */
  protected function addLineItemToPendingContributions($recurringLineItem) {
    $firstContribution = TRUE;

    foreach ($this->getPendingContributions() as $contribution) {
      $lineItemParams = $recurringLineItem;
      $lineItemParams['contribution_id'] = $contribution['id'];
      unset($lineItemParams['id']);

      if ($firstContribution && $this->getElementValue('adjust_first_amount')) {
        $firstAmountTotal = $this->getElementValue('first_installment_amount');
        $taxRates = CRM_Core_PseudoConstant::getTaxRates();
        $rate = CRM_Utils_Array::value($recurringLineItem['financial_type_id'], $taxRates, 0);

        $lineItemParams['unit_price'] = MoneyUtilities::roundToCurrencyPrecision($firstAmountTotal);
        $lineItemParams['line_total'] = MoneyUtilities::roundToCurrencyPrecision($lineItemParams['unit_price'] * $lineItemParams['qty']);
        $lineItemParams['tax_amount'] = MoneyUtilities::roundToCurrencyPrecision($lineItemParams['line_total'] * ($rate / 100));

        $firstContribution = FALSE;
      }

      $existingLineItem = $this->findExistingLineItemForContribution($lineItemParams);
      if (CRM_Utils_Array::value('id', $existingLineItem, FALSE)) {
        $lineItemParams['id'] = $existingLineItem['id'];
      }

      $lineItemCreateResult = civicrm_api3('LineItem', 'create', $lineItemParams);
      $lineItem = array_shift($lineItemCreateResult['values']);

      // calculate balance, tax and paid amount later used to adjust transaction
      $updatedAmount = CRM_Price_BAO_LineItem::getLineTotal($contribution['id']);
      $taxAmount = CRM_MembershipExtras_Service_FinancialTransactionManager::calculateTaxAmountTotalFromContributionID($contribution['id']);

      // Record adjusted amount by updating contribution info
      CRM_MembershipExtras_Service_FinancialTransactionManager::recordAdjustedAmount($contribution, $updatedAmount, $taxAmount);

      // Record financial item on adding of line item
      CRM_MembershipExtras_Service_FinancialTransactionManager::insertFinancialItemOnLineItemAddition($lineItem);
    }
  }

  /**
   * Calculates and returns the prorated value for the first instalment amount.
   * The formula for calculating is remaining days until next billing cycle (inclusive) * Membership price per day.
   * The return value is adjusted due to the pro-rated formula.
   *
   * If the current date when the membership type is added is on or just after the recent billing cycle date,
   * the prorated amount may be slighly more than the line item amount e.g
   * Membership Type A of period type yearly with fee of 1200 paid in 3 monthly instalments with interval = 4
   * Previous cycle date is 2019-06-28.
   * Membership is added on 2019-06-28, next cycle date is 2019-10-28 (Difference in days is 123)
   * Prorated  = 1200/365 * 123 = 404.38
   * IF we divide 1200/3 = 400
   * Prorated amount is greater than 400.
   *
   * @return float|int
   */
  protected function getProratedFirstInstalmentAmount() {
    $lineItems = $this->lineItemParams;
    $membershipType = CRM_Member_BAO_MembershipType::findById($lineItems['membership_type_id']);
    $membershipTypeDates = new MembershipTypeDatesCalculator();
    $membershipDurationCalculator = new CRM_MembershipExtras_Service_MembershipTypeDurationCalculator($membershipType, $membershipTypeDates);
    $proratedAmount = $lineItems['amount'];
    $daysUntilNextCycle = $this->daysRemainingUntilNextCycleDate;

    if ($daysUntilNextCycle) {
      $membershipTypeDurationInDays = $membershipDurationCalculator->calculateOriginalInDays();
      $membershipTypeAmount = $membershipType->minimum_fee;
      $proratedAmount = ($membershipTypeAmount / $membershipTypeDurationInDays) * $daysUntilNextCycle;
      $proratedAmount = MoneyUtilities::roundToPrecision($proratedAmount, 2);
    }

    return $proratedAmount > $lineItems['amount'] ? $lineItems['amount'] : $proratedAmount;
  }

  /**
   * Returns the number of days remaining until the next billing cycle date (billing date incusive).
   *
   * @return int|null
   */
  protected function getDaysRemainingUntilNextCycleDate() {
    $receiveDateCalculator = new InstalmentReceiveDateCalculator($this->recurringContribution);
    $installmentsCount = (int) $this->recurringContribution['installments'];
    $todaysDate = new DateTime('today');

    for ($contributionNumber = 2; $contributionNumber <= $installmentsCount; $contributionNumber++) {
      $nextCycleDate = $receiveDateCalculator->calculate($contributionNumber);
      $nextCycleDate = new DateTime($nextCycleDate);

      if ($nextCycleDate >= $todaysDate) {
        $interval = $nextCycleDate->diff($todaysDate);
        return (int) $interval->format("%a") + 1;
      }
    }

    return NULL;
  }

  /**
   * Returns an array with the information of pending recurring contributions
   * after selected start date.
   *
   * @return array
   */
  protected function getPendingContributions() {
    $result = civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $this->recurringContribution['id'],
      'contribution_status_id' => 'Pending',
      'receive_date' => ['>=' => $this->lineItemParams['start_date']],
      'options' => ['limit' => 0],
    ]);

    if ($result['count'] > 0) {
      return $result['values'];
    }

    return [];
  }

  /**
   * Creates new line item associated to the recurring contribution.
   *
   * @return array
   */
  abstract protected function createRecurringLineItem();

  /**
   * Looks for an existing line item for the contribution.
   *
   * @param $lineItemParams
   *
   * @return array
   */
  abstract protected function findExistingLineItemForContribution($lineItemParams);

  /**
   * Shows notifications after all entities have been created and updated
   * succesfully.
   *
   * @return mixed
   */
  abstract protected function showOnSuccessNotifications();

  /**
   * Shows notification with the given exception's message.
   *
   * @param \Exception $e
   *
   * @return mixed
   */
  abstract protected function showErrorNotification(Exception $e);

}
