<?php

use CRM_MembershipExtras_Test_Fabricator_MembershipType as MembershipTypeFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceSet as PriceSetFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceField as PriceFieldFabricator;
use CRM_MembershipExtras_Test_Fabricator_PriceFieldValue as PriceFieldValueFabricator;

/**
 * Class CRM_MembershipExtras_Page_InstalmentScheduleTest
 *
 * @group headless
 */
class CRM_MembershipExtras_Page_InstalmentScheduleTest extends BaseHeadlessTest {

  use CRM_MembershipExtras_Test_Helper_FinancialAccountTrait;
  use CRM_MembershipExtras_Test_Helper_FixedPeriodMembershipTypeSettingsTrait;

  public function testRunRollingMembershipType() {
    $memType = $this->mockFixedMembershipType('rolling');
    $today = new DateTime('today');
    $_REQUEST['schedule'] = 'quarterly';
    $_REQUEST['start_date'] = $today->format('Y-m-d');
    $_REQUEST['join_date'] = $today->format('Y-m-d');
    $_REQUEST['membership_type_id'] = $memType['id'];
    $_REQUEST['snippet']  = 'json';
    $page = new CRM_MembershipExtras_Page_InstalmentSchedule();
    $this->disableReturnResult($page);
    $page->run();
    $this->assertPageRun($page);
    $this->assertNull($page->get_template_vars('prorated_number'));
    $this->assertNull($page->get_template_vars('prorated_unit'));
  }

  public function testRunFixedMembershipType() {
    $memType = $this->mockFixedMembershipType('fixed');
    $today = new DateTime('today');
    $_REQUEST['schedule'] = 'annual';
    $_REQUEST['start_date'] = $today->format('Y-m-d');
    $_REQUEST['join_date'] = $today->format('Y-m-d');
    $_REQUEST['membership_type_id'] = $memType['id'];
    $_REQUEST['snippet']  = 'json';
    $page = new CRM_MembershipExtras_Page_InstalmentSchedule();
    $this->disableReturnResult($page);
    $page->run();
    $this->assertPageRun($page);
    $this->assertNotNull($page->get_template_vars('prorated_number'));
    $this->assertNotNull($page->get_template_vars('prorated_unit'));
  }

  public function tearDown() {
    parent::tearDown();
    $_REQUEST['schedule'] = NULL;
    $_REQUEST['start_date'] = NULL;
    $_REQUEST['join_date'] = NULL;
    $_REQUEST['membership_type_id'] = NULL;
    $_REQUEST['snippet'] = NULL;
  }

  private function assertPageRun($page) {
    $this->assertNotNull($page->get_template_vars('instalments'));
    $this->assertNotNull($page->get_template_vars('sub_total'));
    $this->assertNotNull($page->get_template_vars('tax_amount'));
    $this->assertNotNull($page->get_template_vars('total_amount'));
    $this->assertNotNull($page->get_template_vars('membership_start_date'));
    $this->assertNotNull($page->get_template_vars('membership_end_date'));
  }

  private function disableReturnResult($page) {
    $refObject   = new ReflectionObject($page);
    $refProperty = $refObject->getProperty('_embedded');
    $refProperty->setAccessible(TRUE);
    $refProperty->setValue($page, TRUE);
  }

  private function mockFixedMembershipType($type) {
    $memType = MembershipTypeFabricator::fabricate(
      [
        'name' => ' Membership Type 1',
        'minimum_fee' => 120,
        'period_type' => $type,
        //01 Oct
        'fixed_period_start_day' => 1001,
        // 30 Sep
        'fixed_period_rollover_day' => 930,
        'financial_type_id' => $this->getFinancialTypeID('Member Dues'),
      ]
    );
    $this->mockSettings($memType['id'], CRM_MembershipExtras_Service_MembershipPeriodType_FixedPeriodTypeCalculator::BY_MONTHS);

    return $memType;
  }

  /**
   * @return array
   * @throws CiviCRM_API3_Exception
   */
  private function mockPriceFieldValues() {
    $priceFieldValues = [];
    $membershipType = MembershipTypeFabricator::fabricate(array_merge(
      ['name' => 'Rolling Membership Type 1', 'minimum_fee' => 120]));

    $priceSetParams = [
      'name' => "test_price_set",
      'extends' => "CiviMember",
      'financial_type_id' => "Member Dues",
      'is_active' => 1,
    ];
    $priceSet = PriceSetFabricator::fabricate($priceSetParams);

    $priceField1 = PriceFieldFabricator::fabricate([
      'price_set_id' => $priceSet['id'],
      'label' => "Price Field 1",
      'name' => "price_field_1",
      'html_type' => "Radio",
    ]);

    $priceFieldValues[] = PriceFieldValueFabricator::fabricate([
      'price_field_id' => $priceField1['id'],
      'label' => "Price Field Value with Membership Type 1",
      'amount' => 240,
      'membership_type_id' => $membershipType['id'],
      'financial_type_id' => "Member Dues",
    ]);

    return $priceFieldValues;
  }

}
