SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `membershipextras_membership_rate_membership_type`;
DROP TABLE IF EXISTS `membershipextras_membership_rate`;
SET FOREIGN_KEY_CHECKS=1;

-- /*******************************************************
-- * membershipextras_membership_rate
-- * Entity to store different membership rates
-- *******************************************************/
CREATE TABLE `membershipextras_membership_rate` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique MembershipRate ID',
  `label` varchar(255) NULL COMMENT 'Rate Label',
  `min_range` decimal(20,2) NULL COMMENT 'Min Range Value',
  `max_range` decimal(20,2) NULL COMMENT 'Max Range Value',
  `multiplier` decimal(20,2) NULL COMMENT 'Subscription Multiplier',
  `min_subscription_rate` decimal(20,2) NULL COMMENT 'Min Subscription Rate',
  `sort_order` int unsigned NOT NULL COMMENT 'Sort Order',
  PRIMARY KEY (`id`)
)
ENGINE=InnoDB;

-- /*******************************************************
-- * membershipextras_membership_rate_membership_type
-- * One to Many Membership Rate - Membership Type Relationship 
-- *******************************************************/
CREATE TABLE `membershipextras_membership_rate_membership_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique MembershipRateMembershipType ID',
  `membership_rate_id` int unsigned COMMENT 'FK to MembershipRate',
  `membership_type_id` int unsigned COMMENT 'FK to MembershipType',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `unique_membership_rate_type_id` (membership_rate_id, membership_type_id),
  CONSTRAINT FK_membership_type_membership_rate_id FOREIGN KEY (`membership_rate_id`) REFERENCES `membershipextras_membership_rate`(`id`) ON DELETE CASCADE,
  CONSTRAINT FK_membership_type_membership_type_id FOREIGN KEY (`membership_type_id`) REFERENCES `civicrm_membership_type`(`id`) ON DELETE CASCADE
)
ENGINE=InnoDB;
