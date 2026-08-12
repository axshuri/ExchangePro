-- ExchangePro backup 2026-08-12 10:12:41
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `account_currencies`;
CREATE TABLE `account_currencies` (
  `account_id` int unsigned NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `balance` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`,`currency_id`),
  KEY `fk_acctcur_currency` (`currency_id`),
  CONSTRAINT `fk_acctcur_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acctcur_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `account_currencies` (`account_id`,`currency_id`,`balance`,`updated_at`) VALUES ('1','1','2000.0000000000','2026-08-12 10:12:41');
INSERT INTO `account_currencies` (`account_id`,`currency_id`,`balance`,`updated_at`) VALUES ('1','5','102667.9000000000','2026-08-12 10:12:41');
INSERT INTO `account_currencies` (`account_id`,`currency_id`,`balance`,`updated_at`) VALUES ('2','1','6647.3800738007','2026-08-12 10:12:41');
INSERT INTO `account_currencies` (`account_id`,`currency_id`,`balance`,`updated_at`) VALUES ('2','2','4003.0100334449','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('cash_desk','vault','bank','wallet','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cash_desk',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `bank_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_number` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `account_holder` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `accounts` (`id`,`code`,`name`,`type`,`is_active`,`bank_name`,`account_number`,`account_holder`,`notes`,`created_at`,`updated_at`) VALUES ('1','DESK-1','Main Cash Desk','cash_desk','1',NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `accounts` (`id`,`code`,`name`,`type`,`is_active`,`bank_name`,`account_number`,`account_holder`,`notes`,`created_at`,`updated_at`) VALUES ('2','VAULT-1','Main Vault','vault','1',NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');

DROP TABLE IF EXISTS `attachments`;
CREATE TABLE `attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stored_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned DEFAULT NULL,
  `uploaded_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_value` json DEFAULT NULL,
  `new_value` json DEFAULT NULL,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_time` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('1','1','update_rates','currency','1',NULL,'{\"buy_rate\": \"1.3550000000\", \"sell_rate\": \"1.3750000000\"}','','','Changed USD rates','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('2','1','update_rates','currency','2',NULL,'{\"buy_rate\": \"1.4750000000\", \"sell_rate\": \"1.4950000000\"}','','','Changed EUR rates','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('3','1','BUY_transaction','transaction','1',NULL,'{\"rate\": \"1.3600000000\", \"amount\": \"1000.0000000000\", \"currency\": \"USD\", \"entry_id\": 2, \"base_amount\": \"1360.0000000000\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('4','1','SELL_transaction','transaction','2',NULL,'{\"rate\": \"1.3800000000\", \"amount\": \"1000.0000000000\", \"currency\": \"USD\", \"entry_id\": 3, \"base_amount\": \"1380.0000000000\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('5','1','cancel_transaction','transaction','1','{\"status\": \"completed\"}','{\"status\": \"reversed\", \"reversal_id\": 3}','','','test reversal','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('6','1','EXCHANGE_transaction','transaction','4',NULL,'{\"source\": \"USD\", \"target\": \"EUR\", \"entry_id\": 9, \"cross_rate\": \"0.9063545150\", \"source_amount\": \"1000.0000000000\", \"target_amount\": \"906.3545150501\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('7','1','SELL_transaction','transaction','5',NULL,'{\"rate\": \"1.3800000000\", \"amount\": \"100.0000000000\", \"currency\": \"USD\", \"entry_id\": 10, \"base_amount\": \"138.0000000000\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('8','1','SELL_transaction','transaction','6',NULL,'{\"rate\": \"1.3000000000\", \"amount\": \"200.0000000000\", \"currency\": \"USD\", \"entry_id\": 11, \"base_amount\": \"260.0000000000\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('9','1','BUY_transaction','transaction','7',NULL,'{\"rate\": \"1.3600000000\", \"amount\": \"100.0000000000\", \"currency\": \"USD\", \"entry_id\": 12, \"base_amount\": \"136.0000000000\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('10','1','SELL_transaction','transaction','8',NULL,'{\"rate\": \"1.3600000000\", \"amount\": \"100.0000000000\", \"currency\": \"USD\", \"entry_id\": 13, \"base_amount\": \"136.0000000000\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('11','1','EXCHANGE_transaction','transaction','9',NULL,'{\"source\": \"USD\", \"target\": \"EUR\", \"entry_id\": 14, \"cross_rate\": \"0.9063545150\", \"source_amount\": \"100.0000000000\", \"target_amount\": \"90.6354515050\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('12','1','EXCHANGE_transaction','transaction','10',NULL,'{\"source\": \"USD\", \"target\": \"EUR\", \"entry_id\": 15, \"cross_rate\": \"0.9063545150\", \"source_amount\": \"150.0000000000\", \"target_amount\": \"135.9531772575\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('13','1','cancel_transaction','transaction','10','{\"status\": \"completed\"}','{\"status\": \"reversed\", \"reversal_id\": 11}','','','test exchange reversal','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('14','1','rate_sync_update','currency','2','{\"buy_rate\": \"1.4750000000\", \"sell_rate\": \"1.4950000000\", \"reference_rate\": null}','{\"buy_rate\": \"1.5920000000\", \"sell_rate\": \"1.6080000000\", \"reference_rate\": \"1.6000000000\"}','','','Automatic rate synchronization','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('15','1','rate_sync_update','currency','1','{\"buy_rate\": \"1.3550000000\", \"sell_rate\": \"1.3750000000\", \"reference_rate\": null}','{\"buy_rate\": \"1.3266666667\", \"sell_rate\": \"1.3399999999\", \"reference_rate\": \"1.3333333333\"}','','','Automatic rate synchronization','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('16','1','rate_sync','rates',NULL,NULL,'{\"failed\": 0, \"status\": \"success\", \"skipped\": 36, \"updated\": 5, \"provider\": \"frankfurter\", \"triggered_by\": \"manual\"}','','','Automatic rate synchronization (manual)','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('17','1','rate_sync','rates',NULL,NULL,'{\"failed\": 0, \"status\": \"success\", \"skipped\": 36, \"updated\": 5, \"provider\": \"frankfurter\", \"triggered_by\": \"manual\"}','','','Automatic rate synchronization (manual)','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('18','1','rate_sync','rates',NULL,NULL,NULL,'','','Rate synchronization failed: provider down (fake)','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('19','1','rate_sync','rates',NULL,NULL,NULL,'','','Rate synchronization failed: Provider returned an invalid (zero/negative/non-numeric) rate for USD.','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('20','1','rate_sync','rates',NULL,NULL,'{\"failed\": 1, \"status\": \"partial\", \"skipped\": 36, \"updated\": 4, \"provider\": \"frankfurter\", \"triggered_by\": \"manual\"}','','','Automatic rate synchronization (manual)','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('21','1','rate_sync','rates',NULL,NULL,'{\"failed\": 0, \"status\": \"success\", \"skipped\": 37, \"updated\": 4, \"provider\": \"frankfurter\", \"triggered_by\": \"manual\"}','','','Automatic rate synchronization (manual)','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('22','1','rate_sync','rates',NULL,NULL,'{\"failed\": 0, \"status\": \"success\", \"skipped\": 36, \"updated\": 5, \"provider\": \"frankfurter\", \"triggered_by\": \"manual\"}','','','Automatic rate synchronization (manual)','2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('23','1','SELL_transaction','transaction','12',NULL,'{\"rate\": \"1.3800000000\", \"amount\": \"1000.0000000000\", \"currency\": \"USD\", \"entry_id\": 17, \"base_amount\": \"1380.0000000000\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('24','1','SELL_transaction','transaction','13',NULL,'{\"rate\": \"1.4000000000\", \"amount\": \"100.0000000000\", \"currency\": \"USD\", \"entry_id\": 18, \"base_amount\": \"140.0000000000\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('25','1','update_inventory_targets','currency',NULL,NULL,'{\"count\": 1}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('26','1','open_day','daily_closing',NULL,NULL,'{\"date\": \"2026-08-12\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('27','1','close_day','daily_closing','1',NULL,'{\"date\": \"2026-08-12\", \"differences\": 0}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('28','1','approve_day','daily_closing','1','{\"status\": \"closed\"}','{\"date\": \"2026-08-12\", \"status\": \"approved\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('29','1','reopen_day','daily_closing','1','{\"status\": \"approved\"}','{\"date\": \"2026-08-12\", \"status\": \"in_progress\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('30','1','close_day','daily_closing','1',NULL,'{\"date\": \"2026-08-12\", \"differences\": 0}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('31','1','approve_day','daily_closing','1','{\"status\": \"closed\"}','{\"date\": \"2026-08-12\", \"status\": \"approved\"}','','',NULL,'2026-08-12 10:12:41');
INSERT INTO `audit_logs` (`id`,`user_id`,`action`,`entity_type`,`entity_id`,`previous_value`,`new_value`,`ip`,`user_agent`,`reason`,`created_at`) VALUES ('32','1','SELL_transaction','transaction','14',NULL,'{\"rate\": \"1.4000000000\", \"amount\": \"10.0000000000\", \"currency\": \"USD\", \"entry_id\": 19, \"base_amount\": \"14.0000000000\"}','','',NULL,'2026-08-12 10:12:41');

DROP TABLE IF EXISTS `backup_records`;
CREATE TABLE `backup_records` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint unsigned DEFAULT NULL,
  `checksum` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encrypted` tinyint(1) NOT NULL DEFAULT '0',
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  `kind` enum('manual','scheduled','restore_point') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `status` enum('ok','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ok',
  `created_by` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cash_count_items`;
CREATE TABLE `cash_count_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cash_count_id` bigint unsigned NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `denomination_id` int unsigned DEFAULT NULL,
  `quantity` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `total` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  PRIMARY KEY (`id`),
  KEY `fk_cci_count` (`cash_count_id`),
  KEY `fk_cci_currency` (`currency_id`),
  KEY `fk_cci_denom` (`denomination_id`),
  CONSTRAINT `fk_cci_count` FOREIGN KEY (`cash_count_id`) REFERENCES `cash_counts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cci_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_cci_denom` FOREIGN KEY (`denomination_id`) REFERENCES `currency_denominations` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `cash_counts`;
CREATE TABLE `cash_counts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `count_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` int unsigned NOT NULL,
  `count_date` date NOT NULL,
  `status` enum('draft','confirmed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `employee_id` int unsigned NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `count_number` (`count_number`),
  KEY `fk_cc_account` (`account_id`),
  KEY `fk_cc_employee` (`employee_id`),
  CONSTRAINT `fk_cc_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_cc_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `currencies`;
CREATE TABLE `currencies` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `localized_name` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `symbol` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount_precision` tinyint NOT NULL DEFAULT '2',
  `rate_precision` tinyint NOT NULL DEFAULT '4',
  `is_base` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `min_amount` decimal(30,10) DEFAULT NULL,
  `max_amount` decimal(30,10) DEFAULT NULL,
  `max_inventory` decimal(30,10) DEFAULT NULL,
  `target_inventory` decimal(30,10) DEFAULT NULL,
  `min_inventory` decimal(30,10) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('1','USD','United States Dollar','دلار آمریکا','$','2','4','0','1',NULL,NULL,'30000.0000000000','15000.0000000000','8000.0000000000',NULL,'2026-08-12 10:12:40','2026-08-12 10:12:41');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('2','EUR','Euro','یورو','€','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('3','GBP','British Pound','پوند بریتانیا','£','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('4','CHF','Swiss Franc','فرانک سوئیس','CHF','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('5','CAD','Canadian Dollar','دلار کانادا','$','2','4','1','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('6','AUD','Australian Dollar','دلار استرالیا','A$','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('7','NZD','New Zealand Dollar','دلار نیوزیلند','NZ$','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('8','JPY','Japanese Yen','ین ژاپن','¥','0','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('9','CNY','Chinese Yuan','یوان چین','¥','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('10','HKD','Hong Kong Dollar','دلار هنگ‌کنگ','HK$','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('11','SGD','Singapore Dollar','دلار سنگاپور','S$','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('12','KRW','South Korean Won','وون کره جنوبی','₩','0','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('13','INR','Indian Rupee','روپیه هند','₹','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('14','AED','UAE Dirham','درهم امارات','AED','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('15','SAR','Saudi Riyal','ریال عربستان','SAR','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('16','QAR','Qatari Riyal','ریال قطر','QAR','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('17','KWD','Kuwaiti Dinar','دینار کویت','KWD','3','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('18','BHD','Bahraini Dinar','دینار بحرین','BHD','3','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('19','OMR','Omani Rial','ریال عمان','OMR','3','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('20','JOD','Jordanian Dinar','دینار اردن','JOD','3','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('21','TRY','Turkish Lira','لیر ترکیه','₺','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('22','RUB','Russian Ruble','روبل روسیه','₽','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('23','AZN','Azerbaijani Manat','منات آذربایجان','AZN','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('24','GEL','Georgian Lari','لاری گرجستان','GEL','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('25','AMD','Armenian Dram','درام ارمنستان','AMD','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('26','IQD','Iraqi Dinar','دینار عراق','IQD','0','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('27','PKR','Pakistani Rupee','روپیه پاکستان','PKR','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('28','AFN','Afghan Afghani','افغانی افغانستان','AFN','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('29','KZT','Kazakhstani Tenge','تنگه قزاقستان','KZT','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('30','TMT','Turkmenistan Manat','منات ترکمنستان','TMT','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('31','UZS','Uzbekistani Som','سوم ازبکستان','UZS','0','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('32','TJS','Tajikistani Somoni','سامانی تاجیکستان','TJS','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('33','MYR','Malaysian Ringgit','رینگیت مالزی','RM','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('34','THB','Thai Baht','بات تایلند','฿','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('35','IDR','Indonesian Rupiah','روپیه اندونزی','Rp','0','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('36','ILS','Israeli New Shekel','شِکِل اسرائیل','₪','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('37','EGP','Egyptian Pound','پوند مصر','EGP','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('38','ZAR','South African Rand','رَند آفریقای جنوبی','R','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('39','NOK','Norwegian Krone','کرون نروژ','kr','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('40','SEK','Swedish Krona','کرون سوئد','kr','2','4','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('41','IRR','Iranian Rial','ریال ایران','IRR','0','2','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');
INSERT INTO `currencies` (`id`,`code`,`name`,`localized_name`,`symbol`,`amount_precision`,`rate_precision`,`is_base`,`is_active`,`min_amount`,`max_amount`,`max_inventory`,`target_inventory`,`min_inventory`,`notes`,`created_at`,`updated_at`) VALUES ('42','IRT','Iranian Toman','تومان ایران','IRT','0','2','0','1',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:12:40','2026-08-12 10:12:40');

DROP TABLE IF EXISTS `currency_denominations`;
CREATE TABLE `currency_denominations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `currency_id` int unsigned NOT NULL,
  `kind` enum('banknote','coin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'banknote',
  `value` decimal(30,10) NOT NULL,
  `label` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `fk_den_currency` (`currency_id`),
  CONSTRAINT `fk_den_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('1','5','banknote','100.0000000000','100','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('2','5','banknote','50.0000000000','50','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('3','5','banknote','20.0000000000','20','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('4','5','banknote','10.0000000000','10','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('5','5','banknote','5.0000000000','5','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('6','5','coin','2.0000000000','2','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('7','5','coin','1.0000000000','1','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('8','5','coin','0.2500000000','0.25','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('9','1','banknote','100.0000000000','100','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('10','1','banknote','50.0000000000','50','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('11','1','banknote','20.0000000000','20','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('12','1','banknote','10.0000000000','10','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('13','1','banknote','5.0000000000','5','1');
INSERT INTO `currency_denominations` (`id`,`currency_id`,`kind`,`value`,`label`,`is_active`) VALUES ('14','1','banknote','1.0000000000','1','1');

DROP TABLE IF EXISTS `customer_accounts`;
CREATE TABLE `customer_accounts` (
  `customer_id` int unsigned NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `balance` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`customer_id`,`currency_id`),
  KEY `fk_ca_currency` (`currency_id`),
  CONSTRAINT `fk_ca_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ca_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `id_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `id_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','blocked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_transaction_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `customers` (`id`,`code`,`full_name`,`phone`,`email`,`address`,`id_type`,`id_number`,`notes`,`status`,`created_at`,`updated_at`,`last_transaction_at`) VALUES ('1','C-00001','Test Customer','+10000000000',NULL,NULL,NULL,NULL,NULL,'active','2026-08-12 10:12:41','2026-08-12 10:12:41',NULL);

DROP TABLE IF EXISTS `daily_closings`;
CREATE TABLE `daily_closings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `closing_date` date NOT NULL,
  `status` enum('open','in_progress','closed','approved') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `opened_by` int unsigned NOT NULL,
  `closed_by` int unsigned DEFAULT NULL,
  `opened_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `opening_balances` json DEFAULT NULL,
  `closing_balances` json DEFAULT NULL,
  `differences` json DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `closing_date` (`closing_date`),
  KEY `fk_dc_opened` (`opened_by`),
  KEY `fk_dc_closed` (`closed_by`),
  CONSTRAINT `fk_dc_closed` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_dc_opened` FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `daily_closings` (`id`,`closing_date`,`status`,`opened_by`,`closed_by`,`opened_at`,`closed_at`,`approved_by`,`approved_at`,`opening_balances`,`closing_balances`,`differences`,`notes`) VALUES ('1','2026-08-12','approved','1','1','2026-08-12 10:12:41','2026-08-12 10:12:41','1','2026-08-12 10:12:41','{\"1:1\": {\"amount\": \"2000.0000000000\", \"account_id\": 1, \"currency_id\": 1}, \"1:5\": {\"amount\": \"102653.9000000000\", \"account_id\": 1, \"currency_id\": 5}, \"2:1\": {\"amount\": \"6657.3800738007\", \"account_id\": 2, \"currency_id\": 1}, \"2:2\": {\"amount\": \"4003.0100334449\", \"account_id\": 2, \"currency_id\": 2}}','{\"1:1\": {\"amount\": \"2000.0000000000\", \"account_id\": 1, \"currency_id\": 1}, \"1:5\": {\"amount\": \"102653.9000000000\", \"account_id\": 1, \"currency_id\": 5}, \"2:1\": {\"amount\": \"6657.3800738007\", \"account_id\": 2, \"currency_id\": 1}, \"2:2\": {\"amount\": \"4003.0100334449\", \"account_id\": 2, \"currency_id\": 2}}','[]','re-close');

DROP TABLE IF EXISTS `exchange_rate_history`;
CREATE TABLE `exchange_rate_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `currency_id` int unsigned NOT NULL,
  `base_currency_id` int unsigned NOT NULL,
  `reference_rate` decimal(30,10) NOT NULL,
  `provider` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_timestamp` datetime DEFAULT NULL,
  `retrieved_at` datetime NOT NULL,
  `sync_id` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_erh_cur_time` (`currency_id`,`retrieved_at`),
  CONSTRAINT `fk_erh_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('1','9','5','0.2051282051','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('2','2','5','1.6000000000','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('3','3','5','1.7777777777','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('4','21','5','0.0290909090','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('5','1','5','1.3333333333','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('6','9','5','0.2051282051','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','2','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('7','2','5','1.6000000000','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','2','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('8','3','5','1.7777777777','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','2','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('9','21','5','0.0290909090','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','2','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('10','1','5','1.3333333333','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','2','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('11','9','5','0.2051282051','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','5','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('12','2','5','1.6000000000','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','5','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('13','3','5','1.7777777777','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','5','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('14','21','5','0.0290909090','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','5','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('15','9','5','0.2051282051','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','6','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('16','2','5','1.6000000000','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','6','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('17','3','5','1.7777777777','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','6','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('18','21','5','0.0290909090','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','6','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('19','9','5','0.2051282051','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','7','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('20','2','5','1.6000000000','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','7','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('21','3','5','1.7777777777','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','7','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('22','21','5','0.0290909090','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','7','2026-08-12 10:12:41');
INSERT INTO `exchange_rate_history` (`id`,`currency_id`,`base_currency_id`,`reference_rate`,`provider`,`provider_timestamp`,`retrieved_at`,`sync_id`,`created_at`) VALUES ('23','1','5','1.3333333333','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','7','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `exchange_rates`;
CREATE TABLE `exchange_rates` (
  `currency_id` int unsigned NOT NULL,
  `buy_rate` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `sell_rate` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `mid_rate` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `previous_buy` decimal(30,10) DEFAULT NULL,
  `previous_sell` decimal(30,10) DEFAULT NULL,
  `reference_rate` decimal(30,10) DEFAULT NULL,
  `previous_reference` decimal(30,10) DEFAULT NULL,
  `buy_spread_type` enum('fixed','percent') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buy_spread_value` decimal(30,10) DEFAULT NULL,
  `sell_spread_type` enum('fixed','percent') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sell_spread_value` decimal(30,10) DEFAULT NULL,
  `buy_override` decimal(30,10) DEFAULT NULL,
  `sell_override` decimal(30,10) DEFAULT NULL,
  `override_persistent` tinyint(1) NOT NULL DEFAULT '0',
  `source` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_manual` tinyint(1) NOT NULL DEFAULT '1',
  `provider` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_timestamp` datetime DEFAULT NULL,
  `retrieved_at` datetime DEFAULT NULL,
  `rate_status` enum('online','cached','stale','manual') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `updated_by` int unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`currency_id`),
  KEY `fk_rates_user` (`updated_by`),
  CONSTRAINT `fk_rates_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rates_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `exchange_rates` (`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`previous_buy`,`previous_sell`,`reference_rate`,`previous_reference`,`buy_spread_type`,`buy_spread_value`,`sell_spread_type`,`sell_spread_value`,`buy_override`,`sell_override`,`override_persistent`,`source`,`is_manual`,`provider`,`provider_timestamp`,`retrieved_at`,`rate_status`,`updated_by`,`updated_at`) VALUES ('1','1.3266666667','1.3399999999','1.3333333333','1.3266666667','1.3399999999','1.3333333333','1.3333333333',NULL,NULL,NULL,NULL,NULL,NULL,'0','api','0','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','online','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rates` (`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`previous_buy`,`previous_sell`,`reference_rate`,`previous_reference`,`buy_spread_type`,`buy_spread_value`,`sell_spread_type`,`sell_spread_value`,`buy_override`,`sell_override`,`override_persistent`,`source`,`is_manual`,`provider`,`provider_timestamp`,`retrieved_at`,`rate_status`,`updated_by`,`updated_at`) VALUES ('2','1.5920000000','1.6080000000','1.6000000000','1.5920000000','1.6080000000','1.6000000000','1.6000000000',NULL,NULL,NULL,NULL,NULL,NULL,'0','api','0','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','online','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rates` (`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`previous_buy`,`previous_sell`,`reference_rate`,`previous_reference`,`buy_spread_type`,`buy_spread_value`,`sell_spread_type`,`sell_spread_value`,`buy_override`,`sell_override`,`override_persistent`,`source`,`is_manual`,`provider`,`provider_timestamp`,`retrieved_at`,`rate_status`,`updated_by`,`updated_at`) VALUES ('3','1.7688888889','1.7866666665','1.7777777777','1.7688888889','1.7866666665','1.7777777777','1.7777777777',NULL,NULL,NULL,NULL,NULL,NULL,'0','api','0','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','online','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rates` (`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`previous_buy`,`previous_sell`,`reference_rate`,`previous_reference`,`buy_spread_type`,`buy_spread_value`,`sell_spread_type`,`sell_spread_value`,`buy_override`,`sell_override`,`override_persistent`,`source`,`is_manual`,`provider`,`provider_timestamp`,`retrieved_at`,`rate_status`,`updated_by`,`updated_at`) VALUES ('9','0.2041025641','0.2061538461','0.2051282051','0.2041025641','0.2061538461','0.2051282051','0.2051282051',NULL,NULL,NULL,NULL,NULL,NULL,'0','api','0','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','online','1','2026-08-12 10:12:41');
INSERT INTO `exchange_rates` (`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`previous_buy`,`previous_sell`,`reference_rate`,`previous_reference`,`buy_spread_type`,`buy_spread_value`,`sell_spread_type`,`sell_spread_value`,`buy_override`,`sell_override`,`override_persistent`,`source`,`is_manual`,`provider`,`provider_timestamp`,`retrieved_at`,`rate_status`,`updated_by`,`updated_at`) VALUES ('21','0.0289454545','0.0292363635','0.0290909090','0.0289454545','0.0292363635','0.0290909090','0.0290909090',NULL,NULL,NULL,NULL,NULL,NULL,'0','api','0','frankfurter','2026-08-12 00:00:00','2026-08-12 10:12:41','online','1','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ref_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(30,10) NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `base_amount` decimal(30,10) NOT NULL,
  `rate` decimal(30,10) DEFAULT NULL,
  `account_id` int unsigned NOT NULL,
  `expense_date` date NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `employee_id` int unsigned NOT NULL,
  `reference_no` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gl_account_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_number` (`ref_number`),
  KEY `fk_exp_currency` (`currency_id`),
  KEY `fk_exp_account` (`account_id`),
  KEY `fk_exp_employee` (`employee_id`),
  CONSTRAINT `fk_exp_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_exp_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_exp_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `expenses` (`id`,`ref_number`,`category`,`amount`,`currency_id`,`base_amount`,`rate`,`account_id`,`expense_date`,`description`,`employee_id`,`reference_no`,`attachment_path`,`gl_account_id`,`created_at`) VALUES ('1','EXP-TEST','rent','500.0000000000','5','500.0000000000','1.0000000000','1','2026-08-12','test','1',NULL,NULL,'5','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `gl_accounts`;
CREATE TABLE `gl_accounts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('income','expense','equity','asset','liability') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'income',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('1','REALIZED_PL','Realized Profit/Loss','income','1','1','2026-08-12 10:12:40');
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('2','FEE_INCOME','Exchange Fee Income','income','1','1','2026-08-12 10:12:40');
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('3','DISCOUNT_GIVEN','Discounts Given','expense','1','1','2026-08-12 10:12:40');
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('4','INV_ADJUSTMENT','Inventory Adjustment','expense','1','1','2026-08-12 10:12:40');
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('5','EXP_RENT','Rent','expense','1','1','2026-08-12 10:12:40');
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('6','EXP_SALARY','Salaries','expense','1','1','2026-08-12 10:12:40');
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('7','EXP_UTILITIES','Utilities','expense','1','1','2026-08-12 10:12:40');
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('8','EXP_OTHER','Other Operating Expenses','expense','1','1','2026-08-12 10:12:40');
INSERT INTO `gl_accounts` (`id`,`code`,`name`,`type`,`is_system`,`is_active`,`created_at`) VALUES ('9','TEST_EQUITY','Test Equity','equity','1','1','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `income`;
CREATE TABLE `income` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ref_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(30,10) NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `base_amount` decimal(30,10) NOT NULL,
  `rate` decimal(30,10) DEFAULT NULL,
  `account_id` int unsigned NOT NULL,
  `income_date` date NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `employee_id` int unsigned NOT NULL,
  `gl_account_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_number` (`ref_number`),
  KEY `fk_inc_currency` (`currency_id`),
  KEY `fk_inc_account` (`account_id`),
  KEY `fk_inc_employee` (`employee_id`),
  CONSTRAINT `fk_inc_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_inc_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_inc_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `inventory_costings`;
CREATE TABLE `inventory_costings` (
  `currency_id` int unsigned NOT NULL,
  `qty` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `total_cost` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `avg_cost` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`currency_id`),
  CONSTRAINT `fk_cost_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `inventory_costings` (`currency_id`,`qty`,`total_cost`,`avg_cost`,`updated_at`) VALUES ('1','8647.3800738007','11716.8265400669','1.3549568123','2026-08-12 10:12:41');
INSERT INTO `inventory_costings` (`currency_id`,`qty`,`total_cost`,`avg_cost`,`updated_at`) VALUES ('2','4003.0100334449','5904.4397993314','1.4750000000','2026-08-12 10:12:41');
INSERT INTO `inventory_costings` (`currency_id`,`qty`,`total_cost`,`avg_cost`,`updated_at`) VALUES ('5','101127.0000000000','101127.0000000000','1.0000000000','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `inventory_movements`;
CREATE TABLE `inventory_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `account_id` int unsigned NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `direction` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(30,10) NOT NULL,
  `rate` decimal(30,10) DEFAULT NULL,
  `base_amount` decimal(30,10) NOT NULL,
  `balance_after` decimal(30,10) DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_im_tx` (`transaction_id`),
  KEY `fk_im_currency` (`currency_id`),
  KEY `idx_im_account_cur` (`account_id`,`currency_id`),
  CONSTRAINT `fk_im_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_im_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_im_tx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('1','1','2','1','in','1000.0000000000','1.3600000000','1360.0000000000','11000.0000000000',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('2','2','2','1','out','1000.0000000000','1.3800000000','1355.4545454000','10000.0000000000',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('3','4','2','1','in','1000.0000000000','1.3550000000','1355.0000000000','7950.0000000000',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('4','4','2','2','out','906.3545150501','1.4950000000','1336.8729096988','4093.6454849499',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('5','5','2','1','out','100.0000000000','1.3800000000','135.4904065700','7850.0000000000',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('6','6','2','1','out','200.0000000000','1.3000000000','270.9808131400','7650.0000000000',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('7','7','2','1','in','100.0000000000','1.3600000000','136.0000000000','7750.0000000000',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('8','8','2','1','out','100.0000000000','1.3600000000','135.4956331700','7650.0000000000',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('9','9','2','1','in','107.3800738007','1.3550000000','145.4999999999','7757.3800738007',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('10','9','2','2','out','90.6354515050','1.4950000000','133.6872909698','4003.0100334449',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('11','10','2','1','in','150.0000000000','1.3550000000','203.2500000000','7907.3800738007',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('12','10','2','2','out','135.9531772575','1.4950000000','200.5309364548','3867.0568561874',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('13','12','2','1','out','1000.0000000000','1.3800000000','1354.9568123000','6757.3800738007',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('14','13','2','1','out','100.0000000000','1.4000000000','135.4956812300','6657.3800738007',NULL,'2026-08-12 10:12:41');
INSERT INTO `inventory_movements` (`id`,`transaction_id`,`account_id`,`currency_id`,`direction`,`amount`,`rate`,`base_amount`,`balance_after`,`note`,`created_at`) VALUES ('15','14','2','1','out','10.0000000000','1.4000000000','13.5495681230','6647.3800738007',NULL,'2026-08-12 10:12:41');

DROP TABLE IF EXISTS `journal_entries`;
CREATE TABLE `journal_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entry_no` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `transaction_id` bigint unsigned DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `entry_no` (`entry_no`),
  KEY `fk_je_tx` (`transaction_id`),
  KEY `fk_je_user` (`created_by`),
  CONSTRAINT `fk_je_tx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`),
  CONSTRAINT `fk_je_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('1','JE-00000001',NULL,'OPENING BALANCE (test)','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('2','JE-00000002','1','BUY USD 1000.0000000000','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('3','JE-00000003','2','SELL USD 1000.0000000000','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('4','JE-00000004',NULL,'TRANSFER test','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('5','JE-00000005',NULL,'EXPENSE rent','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('6','JE-00000006','3','REVERSAL of EX-20260812-000001','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('7','JE-00000007',NULL,'INVENTORY ADJUSTMENT #2','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('8','JE-00000008',NULL,'EUR OPENING (test)','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('9','JE-00000009','4','EXCHANGE USD→EUR','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('10','JE-00000010','5','SELL USD 100.0000000000','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('11','JE-00000011','6','SELL USD 200.0000000000','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('12','JE-00000012','7','BUY USD 100.0000000000','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('13','JE-00000013','8','SELL USD 100.0000000000','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('14','JE-00000014','9','EXCHANGE USD→EUR','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('15','JE-00000015','10','EXCHANGE USD→EUR','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('16','JE-00000016','11','REVERSAL of EX-20260812-000010','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('17','JE-00000017','12','SELL USD 1000.0000000000','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('18','JE-00000018','13','SELL USD 100.0000000000','1','2026-08-12 10:12:41');
INSERT INTO `journal_entries` (`id`,`entry_no`,`transaction_id`,`description`,`created_by`,`created_at`) VALUES ('19','JE-00000019','14','SELL USD 10.0000000000','1','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `journal_lines`;
CREATE TABLE `journal_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `entry_id` bigint unsigned NOT NULL,
  `account_id` int unsigned DEFAULT NULL,
  `gl_account_id` int unsigned DEFAULT NULL,
  `currency_id` int unsigned NOT NULL,
  `debit` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `credit` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `base_debit` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `base_credit` decimal(30,10) NOT NULL DEFAULT '0.0000000000',
  `rate` decimal(30,10) DEFAULT NULL,
  `note` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_jl_entry` (`entry_id`),
  KEY `fk_jl_currency` (`currency_id`),
  KEY `idx_jl_account_cur` (`account_id`,`currency_id`),
  KEY `idx_jl_gl` (`gl_account_id`),
  CONSTRAINT `fk_jl_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_jl_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_jl_entry` FOREIGN KEY (`entry_id`) REFERENCES `journal_entries` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jl_gl` FOREIGN KEY (`gl_account_id`) REFERENCES `gl_accounts` (`id`),
  CONSTRAINT `chk_jl_single_side` CHECK ((((`debit` > 0) and (`credit` = 0)) or ((`credit` > 0) and (`debit` = 0))))
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('1','1','1',NULL,'5','100000.0000000000','0.0000000000','100000.0000000000','0.0000000000','1.0000000000','Opening');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('2','1','2',NULL,'1','10000.0000000000','0.0000000000','13550.0000000000','0.0000000000','1.3550000000','Opening');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('3','1',NULL,'9','5','0.0000000000','113550.0000000000','0.0000000000','113550.0000000000','1.0000000000','Opening equity');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('4','2','2',NULL,'1','1000.0000000000','0.0000000000','1360.0000000000','0.0000000000','1.3600000000','BUY USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('5','2','1',NULL,'5','0.0000000000','1360.0000000000','0.0000000000','1360.0000000000','1.0000000000','BUY payout USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('6','3','1',NULL,'5','1380.0000000000','0.0000000000','1380.0000000000','0.0000000000','1.0000000000','SELL USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('7','3','2',NULL,'1','0.0000000000','1000.0000000000','0.0000000000','1355.4545454000','1.3554545454','SELL inventory USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('8','3',NULL,'1','5','0.0000000000','24.5454546000','0.0000000000','24.5454546000','1.0000000000','Realized P/L');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('9','4','1',NULL,'1','2000.0000000000','0.0000000000','2710.0000000000','0.0000000000','1.3550000000','Transfer in');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('10','4','2',NULL,'1','0.0000000000','2000.0000000000','0.0000000000','2710.0000000000','1.3550000000','Transfer out');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('11','5',NULL,'5','5','500.0000000000','0.0000000000','500.0000000000','0.0000000000','1.0000000000','Rent');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('12','5','1',NULL,'5','0.0000000000','500.0000000000','0.0000000000','500.0000000000','1.0000000000','Rent');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('13','6','2',NULL,'1','0.0000000000','1000.0000000000','0.0000000000','1360.0000000000','1.3600000000','BUY USD (reversal)');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('14','6','1',NULL,'5','1360.0000000000','0.0000000000','1360.0000000000','0.0000000000','1.0000000000','BUY payout USD (reversal)');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('15','7','2',NULL,'1','0.0000000000','50.0000000000','0.0000000000','68.2500000000','1.3650000000','Reconciliation approve test');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('16','7',NULL,'4','5','68.2500000000','0.0000000000','68.2500000000','0.0000000000','1.0000000000','Inventory adjustment');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('17','8','2',NULL,'2','5000.0000000000','0.0000000000','7375.0000000000','0.0000000000','1.4750000000','EUR opening');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('18','8',NULL,'9','5','0.0000000000','7375.0000000000','0.0000000000','7375.0000000000','1.0000000000','EUR opening');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('19','9','2',NULL,'1','1000.0000000000','0.0000000000','1355.0000000000','0.0000000000','1.3550000000','EXCHANGE in USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('20','9','2',NULL,'2','0.0000000000','906.3545150501','0.0000000000','1336.8729096988','1.4750000000','EXCHANGE out EUR');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('21','9',NULL,'1','5','0.0000000000','18.1270903012','0.0000000000','18.1270903012','1.0000000000','Exchange margin');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('22','10','1',NULL,'5','128.0000000000','0.0000000000','128.0000000000','0.0000000000','1.0000000000','SELL USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('23','10','2',NULL,'1','0.0000000000','100.0000000000','0.0000000000','135.4904065700','1.3549040657','SELL inventory USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('24','10',NULL,'1','5','0.0000000000','2.5095934300','0.0000000000','2.5095934300','1.0000000000','Realized P/L');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('25','10',NULL,'3','5','10.0000000000','0.0000000000','10.0000000000','0.0000000000','1.0000000000','Discount');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('26','11','1',NULL,'5','260.0000000000','0.0000000000','260.0000000000','0.0000000000','1.0000000000','SELL USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('27','11','2',NULL,'1','0.0000000000','200.0000000000','0.0000000000','270.9808131400','1.3549040657','SELL inventory USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('28','11',NULL,'1','5','10.9808131400','0.0000000000','10.9808131400','0.0000000000','1.0000000000','Realized P/L');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('29','12','2',NULL,'1','100.0000000000','0.0000000000','136.0000000000','0.0000000000','1.3600000000','BUY USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('30','12','1',NULL,'5','0.0000000000','141.0000000000','0.0000000000','141.0000000000','1.0000000000','BUY payout USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('31','12',NULL,'3','5','5.0000000000','0.0000000000','5.0000000000','0.0000000000','1.0000000000','Discount');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('32','13','2',NULL,'1','0.0000000000','100.0000000000','0.0000000000','135.4956331700','1.3549563317','SELL inventory USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('33','13',NULL,'1','5','0.0000000000','0.5043668300','0.0000000000','0.5043668300','1.0000000000','Realized P/L');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('34','13',NULL,'3','5','136.0000000000','0.0000000000','136.0000000000','0.0000000000','1.0000000000','Discount');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('35','14','2',NULL,'1','107.3800738007','0.0000000000','145.4999999999','0.0000000000','1.3550000000','EXCHANGE in USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('36','14','2',NULL,'2','0.0000000000','90.6354515050','0.0000000000','133.6872909698','1.4750000000','EXCHANGE out EUR');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('37','14',NULL,'1','5','0.0000000000','1.8127090301','0.0000000000','1.8127090301','1.0000000000','Exchange margin');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('38','14',NULL,'2','5','0.0000000000','10.0000000000','0.0000000000','10.0000000000','1.0000000000','Fee');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('39','15','2',NULL,'1','150.0000000000','0.0000000000','203.2500000000','0.0000000000','1.3550000000','EXCHANGE in USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('40','15','2',NULL,'2','0.0000000000','135.9531772575','0.0000000000','200.5309364548','1.4750000000','EXCHANGE out EUR');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('41','15',NULL,'1','5','0.0000000000','2.7190635452','0.0000000000','2.7190635452','1.0000000000','Exchange margin');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('42','16','2',NULL,'1','0.0000000000','150.0000000000','0.0000000000','203.2500000000','1.3550000000','EXCHANGE in USD (reversal)');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('43','16','2',NULL,'2','135.9531772575','0.0000000000','200.5309364548','0.0000000000','1.4750000000','EXCHANGE out EUR (reversal)');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('44','16',NULL,'1','5','2.7190635452','0.0000000000','2.7190635452','0.0000000000','1.0000000000','Exchange margin (reversal)');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('45','17','1',NULL,'5','1386.9000000000','0.0000000000','1386.9000000000','0.0000000000','1.0000000000','SELL USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('46','17','2',NULL,'1','0.0000000000','1000.0000000000','0.0000000000','1354.9568123000','1.3549568123','SELL inventory USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('47','17',NULL,'1','5','0.0000000000','25.0431877000','0.0000000000','25.0431877000','1.0000000000','Realized P/L');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('48','17',NULL,'2','5','0.0000000000','13.8000000000','0.0000000000','13.8000000000','1.0000000000','Fee');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('49','17',NULL,'3','5','6.9000000000','0.0000000000','6.9000000000','0.0000000000','1.0000000000','Discount');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('50','18','1',NULL,'5','140.0000000000','0.0000000000','140.0000000000','0.0000000000','1.0000000000','SELL USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('51','18','2',NULL,'1','0.0000000000','100.0000000000','0.0000000000','135.4956812300','1.3549568123','SELL inventory USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('52','18',NULL,'1','5','0.0000000000','4.5043187700','0.0000000000','4.5043187700','1.0000000000','Realized P/L');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('53','19','1',NULL,'5','14.0000000000','0.0000000000','14.0000000000','0.0000000000','1.0000000000','SELL USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('54','19','2',NULL,'1','0.0000000000','10.0000000000','0.0000000000','13.5495681230','1.3549568123','SELL inventory USD');
INSERT INTO `journal_lines` (`id`,`entry_id`,`account_id`,`gl_account_id`,`currency_id`,`debit`,`credit`,`base_debit`,`base_credit`,`rate`,`note`) VALUES ('55','19',NULL,'1','5','0.0000000000','0.4504318770','0.0000000000','0.4504318770','1.0000000000','Realized P/L');

DROP TABLE IF EXISTS `login_history`;
CREATE TABLE `login_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`user_id`),
  KEY `idx_login_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user_read` (`user_id`,`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('1','view_dashboard','View Dashboard');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('2','view_transactions','View Transactions');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('3','create_transaction','Create Transaction');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('4','edit_transaction','Edit Transaction');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('5','cancel_transaction','Cancel Transaction');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('6','view_customers','View Customers');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('7','manage_customers','Manage Customers');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('8','view_balances','View Balances');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('9','adjust_balance','Adjust Balance');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('10','view_reports','View Reports');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('11','manage_rates','Manage Rates');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('12','manage_currencies','Manage Currencies');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('13','manage_accounts','Manage Accounts');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('14','manage_expenses','Manage Expenses');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('15','view_ledger','View Ledger');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('16','view_inventory','View Inventory');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('17','manage_users','Manage Users');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('18','manage_settings','Manage Settings');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('19','view_audit_log','View Audit Log');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('20','perform_reconciliation','Perform Reconciliation');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('21','export_data','Export Data');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('22','view_analytics','View profit analytics');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('23','view_price_board','View the price board');
INSERT INTO `permissions` (`id`,`code`,`description`) VALUES ('24','closing_approve','Approve / reopen daily closing');

DROP TABLE IF EXISTS `rate_history`;
CREATE TABLE `rate_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `currency_id` int unsigned NOT NULL,
  `buy_rate` decimal(30,10) NOT NULL,
  `sell_rate` decimal(30,10) NOT NULL,
  `mid_rate` decimal(30,10) NOT NULL,
  `source` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_manual` tinyint(1) NOT NULL DEFAULT '1',
  `changed_by` int unsigned DEFAULT NULL,
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ratehist_cur_time` (`currency_id`,`changed_at`),
  CONSTRAINT `fk_ratehist_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `rate_history` (`id`,`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`source`,`is_manual`,`changed_by`,`changed_at`) VALUES ('1','1','1.3550000000','1.3750000000','1.3650000000','manual','1','1','2026-08-12 10:12:41');
INSERT INTO `rate_history` (`id`,`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`source`,`is_manual`,`changed_by`,`changed_at`) VALUES ('2','2','1.4750000000','1.4950000000','1.4850000000','manual','1','1','2026-08-12 10:12:41');
INSERT INTO `rate_history` (`id`,`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`source`,`is_manual`,`changed_by`,`changed_at`) VALUES ('3','2','1.5920000000','1.6080000000','1.6000000000','api','0',NULL,'2026-08-12 10:12:41');
INSERT INTO `rate_history` (`id`,`currency_id`,`buy_rate`,`sell_rate`,`mid_rate`,`source`,`is_manual`,`changed_by`,`changed_at`) VALUES ('4','1','1.3266666667','1.3399999999','1.3333333333','api','0',NULL,'2026-08-12 10:12:41');

DROP TABLE IF EXISTS `rate_sync_logs`;
CREATE TABLE `rate_sync_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `provider` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('success','failed','partial','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'success',
  `triggered_by` enum('login','manual','cron') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `currencies_updated` int unsigned NOT NULL DEFAULT '0',
  `currencies_skipped` int unsigned NOT NULL DEFAULT '0',
  `currencies_failed` int unsigned NOT NULL DEFAULT '0',
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rsl_time` (`started_at`),
  KEY `idx_rsl_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `rate_sync_logs` (`id`,`provider`,`status`,`triggered_by`,`started_at`,`completed_at`,`currencies_updated`,`currencies_skipped`,`currencies_failed`,`error_message`,`created_at`) VALUES ('1','frankfurter','success','manual','2026-08-12 10:12:41','2026-08-12 10:12:41','5','36','0',NULL,'2026-08-12 10:12:41');
INSERT INTO `rate_sync_logs` (`id`,`provider`,`status`,`triggered_by`,`started_at`,`completed_at`,`currencies_updated`,`currencies_skipped`,`currencies_failed`,`error_message`,`created_at`) VALUES ('2','frankfurter','success','manual','2026-08-12 10:12:41','2026-08-12 10:12:41','5','36','0',NULL,'2026-08-12 10:12:41');
INSERT INTO `rate_sync_logs` (`id`,`provider`,`status`,`triggered_by`,`started_at`,`completed_at`,`currencies_updated`,`currencies_skipped`,`currencies_failed`,`error_message`,`created_at`) VALUES ('3','frankfurter','failed','manual','2026-08-12 10:12:41','2026-08-12 10:12:41','0','0','0','provider down (fake)','2026-08-12 10:12:41');
INSERT INTO `rate_sync_logs` (`id`,`provider`,`status`,`triggered_by`,`started_at`,`completed_at`,`currencies_updated`,`currencies_skipped`,`currencies_failed`,`error_message`,`created_at`) VALUES ('4','frankfurter','failed','manual','2026-08-12 10:12:41','2026-08-12 10:12:41','0','0','0','Provider returned an invalid (zero/negative/non-numeric) rate for USD.','2026-08-12 10:12:41');
INSERT INTO `rate_sync_logs` (`id`,`provider`,`status`,`triggered_by`,`started_at`,`completed_at`,`currencies_updated`,`currencies_skipped`,`currencies_failed`,`error_message`,`created_at`) VALUES ('5','frankfurter','partial','manual','2026-08-12 10:12:41','2026-08-12 10:12:41','4','36','1','USD: change +1,333,233.3% exceeds the allowed 10.0%','2026-08-12 10:12:41');
INSERT INTO `rate_sync_logs` (`id`,`provider`,`status`,`triggered_by`,`started_at`,`completed_at`,`currencies_updated`,`currencies_skipped`,`currencies_failed`,`error_message`,`created_at`) VALUES ('6','frankfurter','success','manual','2026-08-12 10:12:41','2026-08-12 10:12:41','4','37','0',NULL,'2026-08-12 10:12:41');
INSERT INTO `rate_sync_logs` (`id`,`provider`,`status`,`triggered_by`,`started_at`,`completed_at`,`currencies_updated`,`currencies_skipped`,`currencies_failed`,`error_message`,`created_at`) VALUES ('7','frankfurter','success','manual','2026-08-12 10:12:41','2026-08-12 10:12:41','5','36','0',NULL,'2026-08-12 10:12:41');

DROP TABLE IF EXISTS `reconciliations`;
CREATE TABLE `reconciliations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rec_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` int unsigned NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `system_balance` decimal(30,10) NOT NULL,
  `physical_balance` decimal(30,10) NOT NULL,
  `difference` decimal(30,10) NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci,
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_by` int unsigned NOT NULL,
  `approved_by` int unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rec_number` (`rec_number`),
  KEY `fk_rec_account` (`account_id`),
  KEY `fk_rec_currency` (`currency_id`),
  KEY `fk_rec_created` (`created_by`),
  KEY `fk_rec_approved` (`approved_by`),
  CONSTRAINT `fk_rec_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_rec_approved` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_rec_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_rec_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `reconciliations` (`id`,`rec_number`,`account_id`,`currency_id`,`system_balance`,`physical_balance`,`difference`,`reason`,`status`,`created_by`,`approved_by`,`approved_at`,`created_at`) VALUES ('1','RC-TEST','2','1','7000.0000000000','6500.0000000000','-500.0000000000','test count','pending','1',NULL,NULL,'2026-08-12 10:12:41');
INSERT INTO `reconciliations` (`id`,`rec_number`,`account_id`,`currency_id`,`system_balance`,`physical_balance`,`difference`,`reason`,`status`,`created_by`,`approved_by`,`approved_at`,`created_at`) VALUES ('2','RC-APPROVE','2','1','7000.0000000000','6950.0000000000','-50.0000000000','approve test','approved','1','1','2026-08-12 10:12:41','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` int unsigned NOT NULL,
  `permission_id` int unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_perm` (`permission_id`),
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','1');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','1');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','1');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','1');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','1');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','2');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','2');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','2');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','2');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','2');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','3');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','3');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','3');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','4');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','4');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','4');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','5');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','5');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','6');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','6');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','6');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','6');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','6');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','7');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','7');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','8');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','8');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','8');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','8');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','8');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','9');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','9');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','9');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','10');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','10');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','10');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','10');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','10');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','11');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','11');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','12');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','12');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','13');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','13');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','14');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','14');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','14');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','15');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','15');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','15');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','15');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','16');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','16');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','16');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','16');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','16');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','17');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','18');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','18');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','19');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','19');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','20');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','20');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','20');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','21');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','21');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','21');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','21');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','22');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','22');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','22');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','22');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','23');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','23');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('3','23');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','23');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('5','23');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('1','24');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('2','24');
INSERT INTO `role_permissions` (`role_id`,`permission_id`) VALUES ('4','24');

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `roles` (`id`,`name`,`description`,`is_system`,`created_at`) VALUES ('1','owner','Full access','1','2026-08-12 10:12:39');
INSERT INTO `roles` (`id`,`name`,`description`,`is_system`,`created_at`) VALUES ('2','manager','Almost full access','1','2026-08-12 10:12:39');
INSERT INTO `roles` (`id`,`name`,`description`,`is_system`,`created_at`) VALUES ('3','cashier','Create transactions, manage assigned desk','1','2026-08-12 10:12:39');
INSERT INTO `roles` (`id`,`name`,`description`,`is_system`,`created_at`) VALUES ('4','accountant','Financial reports, ledgers, expenses, reconciliation','1','2026-08-12 10:12:39');
INSERT INTO `roles` (`id`,`name`,`description`,`is_system`,`created_at`) VALUES ('5','viewer','Read-only access','1','2026-08-12 10:12:39');

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('1','business_name','ExchangePro','2026-08-12 10:12:40');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('2','base_currency','CAD','2026-08-12 10:12:40');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('3','timezone','America/Toronto','2026-08-12 10:12:40');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('4','language','en','2026-08-12 10:12:40');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('5','tx_prefix','EX','2026-08-12 10:12:40');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('6','large_tx_threshold','25000','2026-08-12 10:12:40');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('7','profit_method','weighted_average','2026-08-12 10:12:40');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('8','receipt_footer','Thank you for your business. Amounts subject to confirmation.','2026-08-12 10:12:40');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('9','price_board_refresh','30','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('10','backup_enabled','0','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('11','backup_time','02:00','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('12','backup_retention_daily','30','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('13','backup_retention_weekly','12','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('14','backup_retention_monthly','12','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('15','inventory_min_default','0','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('16','inventory_target_default','0','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('17','inventory_max_default','0','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('18','seq_EX_20260812','14','2026-08-12 10:12:41');
INSERT INTO `settings` (`id`,`setting_key`,`setting_value`,`updated_at`) VALUES ('19','rate_sync_retry_attempts','1','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `transaction_fees`;
CREATE TABLE `transaction_fees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint unsigned NOT NULL,
  `type` enum('fixed','percent','currency','customer','transaction') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(30,10) NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `base_amount` decimal(30,10) NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tf_tx` (`transaction_id`),
  KEY `fk_tf_currency` (`currency_id`),
  CONSTRAINT `fk_tf_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_tf_tx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `transaction_items`;
CREATE TABLE `transaction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transaction_id` bigint unsigned NOT NULL,
  `line_no` tinyint NOT NULL DEFAULT '1',
  `source_currency_id` int unsigned NOT NULL,
  `target_currency_id` int unsigned NOT NULL,
  `source_amount` decimal(30,10) NOT NULL,
  `target_amount` decimal(30,10) NOT NULL,
  `rate` decimal(30,10) NOT NULL,
  `base_amount` decimal(30,10) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ti_tx` (`transaction_id`),
  KEY `fk_ti_src` (`source_currency_id`),
  KEY `fk_ti_tgt` (`target_currency_id`),
  CONSTRAINT `fk_ti_src` FOREIGN KEY (`source_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_ti_tgt` FOREIGN KEY (`target_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_ti_tx` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `transaction_items` (`id`,`transaction_id`,`line_no`,`source_currency_id`,`target_currency_id`,`source_amount`,`target_amount`,`rate`,`base_amount`) VALUES ('1','4','1','1','2','1000.0000000000','906.3545150501','0.9063545150','1355.0000000000');
INSERT INTO `transaction_items` (`id`,`transaction_id`,`line_no`,`source_currency_id`,`target_currency_id`,`source_amount`,`target_amount`,`rate`,`base_amount`) VALUES ('2','9','1','1','2','100.0000000000','90.6354515050','0.9063545150','135.5000000000');
INSERT INTO `transaction_items` (`id`,`transaction_id`,`line_no`,`source_currency_id`,`target_currency_id`,`source_amount`,`target_amount`,`rate`,`base_amount`) VALUES ('3','10','1','1','2','150.0000000000','135.9531772575','0.9063545150','203.2500000000');

DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tx_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('buy','sell','exchange','reversal','adjustment','deposit','withdrawal','expense','income','transfer') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','pending','completed','cancelled','reversed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `customer_id` int unsigned DEFAULT NULL,
  `employee_id` int unsigned NOT NULL,
  `currency_id` int unsigned DEFAULT NULL,
  `rate` decimal(30,10) DEFAULT NULL,
  `foreign_amount` decimal(30,10) DEFAULT NULL,
  `base_amount` decimal(30,10) DEFAULT NULL,
  `fee_amount` decimal(30,10) DEFAULT NULL,
  `fee_currency_id` int unsigned DEFAULT NULL,
  `discount_amount` decimal(30,10) DEFAULT NULL,
  `total_amount` decimal(30,10) DEFAULT NULL,
  `realized_pl` decimal(30,10) DEFAULT NULL,
  `pl_currency_id` int unsigned DEFAULT NULL,
  `payment_method` enum('cash','bank_transfer','card','internal_balance','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_account_id` int unsigned DEFAULT NULL,
  `destination_account_id` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `original_transaction_id` bigint unsigned DEFAULT NULL,
  `reversal_transaction_id` bigint unsigned DEFAULT NULL,
  `compliance_status` enum('normal','requires_review','reviewed','escalated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `is_large` tinyint(1) NOT NULL DEFAULT '0',
  `tx_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tx_number` (`tx_number`),
  KEY `fk_tx_employee` (`employee_id`),
  KEY `fk_tx_currency` (`currency_id`),
  KEY `fk_tx_orig` (`original_transaction_id`),
  KEY `fk_tx_rev` (`reversal_transaction_id`),
  KEY `idx_tx_type_status` (`type`,`status`),
  KEY `idx_tx_date` (`tx_date`),
  KEY `idx_tx_customer` (`customer_id`),
  CONSTRAINT `fk_tx_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_tx_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_tx_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_tx_orig` FOREIGN KEY (`original_transaction_id`) REFERENCES `transactions` (`id`),
  CONSTRAINT `fk_tx_rev` FOREIGN KEY (`reversal_transaction_id`) REFERENCES `transactions` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('1','EX-20260812-000001','buy','reversed',NULL,'1','1','1.3600000000','1000.0000000000','1360.0000000000','0.0000000000','5','0.0000000000','1360.0000000000',NULL,NULL,'cash','1','2',NULL,NULL,'3','normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('2','EX-20260812-000002','sell','completed',NULL,'1','1','1.3800000000','1000.0000000000','1380.0000000000','0.0000000000','5','0.0000000000','1380.0000000000','24.5454546000','1','cash','2','1',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('3','EX-20260812-000003','reversal','completed',NULL,'1','1','1.3600000000','1000.0000000000','1360.0000000000','0.0000000000','5','0.0000000000','1360.0000000000',NULL,NULL,'cash','1','2','[REVERSAL] test reversal','1',NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('4','EX-20260812-000004','exchange','completed',NULL,'1','2','0.9063545150','906.3545150501','1355.0000000000','0.0000000000','5','0.0000000000','906.3545150501','18.1270903012','2','cash','2','2',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('5','EX-20260812-000005','sell','completed',NULL,'1','1','1.3800000000','100.0000000000','138.0000000000','0.0000000000','5','10.0000000000','128.0000000000','2.5095934300','1','cash','2','1',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('6','EX-20260812-000006','sell','completed',NULL,'1','1','1.3000000000','200.0000000000','260.0000000000','0.0000000000','5','0.0000000000','260.0000000000','-10.9808131400','1','cash','2','1',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('7','EX-20260812-000007','buy','completed',NULL,'1','1','1.3600000000','100.0000000000','136.0000000000','0.0000000000','5','5.0000000000','141.0000000000',NULL,NULL,'cash','1','2',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('8','EX-20260812-000008','sell','completed',NULL,'1','1','1.3600000000','100.0000000000','136.0000000000','0.0000000000','5','136.0000000000','0.0000000000','0.5043668300','1','cash','2','1',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('9','EX-20260812-000009','exchange','completed',NULL,'1','2','0.9063545150','90.6354515050','135.5000000000','10.0000000000','5','0.0000000000','90.6354515050','1.8127090301','2','cash','2','2',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('10','EX-20260812-000010','exchange','reversed',NULL,'1','2','0.9063545150','135.9531772575','203.2500000000','0.0000000000','5','0.0000000000','135.9531772575','2.7190635452','2','cash','2','2',NULL,NULL,'11','normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('11','EX-20260812-000011','reversal','completed',NULL,'1','2','0.9063545150','135.9531772575','203.2500000000','0.0000000000','5','0.0000000000','135.9531772575',NULL,NULL,'cash','2','2','[REVERSAL] test exchange reversal','10',NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('12','EX-20260812-000012','sell','completed',NULL,'1','1','1.3800000000','1000.0000000000','1380.0000000000','13.8000000000','5','6.9000000000','1386.9000000000','25.0431877000','1','cash','2','1',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('13','EX-20260812-000013','sell','completed',NULL,'1','1','1.4000000000','100.0000000000','140.0000000000','0.0000000000','5','0.0000000000','140.0000000000','4.5043187700','1','cash','2','1',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `transactions` (`id`,`tx_number`,`type`,`status`,`customer_id`,`employee_id`,`currency_id`,`rate`,`foreign_amount`,`base_amount`,`fee_amount`,`fee_currency_id`,`discount_amount`,`total_amount`,`realized_pl`,`pl_currency_id`,`payment_method`,`source_account_id`,`destination_account_id`,`notes`,`original_transaction_id`,`reversal_transaction_id`,`compliance_status`,`is_large`,`tx_date`,`created_at`,`completed_at`,`updated_at`) VALUES ('14','EX-20260812-000014','sell','completed',NULL,'1','1','1.4000000000','10.0000000000','14.0000000000','0.0000000000','5','0.0000000000','14.0000000000','0.4504318770','1','cash','2','1',NULL,NULL,NULL,'normal','0','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41','2026-08-12 10:12:41');

DROP TABLE IF EXISTS `transfers`;
CREATE TABLE `transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ref_number` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_account_id` int unsigned NOT NULL,
  `destination_account_id` int unsigned NOT NULL,
  `currency_id` int unsigned NOT NULL,
  `amount` decimal(30,10) NOT NULL,
  `base_amount` decimal(30,10) NOT NULL,
  `rate` decimal(30,10) DEFAULT NULL,
  `transfer_date` date NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `employee_id` int unsigned NOT NULL,
  `status` enum('completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ref_number` (`ref_number`),
  KEY `fk_tr_src` (`source_account_id`),
  KEY `fk_tr_dst` (`destination_account_id`),
  KEY `fk_tr_currency` (`currency_id`),
  KEY `fk_tr_employee` (`employee_id`),
  CONSTRAINT `fk_tr_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `fk_tr_dst` FOREIGN KEY (`destination_account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `fk_tr_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_tr_src` FOREIGN KEY (`source_account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` int unsigned NOT NULL,
  `status` enum('active','inactive','locked') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `totp_secret` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `must_change_pwd` tinyint(1) NOT NULL DEFAULT '0',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_role` (`role_id`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `users` (`id`,`username`,`email`,`password_hash`,`full_name`,`role_id`,`status`,`totp_secret`,`totp_enabled`,`must_change_pwd`,`last_login_at`,`created_at`,`updated_at`) VALUES ('1','testadmin','test@example.com','$2y$10$rZbDK/s2KMDMXMnyJlADSe3tPj8NAKs.nouXTqS1K1pFCcaUWwA/2','Test Admin','1','active',NULL,'0','0',NULL,'2026-08-12 10:12:41','2026-08-12 10:12:41');
INSERT INTO `users` (`id`,`username`,`email`,`password_hash`,`full_name`,`role_id`,`status`,`totp_secret`,`totp_enabled`,`must_change_pwd`,`last_login_at`,`created_at`,`updated_at`) VALUES ('2','testcashier','cashier@example.com','$2y$10$A936w4awJ7HqZhBNB9fNJecEJ5SKnVQJ2djmsV8axeHKiKv0LMiEG','Test Cashier','3','active',NULL,'0','0',NULL,'2026-08-12 10:12:41','2026-08-12 10:12:41');

SET FOREIGN_KEY_CHECKS = 1;
