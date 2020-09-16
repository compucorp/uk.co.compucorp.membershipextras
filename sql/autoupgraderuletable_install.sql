SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `membershipextras_auto_membership_upgrade_rule`;
SET FOREIGN_KEY_CHECKS=1;

-- /*******************************************************
-- * Create auto upgrade rules table
-- *******************************************************/
CREATE TABLE `membershipextras_auto_membership_upgrade_rule` (
   `id` int unsigned NOT NULL AUTO_INCREMENT,
   `name` varchar(255) NOT NULL,
   `label` varchar(255) NOT NULL,
   `from_membership_type_id` int unsigned NOT NULL   COMMENT 'FK to civicrm_membership_type table',
   `to_membership_type_id` int unsigned NOT NULL   COMMENT 'FK to civicrm_membership_type table',
   `upgrade_trigger_date_type` int unsigned NOT NULL  DEFAULT 1 ,
   `period_length` int unsigned NOT NULL  DEFAULT 1 ,
   `period_length_unit` int unsigned NOT NULL  DEFAULT 1,
   `filter_group` int unsigned    COMMENT 'FK to civicrm_group table',
   `weight` int unsigned NOT NULL,
   `is_active` tinyint   DEFAULT 1,

   PRIMARY KEY (`id`),

   CONSTRAINT FK_membershipextras_upgraderule_from_membership_type_id
     FOREIGN KEY (`from_membership_type_id`) REFERENCES `civicrm_membership_type`(`id`)
     ON DELETE CASCADE,

   CONSTRAINT FK_membershipextras_upgraderule_to_membership_type_id
     FOREIGN KEY (`to_membership_type_id`) REFERENCES `civicrm_membership_type`(`id`)
     ON DELETE CASCADE,

   CONSTRAINT FK_membershipextras_upgraderule_filter_group
     FOREIGN KEY (`filter_group`) REFERENCES `civicrm_group`(`id`)
     ON DELETE SET NULL
);
