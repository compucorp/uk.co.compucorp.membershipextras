SET FOREIGN_KEY_CHECKS=0;

DELETE FROM civicrm_setting WHERE `name` LIKE 'membershipextras_paymentplan_%';

-- /*******************************************************
-- * Delete External IDs value tables
-- *******************************************************/
DROP TABLE IF EXISTS `civicrm_value_contribution_ext_id`;
DROP TABLE IF EXISTS `civicrm_value_contribution_recur_ext_id`;
DROP TABLE IF EXISTS `civicrm_value_membership_ext_id`;
DROP TABLE IF EXISTS `civicrm_value_line_item_ext_id`;
-- /*******************************************************
-- * Delete Relationships between recurring contributions
-- * and line items
-- *******************************************************/
DELETE FROM civicrm_line_item WHERE `id` IN (
  SELECT membershipextras_subscription_line.line_item_id
  FROM membershipextras_subscription_line
);

DROP TABLE IF EXISTS `membershipextras_membership_period`;
DROP TABLE IF EXISTS `membershipextras_subscription_line`;

SET FOREIGN_KEY_CHECKS=1;
