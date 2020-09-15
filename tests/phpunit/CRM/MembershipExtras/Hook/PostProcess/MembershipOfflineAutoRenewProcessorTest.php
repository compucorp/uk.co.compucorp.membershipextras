<?php

use CRM_MembershipExtras_Test_Fabricator_PaymentPlanOrder as PaymentPlanOrderFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_PaymentProcessor_OfflineRecurringContribution as OfflineRecurringContributionPaymentProcessor;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;

/**
 * Class CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessorTest
 */
class CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessorTest extends BaseHeadlessTest {

  /**
   * @var CRM_Member_Form
   */
  private $form;
  /**
   * @var array
   */
  private $contributionPendingStatusValue;
  /**
   * @var array
   */
  private $memberDuesFinancialType;
  /**
   * @var array
   */
  private $defaultMembershipsPriceSet;
  /**
   * @var array
   */
  private $contact;
  /**
   * @var array
   */
  private $membershipType;
  /**
   * @var array
   */
  private $eftPaymentInstrumentID;
  /**
   * @var array
   */
  private $recurringContributionParams;
  /**
   * @var array
   */
  private $contributionParams;
  /**
   * @var array
   */
  private $paymentPlan;

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function setUp() {
    $this->setTestParameterValues();
    $this->createRequiredTestEntities();
    $this->setUpDefaultPaymentPlanParameters();
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  public function testPostProcessMembershipForm() {
    $this->setUpMembershipForm();
    $this->paymentPlan = PaymentPlanOrderFabricator::fabricate(
      $this->recurringContributionParams,
      $this->lineItemsParams,
      $this->contributionParams
    );
    $this->simulateMembershipSignupForm();

    $this->assertEquals(0, $this->paymentPlan['auto_renew']);

    $contributionRecurLineItem = civicrm_api3('ContributionRecurLineItem', 'getsingle', [
      'contribution_recur_id' => $this->paymentPlan['id'],
    ]);

    $this->assertEquals(0, $contributionRecurLineItem['auto_renew']);

    $membershipOfflineAutoRenewProcessor =
      new CRM_MembershipExtras_Hook_PostProcess_MembershipOfflineAutoRenewProcessor($this->form);
    $membershipOfflineAutoRenewProcessor->postProcess();

    $processedPaymentPlan = civicrm_api3('ContributionRecur', 'getsingle', [
      'id' => $this->paymentPlan['id'],
    ]);

    $this->assertEquals(1, $processedPaymentPlan['auto_renew']);

    $contributionRecurLineItem = civicrm_api3('ContributionRecurLineItem', 'getsingle', [
      'contribution_recur_id' => $this->paymentPlan['id'],
    ]);

    $this->assertEquals(1, $contributionRecurLineItem['auto_renew']);

  }

  /**
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function simulateMembershipSignupForm() {
    $this->setFormMembershipIDs();
    $this->form->setVar('_submitValues', [
      'record_contribution' => 1,
      'membership_type_id' => [$this->membershipType->id, $this->getDefaultPriceFieldValueID()],
      'financial_type_id' => $this->memberDuesFinancialType['id'],
      'payment_instrument_id' => $this->getEFTPaymentInstrumentID(),
      'contribution_status_id' => $this->getPendingContributionStatusValue(),
      'installments' => $this->recurringContributionParams['installments'],
    ]);
    $this->form->buildForm();
  }

  /**
   * @throws CiviCRM_API3_Exception
   * @throws ReflectionException
   */
  private function setFormMembershipIDs() {
    $memberships = civicrm_api3('ContributionRecur', 'get', [
      'sequential' => 1,
      'id' => $this->paymentPlan['id'],
      'api.Membership.get' => [],
    ])['values'][0]['api.Membership.get']['values'];

    $membershipIDs = [];
    foreach ($memberships as $membership) {
      array_push($membershipIDs, $membership['id']);
    }
    $formReflection = new ReflectionObject($this->form);
    $propertyReflection = $formReflection->getProperty('_membershipIDs');
    $propertyReflection->setAccessible(TRUE);
    $propertyReflection->setValue(new stdClass(), $membershipIDs);

  }

  /**
   * Loads parameters required for the tests.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function setTestParameterValues() {
    $this->contributionPendingStatusValue = $this->getPendingContributionStatusValue();
    $this->memberDuesFinancialType = $this->getMembershipDuesFinancialType();
    $this->eftPaymentInstrumentID = $this->getEFTPaymentInstrumentID();
    $this->defaultMembershipsPriceSet = $this->getDefaultPriceSet();
  }

  /**
   * Fabricates entities required for tests.
   *
   * @throws \CiviCRM_API3_Exception
   */
  private function createRequiredTestEntities() {
    $this->contact = ContactFabricator::fabricate();

    $this->membershipType = MembershipTypeFabricator::fabricate(
      [
        'name' => 'Test Rolling Membership',
        'period_type' => 'rolling',
        'minimum_fee' => 120,
      ],
      TRUE
    );

    $priceField = PriceFieldFabricator::fabricate([
      'name' => 'default_price_set',
      'label' => 'Member Dues',
      'price_set_id' => $this->defaultMembershipsPriceSet['id'],
    ]);

    PriceFieldValueFabricator::fabricate([
      'label' => $this->membershipType->name,
      'amount' => $this->membershipType->minimum_fee,
      'price_field_id' => $priceField['id'],
      'membership_type_id' => $this->membershipType->id,
      'financial_type_id' => $this->memberDuesFinancialType['id'],
    ]);
  }

  /**
   * @throws CiviCRM_API3_Exception
   */
  private function setUpDefaultPaymentPlanParameters() {
    $this->recurringContributionParams = [
      'sequential' => 1,
      'contact_id' => $this->contact['id'],
      'amount' => 0,
      'frequency_unit' => 'month',
      'frequency_interval' => 1,
      'installments' => 12,
      'contribution_status_id' => 'Pending',
      'is_test' => 0,
      'auto_renew' => 0,
      'cycle_day' => 1,
      'payment_processor_id' => $this->getPayLaterProcessorID()['id'],
      'financial_type_id' => $this->memberDuesFinancialType['id'],
      'payment_instrument_id' => $this->eftPaymentInstrumentID,
      'start_date' => date('Y-m-d'),
    ];

    $this->contributionParams = [
      'contact_id' => $this->contact['id'],
      'fee_amount' => 0,
      'net_amount' => 120,
      'total_amount' => 120,
      'receive_date' => date('Y-m-d'),
      'payment_instrument_id' => $this->eftPaymentInstrumentID,
      'financial_type_id' => $this->memberDuesFinancialType['id'],
      'contribution_status_id' => $this->contributionPendingStatusValue,
      'is_pay_later' => TRUE,
      'skipLineItem' => 1,
      'skipCleanMoney' => TRUE,
    ];

    $priceFieldValue = $this->getDefaultPriceFieldValueID($this->membershipType->id);
    $this->lineItemsParams[] = [
      'entity_table' => 'civicrm_membership',
      'price_field_id' => $priceFieldValue['price_field_id'],
      'label' => 'Membership subscription',
      'qty' => 1,
      'unit_price' => 120,
      'line_total' => 120,
      'price_field_value_id' => $priceFieldValue['id'],
      'financial_type_id' => $this->getMembershipDuesFinancialType()['id'],
      'non_deductible_amount' => 0,
    ];
  }

  /**
   * Obtains default payment processor used for offline recurring contributions.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getPayLaterProcessorID() {
    $result = civicrm_api3('PaymentProcessor', 'get', [
      'sequential' => 1,
      'name' => OfflineRecurringContributionPaymentProcessor::NAME,
      'is_test' => '0',
      'options' => ['limit' => 1],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Gets the default price field value
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getDefaultPriceFieldValueID() {
    $result = civicrm_api3('PriceFieldValue', 'get', [
      'sequential' => 1,
      'price_field_id.price_set_id.name' => 'default_membership_type_amount',
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Obtains value for EFT payment instrument option value.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getEFTPaymentInstrumentID() {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'payment_instrument',
      'label' => 'EFT',
    ]);
  }

  /**
   * Obtains value for the 'Pending' contribution status option value.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getPendingContributionStatusValue() {
    return civicrm_api3('OptionValue', 'getvalue', [
      'return' => 'value',
      'option_group_id' => 'contribution_status',
      'name' => 'Pending',
    ]);
  }

  /**
   * Obtains 'Membership Dues' financial type.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getMembershipDuesFinancialType() {
    $result = civicrm_api3('FinancialType', 'get', [
      'sequential' => 1,
      'name' => 'Member Dues',
      'options' => ['limit' => 1],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Obtains default price set for memberships.
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
   */
  private function getDefaultPriceSet() {
    $result = civicrm_api3('PriceSet', 'get', [
      'sequential' => 1,
      'name' => 'default_membership_type_amount',
      'options' => ['limit' => 1],
    ]);

    if ($result['count'] > 0) {
      return array_shift($result['values']);
    }

    return [];
  }

  /**
   * Sets Membership Form
   */
  private function setUpMembershipForm() {
    $controller = new CRM_Core_Controller();
    $this->form = new CRM_Member_Form_Membership();
    $this->form->controller = $controller;
  }

}
