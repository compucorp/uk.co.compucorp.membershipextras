RENAME TABLE `membershipextras_contribrecur_lineitem` TO `membershipextras_subscription_line`;

ALTER TABLE `membershipextras_subscription_line` MODIFY `auto_renew` TINYINT NOT NULL DEFAULT false;

ALTER TABLE `membershipextras_subscription_line`
ADD COLUMN `is_removed` TINYINT NOT NULL DEFAULT false COMMENT 'If the line-item has been marked as removed or not.';
