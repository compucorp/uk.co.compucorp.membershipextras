<?php

use CRM_MembershipExtras_ExtensionUtil as E;
use CRM_MembershipExtras_Page_MembershipPeriod_ViewPeriodDataGenerator as ViewPeriodDataGenerator;

class CRM_MembershipExtras_Page_MembershipPeriod_ViewPeriod extends CRM_Core_Page {

  /**
   * Period ID.
   * 
   * @var int
   */
  private $periodId;

  public function run() {
    $this->periodId = CRM_Utils_Request::retrieve('id', 'String', $this, TRUE);
    
    $viewPeriodDataGenerator = new ViewPeriodDataGenerator($this->periodId);
    $periodData = $viewPeriodDataGenerator->getMembershipPeriodData();
    
    $this->setPageTitle($periodData);
    $this->assign('membershipPeriod', $periodData);
    $this->assign('recurContribution', $viewPeriodDataGenerator->getRecurContributionData());

    $this->buildCustomGroupsView();

    parent::run();
  }

  /**
   * Builds the title for the page.
   * 
   * @param array $periodData
   */
  private function setPageTitle($periodData) {
    $periodMembershipType = $periodData['membership_type_name'];
    $periodTermNumber = $periodData['term_number'];
    $pageTitle = "View $periodMembershipType - Period $periodTermNumber";
    
    CRM_Utils_System::setTitle(E::ts($pageTitle));
  }

  private function buildCustomGroupsView() {
    $groupTree = CRM_Core_BAO_CustomGroup::getTree('MembershipPeriod', NULL, $this->periodId);
    CRM_Core_BAO_CustomGroup::buildCustomDataView($this, $groupTree);
  }

}
