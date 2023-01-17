-- /*******************************************************
-- * Creates membershipextras_payment_scheme
-- *******************************************************/
CREATE TABLE `membershipextras_payment_scheme` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique PaymentScheme ID',
  `name` varchar(255) NOT NULL,
  `admin_title` varchar(255) NOT NULL,
  `description` varchar(255),
  `public_title` varchar(255) NOT NULL,
  `public_description` varchar(255),
  `permission` varchar(10) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT false,
  `parameters` text NOT NULL,
  PRIMARY KEY (`id`)
)
  ENGINE=InnoDB;
