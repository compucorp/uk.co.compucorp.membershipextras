SET FOREIGN_KEY_CHECKS=0;

DELETE FROM civicrm_setting WHERE `name` LIKE 'membershipextras_paymentplan_%';

-- /*******************************************************
-- * Delete Relationships between recurring contributions
-- * and line items
-- *******************************************************/
DELETE FROM civicrm_line_item WHERE `id` IN (
    SELECT membershipextras_subscription_line.line_item_id
    FROM membershipextras_subscription_line
);
DROP TABLE IF EXISTS `membershipextras_subscription_line`;

-- /*******************************************************
-- * Delete Auto Upgrade rules table
-- *******************************************************/
DROP TABLE IF EXISTS `membershipextras_auto_membership_upgrade_rule`;

SET FOREIGN_KEY_CHECKS=1;
