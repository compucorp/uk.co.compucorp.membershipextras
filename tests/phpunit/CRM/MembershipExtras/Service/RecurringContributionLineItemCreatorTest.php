<?php

use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_RecurringContribution as RecurringContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contribution as ContributionFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_LineItem as LineItemFabricator;
use CRM_MembershipExtras_Service_RecurringContributionLineItemCreator as RecurringLineItemCreator;

/**
 * Class CRM_MembershipExtras_Service_RecurringContributionLineItemCreatorTest.
 *
 * @group headless
 */
class CRM_MembershipExtras_Service_RecurringContributionLineItemCreatorTest extends BaseHeadlessTest {

  /**
   * Creates a contact.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function createContact() {
    return ContactFabricator::fabricate();
  }

  /**
   * Creates a membership type with the given params.
   *
   * @param array $params
   *
   * @return \stdClass
   * @throws \CiviCRM_API3_Exception
   */
  private function createMembershipType($params) {
    $membershipType = MembershipTypeFabricator::fabricate($params);
    $priceFieldValue = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'membership_type_id' => $membershipType['id'],
      'options' => ['limit' => 1],
    ])['values'][0];

    $result = new stdClass();
    $result->membershipType = $membershipType;
    $result->priceFieldValue = $priceFieldValue;

    return $result;
  }

  /**
   * Creates a recurring contribution with the given parameters.
   *
   * @param array $params
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function createRecurringContribution($params) {
    return RecurringContributionFabricator::fabricate($params);
  }

  /**
   * Creates a contribution with the given parameters and line items.
   *
   * @param array $contributionParams
   * @param array $lineItems
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function createContribution($contributionParams, $lineItems) {
    $contribution = ContributionFabricator::fabricate($contributionParams);

    foreach ($lineItems as &$line) {
      $line['contribution_id'] = $contribution['id'];

      if ($line['entity_table'] === 'civicrm_contribution') {
        $line['line_item']['entity_id'] = $contribution['id'];
      }

      if ($line['entity_table'] === 'civicrm_membership') {
        $membership = $this->createMembership($line, $contribution);
        $line['entity_id'] = $membership['id'];
      }

      LineItemFabricator::fabricate($line);
    }
  }

  /**
   * Creates a membership with the given parameters.
   *
   * @param array $lineItem
   * @param array $contribution
   *
   * @return mixed
   * @throws \CiviCRM_API3_Exception
   */
  private function createMembership($lineItem, $contribution) {
    $priceFieldValue = $this->getPriceFieldValue($lineItem['price_field_value_id']);

    return MembershipFabricator::fabricate([
      'contact_id' => $contribution['contact_id'],
      'membership_type_id' => $priceFieldValue['membership_type_id'],
      'join_date' => CRM_Utils_Array::value('join_date', $lineItem, date('Y-m-d')),
      'start_date' => CRM_Utils_Array::value('start_date', $lineItem, date('Y-m-d')),
      'end_date' => CRM_Utils_Array::value('end_date', $lineItem, NULL),
      'contribution_recur_id' => $contribution['contribution_recur_id'],
      'financial_type_id' => $lineItem['financial_type_id'],
      'skipLineItem' => 1,
    ]);
  }

  /**
   * Obtains data for price field value identified with given ID.
   *
   * @param int $priceFieldValueID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private static function getPriceFieldValue($priceFieldValueID) {
    return civicrm_api3('PriceFieldValue', 'getsingle', [
      'id' => $priceFieldValueID,
    ]);
  }

  public function testLineItemCreation() {
    $contact = $this->createContact();
    $startDate = date('Y-m-d');
    $recurringContribution = $this->createRecurringContribution([
      'sequential' => 1,
      'contact_id' => $contact['id'],
      'amount' => 0,
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 12,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 1,
      'cycle_day' => 1,
      'payment_processor_id' => 'Offline Recurring Contribution',
      'financial_type_id' => 'Member Dues',
      'payment_instrument_id' => 'EFT',
      'start_date' => $startDate,
    ]);

    $contributionParams = [
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
      'receive_date' => $startDate,
      'contribution_recur_id' => $recurringContribution['id'],
      'contact_id' => $recurringContribution['contact_id'],
      'fee_amount' => 0,
      'net_amount' => "{$recurringContribution['amount']}",
      'total_amount' => "{$recurringContribution['amount']}",
      'payment_instrument_id' => 'EFT',
      'financial_type_id' => 'Member Dues',
      'contribution_status_id' => 'Pending',
    ];

    $mainMembershipType = $this->createMembershipType([
      'name' => 'Main Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 60,
      'duration_interval' => 18,
      'duration_unit' => 'month',
    ]);
    $addOnMembershipType = $this->createMembershipType([
      'name' => 'Add-on Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 120,
      'duration_interval' => 12,
      'duration_unit' => 'month',
    ]);
    $secondAddOnMembershipType = $this->createMembershipType([
      'name' => 'Second Add-on Rolling Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 180,
      'duration_interval' => 6,
      'duration_unit' => 'month',
    ]);

    $lineItems = [
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $mainMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $mainMembershipType->priceFieldValue['id'],
        'label' => $mainMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $mainMembershipType->priceFieldValue['amount'] / 12,
        'line_total' => $mainMembershipType->priceFieldValue['amount'] / 12,
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 0,
        'start_date' => $startDate,
      ],
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $addOnMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $addOnMembershipType->priceFieldValue['id'],
        'label' => $addOnMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $addOnMembershipType->priceFieldValue['amount'] / 12,
        'line_total' => $addOnMembershipType->priceFieldValue['amount'] / 12,
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 1,
        'start_date' => $startDate,
      ],
      [
        'entity_table' => 'civicrm_membership',
        'price_field_id' => $secondAddOnMembershipType->priceFieldValue['price_field_id'],
        'price_field_value_id' => $secondAddOnMembershipType->priceFieldValue['id'],
        'label' => $secondAddOnMembershipType->membershipType['name'],
        'qty' => 1,
        'unit_price' => $secondAddOnMembershipType->priceFieldValue['amount'] / 12,
        'line_total' => $secondAddOnMembershipType->priceFieldValue['amount'] / 12,
        'financial_type_id' => 'Member Dues',
        'non_deductible_amount' => 0,
        'auto_renew' => 0,
        'start_date' => $startDate,
      ],
    ];
    $this->createContribution($contributionParams, $lineItems);

    $lineItemCreator = new RecurringLineItemCreator($recurringContribution['id']);
    $lineItemCreator->create();

    $lineItems = $this->getRecurringContributionLines($recurringContribution['id']);
    $this->assertEquals(3, count($lineItems));

    foreach ($lineItems as $line) {
      $this->assertEquals($startDate . ' 00:00:00', $line['start_date']);
    }
  }

  /**
   * Obtains recurring line items for the recurring contribution.
   *
   * @param int $recurringContributionID
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getRecurringContributionLines($recurringContributionID) {
    $result = civicrm_api3('ContributionRecurLineItem', 'get', [
      'sequential' => 1,
      'contribution_recur_id' => $recurringContributionID,
      'api.LineItem.getsingle' => [
        'id' => '$value.line_item_id',
        'entity_table' => ['IS NOT NULL' => 1],
        'entity_id' => ['IS NOT NULL' => 1],
      ],
    ]);
    if ($result['count'] > 0) {
      return $result['values'];
    }

    return [];
  }

}
