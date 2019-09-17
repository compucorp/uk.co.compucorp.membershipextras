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
      $isMembershipPeriodActive = $membershipPeriodEntity->is_active;
      $links = [
        CRM_Core_Action::VIEW => [
          'name' => ts('View'),
          'url' => 'civicrm/membership/period/view',
          'qs' => 'id=%%id%%',
          'title' => ts('View Membership'),
        ],
        CRM_Core_Action::UPDATE => [
          'name' => ts('Edit'),
          'url' => 'civicrm/membership/period/edit',
          'qs' => 'id=%%id%%',
          'title' => ts('Edit Membership Period'),
        ],
        CRM_Core_Action::RENEW => [
          'name' => ts($isMembershipPeriodActive ? 'Deactivate' : 'Activate'),
          'url' => 'civicrm/membership/period/' . $isMembershipPeriodActive ? 'deactivate' : 'activate',
          'qs' => 'id=%%id%%',
          'title' => ts($isMembershipPeriodActive ? 'Deactivate' : 'Activate' . 'Membership Period'),
        ],
        CRM_Core_Action::DELETE => [
          'name' => ts('Delete'),
          'url' => 'civicrm/membership/period/delete',
          'qs' => 'id=%%id%%',
          'title' => ts('Delete Membership Period'),
        ],
      ];

      $membershipPeriods[] = [
        'id' => $membershipPeriodEntity->id,
        'term_number' => $termNumber++,
        'start_date' => CRM_Utils_Date::customFormat($membershipPeriodEntity->start_date, '%Y%m%d'),
        'end_date' => CRM_Utils_Date::customFormat($membershipPeriodEntity->end_date, '%Y%m%d'),
        'css_class' => $this->getPeriodCSSClass($membershipPeriodEntity),
        'action' => CRM_Core_Action::formLink($links,
          null,
          [
            'id' => $membershipPeriodEntity->id,
          ],
          ts('more'),
          FALSE,
          'period.table.manage',
          'Period Table',
          $membershipPeriodEntity->id
        ),
      ];
    }

    return $membershipPeriods;
  }

  private function getPeriodCSSClass($membershipPeriodEntity) {
    $periodEndDate = (new DateTime($membershipPeriodEntity->end_date))->format('Ymd');
    $currentDate = (new DateTime())->format('Ymd');

    if ($membershipPeriodEntity->is_active == 0) {
      return 'membership-period-inactive';
    }

    if ($currentDate > $periodEndDate) {
      return 'membership-period-in-past';
    }

    return '';
  }

}
