<?php

/**
 * Generic contribution utilities.
 */
class CRM_MembershipExtras_Service_ContributionUtilities {
  /**
   * Gets contribution Statuses Name to value Mapping
   *
   * @return array $contributionStatusesNameMap
   */
  public static function getStatusesNameMap() {
    $contributionStatuses = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'return' => ['name', 'value'],
      'option_group_id' => 'contribution_status',
      'options' => ['limit' => 0],
    ])['values'];

    $contributionStatusesNameMap = [];
    foreach ($contributionStatuses as $status) {
      $contributionStatusesNameMap[$status['name']] = $status['value'];
    }

    return $contributionStatusesNameMap;
  }
}
