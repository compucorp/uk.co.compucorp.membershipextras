<?php

/**
 * UpdateMembershipPeriod.run API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 */
function civicrm_api3_update_membership_period_run($params) {
  $UpdateMembershipPeriod = new CRM_MembershipExtras_Job_UpdateMembershipPeriod();
  
  return civicrm_api3_create_success(
    $UpdateMembershipPeriod->run,
    $params
  );
}
