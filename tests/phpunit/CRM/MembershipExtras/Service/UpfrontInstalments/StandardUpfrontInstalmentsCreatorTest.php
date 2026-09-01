<?php

use CRM_MembershipExtras_Service_UpfrontInstalments_StandardUpfrontInstalmentsCreator as MembershipInstalmentsHandler;
use CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator as FixedPeriodTypeCalculator;
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contribution as ContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringLineItem as RecurringLineItemFabricator;

/**
 * CRM_MembershipExtras_Service_UpfrontInstalments_StandardUpfrontInstalmentsCreatorTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_UpfrontInstalments_StandardUpfrontInstalmentsCreatorTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;
  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;

  /**
   * @var mixed
   */
  private $recurringContribution;
  /**
   * @var mixed
   */
  private $membership;
  /**
   * @var array
   */
  private $membershipTypeDates;
  /**
   * @var mixed
   */
  private $membershipTypePriceFieldValue;
  /**
   * @var CRM_Member_BAO_MembershipType|mixed
   */
  private $membershipType;

  public function testCreateRemainingInstalmentContributionsUpfront() {
    $this->mockSalesTaxFinancialAccount();
    $this->mockPaymentPlanMembershipOrder('rolling');
    $mockedMembershipPayments = $this->getMembershipPayment();
    $existingTaxFinancialItemsCount = civicrm_api3('FinancialItem', 'get', ['description' => Civi::settings()->get('tax_term')])['count'];

    $this->assertEquals(1, $mockedMembershipPayments['count']);

    $expectedContribution = $mockedMembershipPayments['values'][0]['api.Contribution.get']['values'][0];
    unset($expectedContribution['id']);

    $handler = new MembershipInstalmentsHandler($this->recurringContribution['id']);
    $handler->createRemainingInstalments();

    $processesMembershipPayments = $this->getMembershipPayment();
    $this->assertEquals(12, $processesMembershipPayments['count']);

    $taxFinancialItemsCount = civicrm_api3('FinancialItem', 'get', ['description' => Civi::settings()->get('tax_term')])['count'];
    $this->assertEquals($existingTaxFinancialItemsCount + 11, $taxFinancialItemsCount);

    $processedContributions = $processesMembershipPayments['values'][0]['api.Contribution.get']['values'];
    foreach ($processedContributions as $contribution) {
      unset($contribution['id']);
      $this->assertTrue($expectedContribution == $contribution);
    }

  }

  /**
   * Tests last contribution has adjusted amounts when fee doesn't divide evenly.
   *
   * £100 / 12 = 8.33 per instalment, remainder 0.04 on last.
   * Contributions 1–11: total_amount=8.33, Contribution 12: total_amount=8.37
   */
  public function testLastContributionHasRoundingAdjustmentForUnevenAmount() {
    $minimumFee = 100;
    $instalments = 12;
    $perInstalmentAmount = round($minimumFee / $instalments, 2);

    $this->mockPaymentPlanOrderWithInstalmentAmounts($minimumFee, $perInstalmentAmount, $instalments);

    $handler = new MembershipInstalmentsHandler($this->recurringContribution['id']);
    $handler->createRemainingInstalments();

    $membershipPayments = $this->getMembershipPayment();
    $this->assertEquals($instalments, $membershipPayments['count']);

    $contributions = [];
    foreach ($membershipPayments['values'] as $membershipPayment) {
      $contributions[] = $membershipPayment['api.Contribution.get']['values'][0];
    }

    // Sort by id to ensure order.
    usort($contributions, function ($a, $b) {
      return $a['id'] - $b['id'];
    });

    // First 11 contributions have the regular per-instalment amount.
    for ($i = 0; $i < $instalments - 1; $i++) {
      $this->assertEquals($perInstalmentAmount, (float) $contributions[$i]['total_amount'],
        "Contribution " . ($i + 1) . " should have total_amount={$perInstalmentAmount}");
    }

    // Last contribution has the remainder adjustment.
    $expectedLastAmount = round($minimumFee - ($perInstalmentAmount * ($instalments - 1)), 2);
    $this->assertEquals($expectedLastAmount, (float) $contributions[$instalments - 1]['total_amount'],
      "Last contribution should have adjusted total_amount={$expectedLastAmount}");

    // Verify all contributions sum to the original fee.
    $totalContributions = 0;
    foreach ($contributions as $contribution) {
      $totalContributions += (float) $contribution['total_amount'];
    }
    $this->assertEquals($minimumFee, round($totalContributions, 2));
  }

  /**
   * Tests last contribution has adjusted tax and line item amounts with VAT.
   *
   * £100 + 20% VAT = £120 / 12 instalments.
   * Regular: sub=8.33, tax=1.67, total=10.00
   * Last:    sub=8.37, tax=1.63, total=10.00
   */
  public function testLastContributionHasAdjustedTaxAmountWithVAT() {
    $this->mockSalesTaxFinancialAccount();

    $minimumFee = 100;
    $instalments = 12;
    $perInstalmentSubTotal = round($minimumFee / $instalments, 2);
    $fullTaxAmount = round($minimumFee * 0.20, 2);
    $perInstalmentTax = round($fullTaxAmount / $instalments, 2);
    $perInstalmentTotal = round($perInstalmentSubTotal + $perInstalmentTax, 2);

    $this->mockPaymentPlanOrderWithInstalmentAmounts(
      $minimumFee, $perInstalmentTotal, $instalments, $perInstalmentSubTotal, $perInstalmentTax
    );

    $handler = new MembershipInstalmentsHandler($this->recurringContribution['id']);
    $handler->createRemainingInstalments();

    $membershipPayments = $this->getMembershipPayment();
    $this->assertEquals($instalments, $membershipPayments['count']);

    $contributions = [];
    foreach ($membershipPayments['values'] as $membershipPayment) {
      $contributions[] = $membershipPayment['api.Contribution.get']['values'][0];
    }

    usort($contributions, function ($a, $b) {
      return $a['id'] - $b['id'];
    });

    // Verify the last contribution's line item has adjusted amounts.
    $lastContribution = $contributions[$instalments - 1];
    $lastLineItems = civicrm_api3('LineItem', 'get', [
      'sequential' => 1,
      'contribution_id' => $lastContribution['id'],
    ])['values'];

    $this->assertNotEmpty($lastLineItems);
    $lastLineItem = $lastLineItems[0];

    $expectedLastSubTotal = round($minimumFee - ($perInstalmentSubTotal * ($instalments - 1)), 2);
    $expectedLastTax = round($fullTaxAmount - ($perInstalmentTax * ($instalments - 1)), 2);

    $this->assertEquals($expectedLastSubTotal, (float) $lastLineItem['line_total'],
      "Last line item should have adjusted line_total={$expectedLastSubTotal}");
    $this->assertEquals($expectedLastTax, round((float) $lastLineItem['tax_amount'], 2),
      "Last line item should have adjusted tax_amount={$expectedLastTax}");

    // Verify all contribution totals sum to the full amount including tax.
    $totalContributions = 0;
    foreach ($contributions as $contribution) {
      $totalContributions += (float) $contribution['total_amount'];
    }
    $expectedTotal = round($minimumFee + $fullTaxAmount, 2);
    $this->assertEquals($expectedTotal, round($totalContributions, 2));
  }

  /**
   * Tests fixed membership pro-rated renewal produces correct last instalment.
   *
   * This covers the scenario that caused the negative-amount regression:
   * a fixed membership type pro-rated in its first year (e.g. Jan–Sep = 9 months
   * out of a full Oct–Sep year). The rounding code must detect the pro-ration
   * and use the prorated total — not the full membership fee — when computing
   * the last instalment adjustment.
   *
   * £685 fixed membership, pro-rated to 9 months (BY_MONTHS).
   * Prorated fee = 685 × 9/12 = 513.75
   * Per instalment: round(513.75/9, 2) = 57.08
   * Last: 513.75 − 57.08×8 = 57.11
   */
  public function testFixedMembershipProratedRenewalHasCorrectLastContribution() {
    $minimumFee = 685;
    $instalments = 9;

    $this->membershipType = CRM_MembershipExtras_Test_Fabricator_MembershipType::fabricate([
      'name' => 'Test Fixed Prorated Membership',
      'period_type' => 'fixed',
      'minimum_fee' => $minimumFee,
      'duration_interval' => 1,
      'duration_unit' => 'year',
      'fixed_period_start_day' => 1001,
      'fixed_period_rollover_day' => 930,
    ]);

    $this->mockSettings(
      $this->membershipType['id'],
      FixedPeriodTypeCalculator::BY_MONTHS
    );

    // Pro-rated period: Jan 1 to Sep 30 (9 out of 12 months).
    $startDate = new DateTime('2025-01-01');
    $endDate = new DateTime('2025-09-30');
    $joinDate = new DateTime('2025-01-01');

    // Calculate the prorated fee using the same calculator the production code uses.
    $membershipTypeObj = CRM_Member_BAO_MembershipType::findById($this->membershipType['id']);
    $calculator = new FixedPeriodTypeCalculator([$membershipTypeObj]);
    $calculator->setStartDate(clone $startDate);
    $calculator->setEndDate(clone $endDate);
    $calculator->setJoinDate(clone $joinDate);
    $calculator->calculate();
    $proratedFee = round($calculator->getAmount(), 2);

    $perInstalmentAmount = round($proratedFee / $instalments, 2);

    $this->membershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $this->membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $this->membershipType['id'],
      $startDate,
      $endDate,
      $joinDate
    );

    $contact = ContactFabricator::fabricate();
    $this->recurringContribution = RecurringContributionFabricator::fabricate([
      'sequential' => 1,
      'contact_id' => $contact['id'],
      'amount' => $perInstalmentAmount,
      'frequency_unit' => 'month',
      'frequency_interval' => '1',
      'installments' => $instalments,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'financial_type_id' => 'Member Dues',
      'payment_instrument_id' => 'EFT',
      'start_date' => $startDate->format('Y-m-d'),
    ]);

    $this->membership = MembershipFabricator::fabricate([
      'contact_id' => $this->recurringContribution['contact_id'],
      'membership_type_id' => $this->membershipType['id'],
      'join_date' => $joinDate->format('Y-m-d'),
      'start_date' => $startDate->format('Y-m-d'),
      'end_date' => $endDate->format('Y-m-d'),
      'contribution_recur_id' => $this->recurringContribution['id'],
      'financial_type_id' => 'Member Dues',
      'skipLineItem' => 1,
    ]);

    $lineItemData = [
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->membership['id'],
      'price_field_id' => $this->membershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->membershipTypePriceFieldValue['id'],
      'label' => $this->membershipType['name'],
      'qty' => 1,
      'unit_price' => $perInstalmentAmount,
      'line_total' => $perInstalmentAmount,
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];

    $newLineItem = LineItemFabricator::fabricate($lineItemData);
    RecurringLineItemFabricator::fabricate([
      'contribution_recur_id' => $this->recurringContribution['id'],
      'line_item_id' => $newLineItem['id'],
      'start_date' => $startDate->format('Y-m-d'),
      'auto_renew' => 0,
    ]);

    $contribution = ContributionFabricator::fabricate([
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => $joinDate->format('Y-m-d'),
      'contribution_recur_id' => $this->recurringContribution['id'],
      'contact_id' => $this->recurringContribution['contact_id'],
      'fee_amount' => 0,
      'net_amount' => $perInstalmentAmount,
      'total_amount' => $perInstalmentAmount,
      'payment_instrument_id' => 'EFT',
      'financial_type_id' => 'Member Dues',
      'contribution_status_id' => 'Pending',
    ]);

    $contributionLineItemData = $newLineItem;
    unset($contributionLineItemData['id']);
    $contributionLineItemData['contribution_id'] = $contribution['id'];
    $this->createLineItemForContribution(['line_item' => $contributionLineItemData]);

    $handler = new MembershipInstalmentsHandler($this->recurringContribution['id']);
    $handler->setInstalmentsCount($instalments);
    $handler->createRemainingInstalments();

    $membershipPayments = $this->getMembershipPayment();
    $this->assertEquals($instalments, $membershipPayments['count']);

    $contributions = [];
    foreach ($membershipPayments['values'] as $membershipPayment) {
      $contributions[] = $membershipPayment['api.Contribution.get']['values'][0];
    }

    usort($contributions, function ($a, $b) {
      return $a['id'] - $b['id'];
    });

    // Key regression check: last contribution must NOT be negative.
    $lastContributionAmount = (float) $contributions[$instalments - 1]['total_amount'];
    $this->assertGreaterThan(0, $lastContributionAmount,
      'Last contribution must not be negative (regression check)');

    // Last contribution should be close to regular amount (not wildly different).
    $this->assertEqualsWithDelta($perInstalmentAmount, $lastContributionAmount, 1.00,
      'Last contribution should be within £1.00 of regular instalment amount');

    // All contributions must sum to the prorated fee.
    $totalContributions = 0;
    foreach ($contributions as $contribution) {
      $totalContributions += (float) $contribution['total_amount'];
    }
    $this->assertEquals($proratedFee, round($totalContributions, 2),
      "All contributions should sum to prorated fee of {$proratedFee}");
  }

  public function testCreateRemainingInstalmentAccordingToOverriddenNumberOfInstalments() {
    $this->mockPaymentPlanMembershipOrder('fixed');
    $mockedMembershipPayments = $this->getMembershipPayment();

    $this->assertEquals(1, $mockedMembershipPayments['count']);

    $handler = new MembershipInstalmentsHandler($this->recurringContribution['id']);
    $handler->setInstalmentsCount(9);
    $handler->createRemainingInstalments();

    $expectedContribution = $mockedMembershipPayments['values'][0]['api.Contribution.get']['values'][0];
    unset($expectedContribution['id']);

    $processesMembershipPayments = $this->getMembershipPayment();

    $processedContributions = $processesMembershipPayments['values'][0]['api.Contribution.get']['values'];
    foreach ($processedContributions as $contribution) {
      unset($contribution['id']);
      $this->assertTrue($expectedContribution == $contribution);
    }
  }

  private function getMembershipPayment() {
    $params = [
      'sequential' => 1,
      'membership_id' => $this->membership['id'],
      'api.Contribution.get' => [],
    ];

    return civicrm_api3('MembershipPayment', 'get', $params);
  }

  /**
   * Fabricates payment plan membership order
   *
   * @param $periodType
   * @throws CRM_Core_Exception
   */
  private function mockPaymentPlanMembershipOrder($periodType) {
    $this->membershipType = CRM_MembershipExtras_Test_Fabricator_MembershipType::fabricate([
      'name' => 'Test Membership',
      'period_type' => $periodType,
      'minimum_fee' => 120,
      'duration_interval' => 1,
      'duration_unit' => 'year',
      //01 Oct
      'fixed_period_start_day' => 1001,
      // 30 Sep
      'fixed_period_rollover_day' => 930,
    ]);

    $startDate = new DateTime();
    $joinDate = $startDate;
    $endDate = new DateTime();
    $endDate->add(new DateInterval('P1Y'));
    $endDate->modify("-1 day");

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $this->membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $this->membershipType['id'],
      $startDate,
      $endDate,
      $joinDate
    );

    $this->membershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $this->createRecurringContribution();
    $this->createdLineItems();
  }

  private function createRecurringContribution() {
    $contact = ContactFabricator::fabricate();
    $recurringContributionParams = [
      'sequential' => 1,
      'contact_id' => $contact['id'],
      //120 (Membership fee)  / 12 (instalments)
      'amount' => 10,
      'frequency_unit' => 'year',
      'frequency_interval' => '1',
      'installments' => 12,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'financial_type_id' => 'Member Dues',
      'payment_instrument_id' => 'EFT',
      'start_date' => $this->membershipTypeDates['start_date'],
    ];

    $this->recurringContribution = RecurringContributionFabricator::fabricate($recurringContributionParams);
  }

  private function createdLineItems() {
    $lineItems[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $this->membershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->membershipTypePriceFieldValue['id'],
      'label' => $this->membershipType['name'],
      'qty' => 1,
      'unit_price' => $this->membershipTypePriceFieldValue['amount'],
      'line_total' => $this->membershipTypePriceFieldValue['amount'],
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
      'auto_renew' => 0,
    ];

    $createdLines = [];

    foreach ($lineItems as $lineItem) {
      if ($lineItem['entity_table'] == 'civicrm_membership') {
        $this->membership = MembershipFabricator::fabricate([
          'contact_id' => $this->recurringContribution['contact_id'],
          'membership_type_id' => $this->membershipType['id'],
          'join_date' => CRM_Utils_Array::value('join_date', $lineItem, $this->membershipTypeDates['join_date']),
          'start_date' => CRM_Utils_Array::value('start_date', $lineItem, $this->membershipTypeDates['start_date']),
          'end_date' => CRM_Utils_Array::value('end_date', $lineItem, $this->membershipTypeDates['end_date']),
          'contribution_recur_id' => $this->recurringContribution['id'],
          'financial_type_id' => $lineItem['financial_type_id'],
          'skipLineItem' => 1,
        ]);

        $lineItem['entity_id'] = $this->membership['id'];
        $lineItem['entity_table'] = 'civicrm_membership';
      }

      $newLineItem = LineItemFabricator::fabricate($lineItem);
      $recurringLineItem = RecurringLineItemFabricator::fabricate([
        'contribution_recur_id' => $this->recurringContribution['id'],
        'line_item_id' => $newLineItem['id'],
        'start_date' => $this->membershipTypeDates['start_date'],
        'auto_renew' => isset($lineItem['auto_renew']) ? $lineItem['auto_renew'] : 1,
      ]);

      $createdLines[] = ['line_item' => $newLineItem, 'recurring_line' => $recurringLineItem];
    }

    $this->createContribution($createdLines);
  }

  private function createContribution($createdLines) {
    //Create contribution
    $params = [
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => $this->membershipTypeDates['join_date'],
      'contribution_recur_id' => $this->recurringContribution['id'],
      'contact_id' => $this->recurringContribution['contact_id'],
      'fee_amount' => 0,
      'net_amount' => "{$this->recurringContribution['amount']}",
      'total_amount' => "{$this->recurringContribution['amount']}",
      'payment_instrument_id' => 'EFT',
      'financial_type_id' => 'Member Dues',
      'contribution_status_id' => 'Pending',
    ];
    $contribution = ContributionFabricator::fabricate($params);

    foreach ($createdLines as &$line) {
      unset($line['line_item']['id']);
      $line['line_item']['contribution_id'] = $contribution['id'];

      if ($line['line_item']['entity_table'] === 'civicrm_contribution') {
        $line['line_item']['entity_id'] = $contribution['id'];
      }

      $this->createLineItemForContribution($line);
    }
  }

  /**
   * Creates the line item for the contribution identified by the given ID.
   *
   * @param array $line
   *
   * @throws \CRM_Core_Exception
   */
  private function createLineItemForContribution($line) {
    $contribution = CRM_Contribute_BAO_Contribution::findById($line['line_item']['contribution_id']);
    $newLineItem = CRM_Price_BAO_LineItem::create($line['line_item']);
    CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution);

    if (!empty($newLineItem->tax_amount)) {
      CRM_Financial_BAO_FinancialItem::add($newLineItem, $contribution, TRUE);
    }
    if ($line['line_item']['entity_table'] == 'civicrm_membership') {
      CRM_Member_BAO_MembershipPayment::create([
        'membership_id' => $line['line_item']['entity_id'],
        'contribution_id' => $contribution->id,
      ]);
    }
  }

  /**
   * Fabricates payment plan with per-instalment amounts in line items.
   *
   * Unlike mockPaymentPlanMembershipOrder which uses the full membership fee
   * as line_total, this method uses per-instalment amounts — matching what
   * happens in production when the payment plan is created with split amounts.
   *
   * @param float $minimumFee
   * @param float $perInstalmentTotal
   * @param int $instalments
   * @param float|null $perInstalmentLineTotal
   * @param float|null $perInstalmentTax
   */
  private function mockPaymentPlanOrderWithInstalmentAmounts(
    $minimumFee,
    $perInstalmentTotal,
    $instalments = 12,
    $perInstalmentLineTotal = NULL,
    $perInstalmentTax = NULL
  ) {
    if ($perInstalmentLineTotal === NULL) {
      $perInstalmentLineTotal = $perInstalmentTotal;
    }

    $this->membershipType = CRM_MembershipExtras_Test_Fabricator_MembershipType::fabricate([
      'name' => 'Test Rounding Membership',
      'period_type' => 'rolling',
      'minimum_fee' => $minimumFee,
      'duration_interval' => 1,
      'duration_unit' => 'year',
    ]);

    $startDate = new DateTime();
    $joinDate = $startDate;
    $endDate = new DateTime();
    $endDate->add(new DateInterval('P1Y'));
    $endDate->modify('-1 day');

    $membershipTypeDatesCalculator = new CRM_MembershipExtras_Service_MembershipTypeDatesCalculator();
    $this->membershipTypeDates = $membershipTypeDatesCalculator->getDatesForMembershipType(
      $this->membershipType['id'],
      $startDate,
      $endDate,
      $joinDate
    );

    $this->membershipTypePriceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $this->membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $contact = ContactFabricator::fabricate();
    $this->recurringContribution = RecurringContributionFabricator::fabricate([
      'sequential' => 1,
      'contact_id' => $contact['id'],
      'amount' => $perInstalmentTotal,
      'frequency_unit' => 'month',
      'frequency_interval' => '1',
      'installments' => $instalments,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'financial_type_id' => 'Member Dues',
      'payment_instrument_id' => 'EFT',
      'start_date' => $this->membershipTypeDates['start_date'],
    ]);

    $this->membership = MembershipFabricator::fabricate([
      'contact_id' => $this->recurringContribution['contact_id'],
      'membership_type_id' => $this->membershipType['id'],
      'join_date' => $this->membershipTypeDates['join_date'],
      'start_date' => $this->membershipTypeDates['start_date'],
      'end_date' => $this->membershipTypeDates['end_date'],
      'contribution_recur_id' => $this->recurringContribution['id'],
      'financial_type_id' => 'Member Dues',
      'skipLineItem' => 1,
    ]);

    $lineItemData = [
      'entity_table' => 'civicrm_membership',
      'entity_id' => $this->membership['id'],
      'price_field_id' => $this->membershipTypePriceFieldValue['price_field_id'],
      'price_field_value_id' => $this->membershipTypePriceFieldValue['id'],
      'label' => $this->membershipType['name'],
      'qty' => 1,
      'unit_price' => $perInstalmentLineTotal,
      'line_total' => $perInstalmentLineTotal,
      'financial_type_id' => 'Member Dues',
      'non_deductible_amount' => 0,
    ];

    $newLineItem = LineItemFabricator::fabricate($lineItemData);
    RecurringLineItemFabricator::fabricate([
      'contribution_recur_id' => $this->recurringContribution['id'],
      'line_item_id' => $newLineItem['id'],
      'start_date' => $this->membershipTypeDates['start_date'],
      'auto_renew' => 0,
    ]);

    $contributionParams = [
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => $this->membershipTypeDates['join_date'],
      'contribution_recur_id' => $this->recurringContribution['id'],
      'contact_id' => $this->recurringContribution['contact_id'],
      'fee_amount' => 0,
      'net_amount' => $perInstalmentTotal,
      'total_amount' => $perInstalmentTotal,
      'payment_instrument_id' => 'EFT',
      'financial_type_id' => 'Member Dues',
      'contribution_status_id' => 'Pending',
    ];

    if ($perInstalmentTax !== NULL) {
      $contributionParams['tax_amount'] = $perInstalmentTax;
    }

    $contribution = ContributionFabricator::fabricate($contributionParams);

    // Use the fabricated result (which has resolved numeric IDs) for the
    // contribution line item — matching the pattern in createdLineItems().
    $contributionLineItemData = $newLineItem;
    unset($contributionLineItemData['id']);
    $contributionLineItemData['contribution_id'] = $contribution['id'];

    $this->createLineItemForContribution(['line_item' => $contributionLineItemData]);
  }

}
