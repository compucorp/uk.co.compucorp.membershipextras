--
-- Table to store the relationship between a recurring contribution and line
-- items.
--
CREATE TABLE IF NOT EXISTS `membershipextras_contribution_recur_line_item` (
  `contribution_recur_id` int(11) NOT NULL,
  `line_item_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`contribution_recur_id`,`line_item_id`)
)
