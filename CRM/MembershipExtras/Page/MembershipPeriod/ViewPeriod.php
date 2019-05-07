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
    $periodTermNumber = $this->getPeriodTermNumber($periodData['membership_id']);
    $pageTitle = "View $periodMembershipType - Period $periodTermNumber";
    
    CRM_Utils_System::setTitle(E::ts($pageTitle));
  }
  
  /**
   * Obtains term number for the current period.
   */
  private function getPeriodTermNumber($membershipID) {
    $membershipPeriods = $this->getSortedMembershipPeriods($membershipID);
    
    $term = 1;
    foreach ($membershipPeriods as $period) {
      if ($period['id'] === $this->periodId) {
        return $term;
      }
      
      $term++;
    }
  }

  /**
   * Builds array of periods for the given membership, sorted by start date.
   * 
   * @param int $membershipID
   * 
   * @return array
   */
  private function getSortedMembershipPeriods($membershipID) {
    $membershipPeriodsResult = CRM_MembershipExtras_BAO_MembershipPeriod::getOrderedMembershipPeriods($membershipID);

    $membershipPeriods = [];
    while ($membershipPeriodsResult->N && $membershipPeriodsResult->fetch()) {
      $membershipPeriods[] = [
        'id' => $membershipPeriodsResult->id,
      ];
    }

    return $membershipPeriods;    
  }
  
  private function buildCustomGroupsView() {
    $groupTree = CRM_Core_BAO_CustomGroup::getTree('MembershipPeriod', NULL, $this->periodId);
    CRM_Core_BAO_CustomGroup::buildCustomDataView($this, $groupTree);
  }

}
