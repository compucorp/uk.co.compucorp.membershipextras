-- /*******************************************************
-- * Creates membershipextras_payment_scheme
-- *******************************************************/
CREATE TABLE `membershipextras_payment_scheme` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique PaymentScheme ID',
  `name` varchar(250) NOT NULL,
  `admin_title` varchar(250),
  `description` varchar(500),
  `public_title` varchar(250) NOT NULL,
  `public_description` varchar(500) NOT NULL,
  `payment_processor` int unsigned NOT NULL,
  `permission` varchar(10) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT false,
  `parameters` text NOT NULL,
  PRIMARY KEY (`id`),

  CONSTRAINT FK_membershipextras_payment_scheme_payment_processor
  FOREIGN KEY (`payment_processor`) REFERENCES `civicrm_payment_processor`(`id`)
  ON DELETE CASCADE
)
  ENGINE=InnoDB;
