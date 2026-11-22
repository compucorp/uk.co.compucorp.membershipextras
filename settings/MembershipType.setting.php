<?php

/*
 * Metadata for the per membership type settings (pro-rata calculation and
 * skip period of fixed period membership types), stored as a single array
 * keyed by membership type ID.
 *
 * Declared so the setting is valid for the Setting API: since CiviCRM 6.x
 * Setting.get and Setting.create reject settings without metadata.
 */
return [
  'membership_extras_membership_type_settings' => [
    'name' => 'membership_extras_membership_type_settings',
    'group_name' => 'MembershipExtras: Membership Type',
    'group' => 'membershipextras_membership_type',
    'type' => 'Array',
    'default' => NULL,
    'add' => '5.51',
    'title' => 'Membership type settings',
    'description' => 'Annual pro-rata settings of fixed period membership types, keyed by membership type ID.',
    'is_domain' => 1,
    'is_contact' => 0,
  ],
];
