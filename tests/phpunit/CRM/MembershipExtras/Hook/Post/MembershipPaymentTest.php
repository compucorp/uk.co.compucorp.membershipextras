<?php

use CRM_MembershipExtras_Test_Fabricator_Contact as ContactFabricator;
use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_Membership as MembershipFabricator;
use CRM_MembershipExtras_Test_Fabricator_Contribution as ContributionFabricator;

/**
 * Class CRM_MembershipExtras_Hook_Post_MembershipPaymentTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Hook_Post_MembershipPaymentTest extends BaseHeadlessTest {

  /**
   * Ovject for the membership payment.
   *
   * @var \CRM_Member_DAO_MembershipPayment
   */
  private $membershipPayment;

  /**
   * Sets up common data for all the tests.
   *
   * @throws \CiviCRM_API3_Exception
   */
  public function setUp() {
    $contact = ContactFabricator::fabricate();
    $membershipType = MembershipTypeFabricator::fabricate([
      'name' => 'Test Membership',
      'period_type' => 'rolling',
      'minimum_fee' => 120,
    ]);
    $membership = MembershipFabricator::fabricate([
      'contact_id' => $contact['id'],
      'membership_type_id' => $membershipType['id'],
      'join_date' => date('YmdHis'),
      'start_date' => date('YmdHis'),
    ]);

    $financialType = $this->getMembershipDuesFinancialType();
    $contribution = ContributionFabricator::fabricate([
      'contact_id' => $contact['id'],
      'financial_type_id' => $financialType['id'],
      'total_amount' => 120,
    ]);

    $membershipPayment = new CRM_Member_DAO_MembershipPayment();
    $membershipPayment->contribution_id = $contribution['id'];
    $membershipPayment->membership_id = $membership['id'];
    $membershipPayment->save();

    $this->membershipPayment = $membershipPayment;
  }

  public function testPostDeleteHookDoesntTriggerExceptionsWhenContributionsHaveAlreadyBeenDeleted() {
    civicrm_api3('Contribution', 'delete', [
      'id' => $this->membershipPayment->contribution_id,
    ]);

    $membershipPaymentPostHook = new CRM_MembershipExtras_Hook_Post_MembershipPayment(
      'delete',
      $this->membershipPayment->id,
      $this->membershipPayment
    );
    $membershipPaymentPostHook->postProcess();

    $this->assertTrue(TRUE);
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

}
