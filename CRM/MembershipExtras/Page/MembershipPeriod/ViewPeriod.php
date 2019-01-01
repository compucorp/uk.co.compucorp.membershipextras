<?php

use CRM_MembershipExtras_Page_MembershipPeriod_ViewPeriodDataGenerator as ViewPeriodDataGenerator;

class CRM_MembershipExtras_Page_MembershipPeriod_ViewPeriod extends CRM_Core_Page {

  private $periodId;

  public function run() {
    $this->periodId = CRM_Utils_Request::retrieve('id', 'String', $this, TRUE);

    $viewPeriodDataGenerator = new ViewPeriodDataGenerator($this->periodId);
    $this->assign('membershipPeriod', $viewPeriodDataGenerator->getMembershipPeriodData());
    $this->assign('contributions', $viewPeriodDataGenerator->getContributions());
    $this->assign('recurContribution', $viewPeriodDataGenerator->getRecurContributionData());

    $this->buildCustomGroupsView();

    parent::run();
  }

  private function buildCustomGroupsView() {
    $groupTree = CRM_Core_BAO_CustomGroup::getTree('MembershipPeriod', NULL, $this->periodId);
    CRM_Core_BAO_CustomGroup::buildCustomDataView($this, $groupTree);
  }

}
