<?php

/**
 * OverdueMembershipPeriodProcessor.run API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 */
function civicrm_api3_overdue_membership_period_processor_run($params) {
  $overdueMembershipPeriodProcessor = new CRM_MembershipExtras_Job_OverdueMembershipPeriodProcessor();
  
  return civicrm_api3_create_success(
    $overdueMembershipPeriodProcessor->run(),
    $params
  );
}
