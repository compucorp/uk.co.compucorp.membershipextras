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
  SELECT membershipextras_contribrecur_lineitem.line_item_id
  FROM membershipextras_contribrecur_lineitem
);
DROP TABLE IF EXISTS `membershipextras_contribrecur_lineitem`;

SET FOREIGN_KEY_CHECKS=1;
