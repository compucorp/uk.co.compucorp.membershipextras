<?php
 /**
 * Generic contribution utilities.
 */
class CRM_MembershipExtras_Service_ContributionUtilities {

  /**
   * Obtains list of contribution statuses.
   *
   * @return array
   */
  private static function getContributionStatuses() {
    try {
      $contributionStatuses = civicrm_api3('OptionValue', 'get', [
        'sequential' => 1,
        'return' => ['name', 'value'],
        'option_group_id' => 'contribution_status',
        'options' => ['limit' => 0],
      ])['values'];
    } catch (Exception $e) {
      return [];
    }

    return $contributionStatuses;
  }

  /**
   * Gets contribution Statuses Name to value Mapping
   *
   * @return array $contributionStatusesNameMap
   */
  public static function getStatusesNameMap() {
    $contributionStatuses = self::getContributionStatuses();

    $contributionStatusesNameMap = [];
    foreach ($contributionStatuses as $status) {
      $contributionStatusesNameMap[$status['name']] = $status['value'];
    }
    
    return $contributionStatusesNameMap;
  }

  /**
   * Maps contribution statuses to values.
   *
   * @return array
   */
  public static function getStatusesValueMap() {
    $contributionStatuses = self::getContributionStatuses();

    $contributionStatusesValueMap = [];
    foreach ($contributionStatuses as $status) {
      $contributionStatusesValueMap[$status['value']] = $status['name'];
    }

    return $contributionStatusesValueMap;
  }

}
