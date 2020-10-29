<?php

use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_PeriodUnit as PeriodUnitSelectValues;
use CRM_MembershipExtras_SelectValues_AutoMembershipUpgradeRules_TriggerDateType as TriggerDateTypeSelectValues;

class CRM_MembershipExtras_Page_AutomatedMembershipUpgradeRule extends CRM_Core_Page {

  private $links = [];

  private $membershipIdsToLabelsMap;

  /**
   * @inheritdoc
   */
  public function run() {
    $this->browse();

    parent::run();
  }

  public function browse() {
    $upgradeRules = new CRM_MembershipExtras_BAO_AutoMembershipUpgradeRule();
    $upgradeRules->orderBy('weight');
    $upgradeRules->find();
    $rows = [];
    while ($upgradeRules->fetch()) {
      $rows[$upgradeRules->id] = [];
      CRM_Core_DAO::storeValues($upgradeRules, $rows[$upgradeRules->id]);

      $rows[$upgradeRules->id] = $this->updateRowLabels($rows[$upgradeRules->id]);

      $rows[$upgradeRules->id]['action'] = CRM_Core_Action::formLink(
        $this->generateActionLinks(),
        $this->calculateLinksMask($upgradeRules),
        ['id' => $upgradeRules->id]
      );
    }

    $returnURL = CRM_Utils_System::url('civicrm/admin/member/automated-upgrade-rules');
    CRM_Utils_Weight::addOrder($rows, 'CRM_MembershipExtras_DAO_AutoMembershipUpgradeRule', 'id', $returnURL);

    $this->assign('rows', $rows);
  }

  /**
   * Updates list view row values
   * to human-readable values.
   *
   * @param $rowValues
   * @return mixed
   */
  private function updateRowLabels($rowValues) {
    $membershipIdsToLabelsMap = $this->getMembershipIdsToLabelsMap();
    $rowValues['from_membership_label'] = $membershipIdsToLabelsMap[$rowValues['from_membership_type_id']];
    $rowValues['to_membership_label'] = $membershipIdsToLabelsMap[$rowValues['to_membership_type_id']];

    $periodLengthValues = PeriodUnitSelectValues::getAll();
    $rowValues['held_for'] = $rowValues['period_length'] . ' ' . $periodLengthValues[$rowValues['period_length_unit']];

    $triggerDateTypeValues = TriggerDateTypeSelectValues::getAll();
    $rowValues['basis'] = $triggerDateTypeValues[$rowValues['upgrade_trigger_date_type']];

    $rowValues['filter_group_label'] = '';
    if (!empty($rowValues['filter_group'])) {
      $rowValues['filter_group_label'] = $this->getGroupTitleById($rowValues['filter_group']);
    }

    return $rowValues;
  }

  private function getMembershipIdsToLabelsMap() {
    if (!empty($this->membershipIdsToLabelsMap)) {
      return $this->membershipIdsToLabelsMap;
    }

    $this->membershipIdsToLabelsMap = [];
    $membershipTypes = civicrm_api3('MembershipType', 'get', [
      'sequential' => 1,
      'return' => ['id', 'name'],
      'options' => ['limit' => 0],
    ]);

    if (!empty($membershipTypes['values'])) {
      foreach ($membershipTypes['values'] as $membershipType) {
        $this->membershipIdsToLabelsMap[$membershipType['id']] = $membershipType['name'];
      }
    }

    return $this->membershipIdsToLabelsMap;
  }

  private function getGroupTitleById($groupId) {
    $group = civicrm_api3('Group', 'get', [
      'sequential' => 1,
      'return' => ['title'],
      'id' => $groupId,
    ]);

    $groupTitle = '';
    if (!empty($group['values'][0])) {
      $groupTitle = $group['values'][0]['title'];
    }

    return $groupTitle;
  }

  private function generateActionLinks() {
    if (empty($this->links)) {
      $this->links = [
        CRM_Core_Action::UPDATE  => [
          'name'  => ts('Edit'),
          'url'   => 'civicrm/admin/member/automated-upgrade-rules/add',
          'qs'    => 'id=%%id%%&reset=1',
        ],
        CRM_Core_Action::ENABLE  => [
          'name'  => ts('Enable'),
          'class' => 'crm-enable-disable',
        ],
        CRM_Core_Action::DISABLE => [
          'name'  => ts('Disable'),
          'class' => 'crm-enable-disable',
        ],
        CRM_Core_Action::DELETE => [
          'name' => ts('Delete'),
          'url' => 'civicrm/admin/member/automated-upgrade-rules/delete',
          'qs' => 'id=%%id%%',
        ],
      ];
    }

    return $this->links;
  }

  private function calculateLinksMask($upgradeRule) {
    $mask = array_sum(array_keys($this->generateActionLinks()));

    if ($upgradeRule->is_active) {
      $mask -= CRM_Core_Action::ENABLE;
    }
    else {
      $mask -= CRM_Core_Action::DISABLE;
    }

    return $mask;
  }

}
