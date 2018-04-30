DELETE FROM civicrm_setting WHERE `name` LIKE 'membershipextras_paymentplan_%';

-- /*******************************************************
-- * Delete External IDs value tables
-- *******************************************************/
DROP TABLE IF EXISTS `civicrm_value_contribution_ext_id`;
DROP TABLE IF EXISTS `civicrm_value_contribution_recur_ext_id`;
DROP TABLE IF EXISTS `civicrm_value_membership_ext_id`;
DROP TABLE IF EXISTS `civicrm_value_line_item_ext_id`;
