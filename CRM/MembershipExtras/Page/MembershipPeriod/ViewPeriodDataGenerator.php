<?php
use CRM_MembershipExtras_BAO_MembershipPeriod as MembershipPeriod;

class CRM_MembershipExtras_Page_MembershipPeriod_ViewPeriodDataGenerator {

  private $periodId;

  /**
   * @var MembershipPeriod
   */
  private $membershipPeriod;

  public function __construct($periodId) {
    $this->periodId = $periodId;
    $this->setMembershipPeriod();
  }

  public function setMembershipPeriod() {
    $this->membershipPeriod = MembershipPeriod::getMembershipPeriodById($this->periodId);

    if (!$this->membershipPeriod) {
      throw new CRM_Core_Exception('Membership period Id could not be found');
    }
  }

  public function getMembershipPeriodData() {
    return $this->convertPeriodInfoToHumanReadableValues();
  }

  private function convertPeriodInfoToHumanReadableValues() {
    $contactName = '';
    $membershipType = '';

    $membership = civicrm_api3('Membership', 'get', [
      'sequential' => 1,
      'return' => ['contact_id.display_name', 'membership_type_id.name'],
      'id' => $this->membershipPeriod->membership_id,
    ]);

    if (!empty($membership['values'][0]['contact_id.display_name'])) {
      $contactName = $membership['values'][0]['contact_id.display_name'];
    }

    if (!empty($membership['values'][0]['membership_type_id.name'])) {
      $membershipType = $membership['values'][0]['membership_type_id.name'];
    }

    $membershipPeriod = $this->membershipPeriod->toArray();

    $membershipPeriod['contact_name'] = $contactName;
    $membershipPeriod['membership_type_name'] = $membershipType;
    $membershipPeriod['is_active'] = $membershipPeriod['is_active'] ? ts('Yes') : ts('No');
    $membershipPeriod['is_historic'] = $membershipPeriod['is_historic'] ? ts('Yes') : ts('No');

    $startDate = new DateTime($membershipPeriod['start_date']);
    $membershipPeriod['start_date'] = $startDate->format('Y-m-d');

    $endDate = new DateTime($membershipPeriod['end_date']);
    $membershipPeriod['end_date'] = $endDate->format('Y-m-d');

    $membershipPeriod['term_number'] = $this->membershipPeriod->calculateTermNumber();

    return $membershipPeriod;
  }

  public function getRecurContributionData() {
    if ($this->membershipPeriod->payment_entity_table == 'civicrm_contribution_recur') {
      $contributionRecur = civicrm_api3('ContributionRecur', 'get', [
        'sequential' => 1,
        'id' => $this->membershipPeriod->entity_id,
      ]);

      if (empty($contributionRecur['id'])) {
        return [];
      }

      return $contributionRecur['values'][0];
    }

    return [];
  }

  public function getContributions() {
    $contributionsResult = NULL;

    switch ($this->membershipPeriod->payment_entity_table) {
      case 'civicrm_contribution_recur':
        $contributionsResult = civicrm_api3('Contribution', 'get', [
          'sequential' => 1,
          'contribution_recur_id' => $this->membershipPeriod->entity_id,
          'options' => ['limit' => 0],
        ]);
        break;
      case 'civicrm_contribution':
        $contributionsResult = civicrm_api3('Contribution', 'get', [
          'sequential' => 1,
          'id' => $this->membershipPeriod->entity_id,
        ]);
        break;
    }

    if (empty($contributionsResult['values'])) {
      return [];
    }

    return $contributionsResult['values'];
  }

}
