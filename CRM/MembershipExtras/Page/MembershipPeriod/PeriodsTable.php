<?php
use CRM_MembershipExtras_BAO_MembershipPeriod as MembershipPeriod;

class CRM_MembershipExtras_Page_MembershipPeriod_PeriodsTable extends CRM_Core_Page {

  public function run() {
    $this->assign('membershipPeriods', $this->getMembershipPeriodsRows());

    parent::run();
  }

  private function getMembershipPeriodsRows() {
    $membershipId = CRM_Utils_Request::retrieve('id', 'Positive');
    $membershipPeriodEntity = MembershipPeriod::getOrderedMembershipPeriods($membershipId);

    $termNumber = 1;
    $membershipPeriods = [];
    while ($membershipPeriodEntity->N && $membershipPeriodEntity->fetch()) {
      $membershipPeriods[] = [
        'id' => $membershipPeriodEntity->id,
        'term_number' => $termNumber++,
        'start_date' => $membershipPeriodEntity->start_date,
        'end_date' => $membershipPeriodEntity->end_date,
        'is_active' => $membershipPeriodEntity->is_active,
        'css_class' => $this->getPeriodCSSClass($membershipPeriodEntity),
      ];
    }

    return $membershipPeriods;
  }

  private function getPeriodCSSClass($membershipPeriodEntity) {
    $periodEndDate = (new DateTime($membershipPeriodEntity->end_date))->format('Ymd');
    $currentDate = (new DateTime())->format('Ymd');
    if ($currentDate > $periodEndDate) {
      return 'membership-period-in-past';
    }

    if ($membershipPeriodEntity->is_active == 0) {
      return 'membership-period-inactive';
    }

    return '';
  }

}
