<?php
// This file declares a new entity type. For more details, see "hook_civicrm_entityTypes" at:
// https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes

return [
  [
    'name' => 'MembershipRate',
    'class' => 'CRM_MembershipExtras_DAO_MembershipRate',
    'table' => 'civicrm_membership_rate',
  ],
];
