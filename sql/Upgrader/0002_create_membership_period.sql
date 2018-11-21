SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `membershipextras_membership_period`;

SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE `membershipextras_membership_period` (
  `id` int unsigned NOT NULL AUTO_INCREMENT  COMMENT 'Unique MembershipPeriod ID',
  `membership_id` int unsigned NOT NULL   COMMENT 'ID of the membership.',
  `start_date` datetime NOT NULL   COMMENT 'Start date of the period for the membership/recurring contribution.',
  `end_date` datetime NULL   COMMENT 'End date of the period for the membership/recurring contribution.',
  `payment_entity_table` varchar(64) NOT NULL   COMMENT 'Either civicrm_contribution or civicrm_contribution_recur.',
  `entity_id` int unsigned NOT NULL   COMMENT 'ID of the payment entity.',
  `is_active` tinyint NOT NULL  DEFAULT false COMMENT 'Whether the period has taken effect or not.',
  `is_historic` tinyint NOT NULL  DEFAULT false COMMENT 'Whether it is an historical period created before the membership extras is installed.',

  PRIMARY KEY (`id`),

  CONSTRAINT FK_membershipextras_membership_period_membership_id
    FOREIGN KEY (`membership_id`) REFERENCES `civicrm_membership`(`id`)
    ON DELETE CASCADE
);
