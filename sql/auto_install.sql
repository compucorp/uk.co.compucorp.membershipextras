SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `membershipextras_subscription_line`;

SET FOREIGN_KEY_CHECKS=1;
CREATE TABLE `membershipextras_subscription_line` (
  `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Discount Item ID',
  `contribution_recur_id` int unsigned NOT NULL   COMMENT 'ID of the recurring contribution.',
  `line_item_id` int unsigned NOT NULL   COMMENT 'ID of the line item related to the recurring contribution.',
  `start_date` datetime NULL   COMMENT 'Start date of the period for the membership/recurring contribution.',
  `end_date` datetime NULL   COMMENT 'End date of the period for the membership/recurring contribution.',
  `auto_renew` tinyint NOT NULL  DEFAULT false COMMENT 'If the line-item should be auto-renewed or not.',
  `is_removed` tinyint NOT NULL  DEFAULT false COMMENT 'If the line-item has been marked as removed or not.',

  PRIMARY KEY (`id`),

  UNIQUE INDEX `index_contribrecurid_lineitemid` (
    contribution_recur_id,
    line_item_id
  ),

  CONSTRAINT FK_membershipextras_subscription_line_contribution_recur_id
    FOREIGN KEY (`contribution_recur_id`) REFERENCES `civicrm_contribution_recur`(`id`)
    ON DELETE CASCADE,

  CONSTRAINT FK_membershipextras_subscription_line_line_item_id
    FOREIGN KEY (`line_item_id`) REFERENCES `civicrm_line_item`(`id`)
    ON DELETE CASCADE  
);

-- /*******************************************************
-- *
-- * civicrm_membersonlyevent
-- *
-- * Stores members-only event configurations
-- *
-- *******************************************************/
CREATE TABLE `membersonlyevent` (
     `id` int unsigned NOT NULL AUTO_INCREMENT  ,
     `event_id` int unsigned NOT NULL   COMMENT 'Foreign key for the Event',
     `purchase_membership_button` tinyint   DEFAULT 0 COMMENT 'Should we provide membership purchase button when access to event denied ?',
     `notice_for_access_denied` text   DEFAULT NULL COMMENT 'Notice message to show to the user when the access to members-only event denied.',
     `purchase_membership_button_label` varchar(255)   DEFAULT NULL COMMENT 'Purchase membership button label if it is enabled',
     `purchase_membership_link_type` int   DEFAULT 0 COMMENT '0: contribution page, 1: custom URL',
     `contribution_page_id` int unsigned   DEFAULT NULL COMMENT 'Foreign key for the Contribution page',
     `purchase_membership_url` varchar(3000)   DEFAULT NULL COMMENT 'Purchase membership page URL',
    PRIMARY KEY ( `id` )
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;

-- /*******************************************************
-- *
-- * membersonlyevent_event_membership_type
-- *
-- * Joining table for members-only event and allowed membership types,
-- * In other words, this is where allowed membership types for members-only
-- * event are stored.
-- *
-- *******************************************************/
CREATE TABLE `membersonlyevent_event_membership_type` (
     `members_only_event_id` int unsigned NOT NULL   COMMENT 'Members-only event ID.',
     `membership_type_id` int unsigned NOT NULL   COMMENT 'Allowed Membership Type ID.',
    INDEX `index_event_id_membership_type_id`(members_only_event_id, membership_type_id)
)  ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci  ;