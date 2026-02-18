/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `admin_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `admin_audit_logs_user_id_foreign` (`user_id`),
  KEY `admin_audit_logs_action_index` (`action`),
  KEY `admin_audit_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  CONSTRAINT `admin_audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `checkout_intents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `checkout_intents` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'order',
  `invoice_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Linked invoice for invoice payment intents',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temp_disk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temp_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_count` int DEFAULT NULL,
  `amount_cents` int unsigned NOT NULL,
  `credit_applied` int NOT NULL DEFAULT '0',
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_plan_id` bigint unsigned DEFAULT NULL,
  `stripe_session_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_payment_intent_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `checkout_intents_pricing_plan_id_foreign` (`pricing_plan_id`),
  KEY `checkout_intents_user_id_index` (`user_id`),
  KEY `checkout_intents_status_index` (`status`),
  KEY `checkout_intents_created_at_index` (`created_at`),
  KEY `checkout_intents_stripe_session_id_index` (`stripe_session_id`),
  KEY `checkout_intents_stripe_payment_intent_id_index` (`stripe_payment_intent_id`),
  CONSTRAINT `checkout_intents_pricing_plan_id_foreign` FOREIGN KEY (`pricing_plan_id`) REFERENCES `pricing_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `checkout_intents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `credits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `amount` bigint NOT NULL DEFAULT '0',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `credits_user_id_foreign` (`user_id`),
  KEY `credits_invoice_id_foreign` (`invoice_id`),
  CONSTRAINT `credits_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `credits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_verification_outcome_imports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_outcome_imports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `file_disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin_import',
  `imported_count` int unsigned NOT NULL DEFAULT '0',
  `skipped_count` int unsigned NOT NULL DEFAULT '0',
  `error_sample` json DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_verification_outcome_imports_user_id_foreign` (`user_id`),
  KEY `email_verification_outcome_imports_status_index` (`status`),
  CONSTRAINT `email_verification_outcome_imports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_verification_outcome_ingestions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_outcome_ingestions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_count` int unsigned NOT NULL DEFAULT '0',
  `imported_count` int unsigned NOT NULL DEFAULT '0',
  `skipped_count` int unsigned NOT NULL DEFAULT '0',
  `error_count` int unsigned NOT NULL DEFAULT '0',
  `user_id` bigint unsigned DEFAULT NULL,
  `token_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `import_id` bigint unsigned DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `email_verification_outcome_ingestions_user_id_foreign` (`user_id`),
  KEY `email_verification_outcome_ingestions_import_id_foreign` (`import_id`),
  KEY `email_verification_outcome_ingestions_created_at_index` (`created_at`),
  CONSTRAINT `email_verification_outcome_ingestions_import_id_foreign` FOREIGN KEY (`import_id`) REFERENCES `email_verification_outcome_imports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `email_verification_outcome_ingestions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `email_verification_outcomes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_verification_outcomes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_normalized` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` json DEFAULT NULL,
  `observed_at` timestamp NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_outcome_observed_unique` (`email_hash`,`outcome`,`observed_at`),
  KEY `email_verification_outcomes_user_id_foreign` (`user_id`),
  KEY `email_verification_outcomes_email_hash_index` (`email_hash`),
  KEY `email_verification_outcomes_observed_at_index` (`observed_at`),
  CONSTRAINT `email_verification_outcomes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engine_server_blacklist_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `engine_server_blacklist_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `engine_server_id` bigint unsigned NOT NULL,
  `rbl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `severity` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `first_seen` timestamp NOT NULL,
  `last_seen` timestamp NOT NULL,
  `last_response` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `listed_count` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `esbe_server_rbl_unique` (`engine_server_id`,`rbl`),
  KEY `esbe_server_status` (`engine_server_id`,`status`),
  CONSTRAINT `engine_server_blacklist_events_engine_server_id_foreign` FOREIGN KEY (`engine_server_id`) REFERENCES `engine_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engine_server_delist_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `engine_server_delist_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `engine_server_id` bigint unsigned NOT NULL,
  `rbl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `requested_by` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `engine_server_delist_requests_requested_by_foreign` (`requested_by`),
  KEY `esdr_server_status` (`engine_server_id`,`status`),
  CONSTRAINT `engine_server_delist_requests_engine_server_id_foreign` FOREIGN KEY (`engine_server_id`) REFERENCES `engine_servers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `engine_server_delist_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engine_server_provisioning_bundles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `engine_server_provisioning_bundles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `engine_server_id` bigint unsigned NOT NULL,
  `bundle_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `env_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `script_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `engine_server_provisioning_bundles_bundle_uuid_unique` (`bundle_uuid`),
  KEY `engine_server_provisioning_bundles_engine_server_id_foreign` (`engine_server_id`),
  KEY `engine_server_provisioning_bundles_created_by_foreign` (`created_by`),
  KEY `engine_server_provisioning_bundles_token_id_index` (`token_id`),
  KEY `engine_server_provisioning_bundles_expires_at_index` (`expires_at`),
  CONSTRAINT `engine_server_provisioning_bundles_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `engine_server_provisioning_bundles_engine_server_id_foreign` FOREIGN KEY (`engine_server_id`) REFERENCES `engine_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engine_server_reputation_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `engine_server_reputation_checks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `engine_server_id` bigint unsigned NOT NULL,
  `ip_address` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rbl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `response` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checked_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `esrc_server_checked_at` (`engine_server_id`,`checked_at`),
  KEY `esrc_server_rbl` (`engine_server_id`,`rbl`),
  KEY `engine_server_reputation_checks_status_index` (`status`),
  CONSTRAINT `engine_server_reputation_checks_engine_server_id_foreign` FOREIGN KEY (`engine_server_id`) REFERENCES `engine_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engine_server_reputation_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `engine_server_reputation_samples` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `engine_server_id` bigint unsigned NOT NULL,
  `verification_job_chunk_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_count` int unsigned NOT NULL DEFAULT '0',
  `tempfail_count` int unsigned NOT NULL DEFAULT '0',
  `recorded_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ess_chunk_id_unique` (`verification_job_chunk_id`),
  KEY `ess_engine_server_recorded_at` (`engine_server_id`,`recorded_at`),
  CONSTRAINT `engine_server_reputation_samples_engine_server_id_foreign` FOREIGN KEY (`engine_server_id`) REFERENCES `engine_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engine_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `engine_servers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `helo_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mail_from_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `environment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `region` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_heartbeat_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `drain_mode` tinyint(1) NOT NULL DEFAULT '0',
  `max_concurrency` int unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `verifier_domain_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `engine_servers_ip_address_index` (`ip_address`),
  KEY `engine_servers_last_heartbeat_at_index` (`last_heartbeat_at`),
  KEY `engine_servers_verifier_domain_id_foreign` (`verifier_domain_id`),
  CONSTRAINT `engine_servers_verifier_domain_id_foreign` FOREIGN KEY (`verifier_domain_id`) REFERENCES `verifier_domains` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engine_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `engine_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `engine_paused` tinyint(1) NOT NULL DEFAULT '0',
  `enhanced_mode_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `role_accounts_behavior` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'risky',
  `role_accounts_list` text COLLATE utf8mb4_unicode_ci,
  `catch_all_policy` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'risky_only',
  `catch_all_promote_threshold` smallint unsigned DEFAULT NULL,
  `cache_only_mode_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `cache_only_miss_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'risky',
  `cache_capacity_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'on_demand',
  `cache_batch_size` int unsigned NOT NULL DEFAULT '100',
  `cache_consistent_read` tinyint(1) NOT NULL DEFAULT '0',
  `cache_ondemand_max_batches_per_second` int unsigned DEFAULT NULL,
  `cache_ondemand_sleep_ms_between_batches` int unsigned NOT NULL DEFAULT '0',
  `cache_provisioned_max_batches_per_second` int unsigned NOT NULL DEFAULT '5',
  `cache_provisioned_sleep_ms_between_batches` int unsigned NOT NULL DEFAULT '100',
  `cache_provisioned_max_retries` int unsigned NOT NULL DEFAULT '5',
  `cache_provisioned_backoff_base_ms` int unsigned NOT NULL DEFAULT '200',
  `cache_provisioned_backoff_max_ms` int unsigned NOT NULL DEFAULT '2000',
  `cache_provisioned_jitter_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `cache_failure_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fail_job',
  `cache_writeback_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `cache_writeback_statuses` json DEFAULT NULL,
  `cache_writeback_batch_size` int unsigned NOT NULL DEFAULT '25',
  `cache_writeback_max_writes_per_second` int unsigned DEFAULT NULL,
  `cache_writeback_retry_attempts` int unsigned NOT NULL DEFAULT '5',
  `cache_writeback_backoff_base_ms` int unsigned NOT NULL DEFAULT '200',
  `cache_writeback_backoff_max_ms` int unsigned NOT NULL DEFAULT '2000',
  `cache_writeback_failure_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fail_job',
  `cache_writeback_test_mode_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `cache_writeback_test_table` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_policies` json DEFAULT NULL,
  `tempfail_retry_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `tempfail_retry_max_attempts` int unsigned NOT NULL DEFAULT '2',
  `tempfail_retry_backoff_minutes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tempfail_retry_reasons` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reputation_window_hours` int unsigned NOT NULL DEFAULT '24',
  `reputation_min_samples` int unsigned NOT NULL DEFAULT '100',
  `reputation_tempfail_warn_rate` decimal(5,2) NOT NULL DEFAULT '0.20',
  `reputation_tempfail_critical_rate` decimal(5,2) NOT NULL DEFAULT '0.40',
  `show_single_checks_in_admin` tinyint(1) NOT NULL DEFAULT '0',
  `monitor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `monitor_interval_minutes` int unsigned NOT NULL DEFAULT '60',
  `monitor_rbl_list` text COLLATE utf8mb4_unicode_ci,
  `monitor_dns_mode` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `monitor_dns_server_ip` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monitor_dns_server_port` smallint unsigned NOT NULL DEFAULT '53',
  `metrics_source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'container',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `engine_verification_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `engine_verification_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `dns_timeout_ms` int unsigned NOT NULL,
  `smtp_connect_timeout_ms` int unsigned NOT NULL,
  `smtp_read_timeout_ms` int unsigned NOT NULL,
  `max_mx_attempts` int unsigned NOT NULL,
  `max_concurrency_default` int unsigned NOT NULL,
  `per_domain_concurrency` int unsigned NOT NULL,
  `global_connects_per_minute` int unsigned DEFAULT NULL,
  `tempfail_backoff_seconds` int unsigned DEFAULT NULL,
  `circuit_breaker_tempfail_rate` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `catch_all_detection_enabled` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `engine_verification_policies_mode_unique` (`mode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` bigint NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Order',
  `rel_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rel_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `invoice_items_invoice_id_foreign` (`invoice_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unpaid',
  `is_published` tinyint(1) NOT NULL DEFAULT '0',
  `date` datetime NOT NULL,
  `due_date` datetime NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `subtotal` bigint NOT NULL DEFAULT '0',
  `tax` bigint NOT NULL DEFAULT '0',
  `discount` bigint NOT NULL DEFAULT '0',
  `total` bigint NOT NULL DEFAULT '0',
  `credit_applied` bigint NOT NULL DEFAULT '0',
  `balance_due` bigint NOT NULL DEFAULT '0',
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  KEY `invoices_user_id_foreign` (`user_id`),
  CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `portal_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `portal_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `portal_settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pricing_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pricing_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_price_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_interval` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_per_email` decimal(10,4) DEFAULT NULL,
  `price_per_1000` decimal(10,2) DEFAULT NULL,
  `min_emails` int unsigned DEFAULT NULL,
  `max_emails` int unsigned DEFAULT NULL,
  `credits_per_month` int unsigned DEFAULT NULL,
  `max_file_size_mb` int unsigned DEFAULT NULL,
  `concurrency_limit` int unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pricing_plans_slug_unique` (`slug`),
  KEY `pricing_plans_is_active_index` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `queue_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `driver` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `depth` int unsigned NOT NULL DEFAULT '0',
  `failed_count` int unsigned NOT NULL DEFAULT '0',
  `oldest_age_seconds` int unsigned DEFAULT NULL,
  `throughput_per_min` int unsigned DEFAULT NULL,
  `captured_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `queue_metrics_driver_queue_captured_at_index` (`driver`,`queue`,`captured_at`),
  KEY `queue_metrics_captured_at_index` (`captured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `retention_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `retention_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `retention_days` int unsigned NOT NULL DEFAULT '30',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `subscription_id` bigint unsigned NOT NULL,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_product` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_price` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `meter_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `meter_event_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_items_stripe_id_unique` (`stripe_id`),
  KEY `subscription_items_subscription_id_stripe_price_index` (`subscription_id`,`stripe_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscriptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_price` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscriptions_stripe_id_unique` (`stripe_id`),
  KEY `subscriptions_user_id_stripe_status_index` (`user_id`,`stripe_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `support_ticket_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `support_messages_support_ticket_id_foreign` (`support_ticket_id`),
  KEY `support_messages_user_id_foreign` (`user_id`),
  CONSTRAINT `support_messages_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `support_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `support_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `support_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `verification_order_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ticket_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'General',
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Normal',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `support_tickets_ticket_number_unique` (`ticket_number`),
  KEY `support_tickets_user_id_foreign` (`user_id`),
  KEY `support_tickets_verification_order_id_index` (`verification_order_id`),
  CONSTRAINT `support_tickets_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_metrics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `captured_at` timestamp NOT NULL,
  `cpu_percent` decimal(5,2) DEFAULT NULL,
  `cpu_total_ticks` bigint unsigned DEFAULT NULL,
  `cpu_idle_ticks` bigint unsigned DEFAULT NULL,
  `mem_total_mb` bigint unsigned DEFAULT NULL,
  `mem_used_mb` bigint unsigned DEFAULT NULL,
  `disk_total_gb` bigint unsigned DEFAULT NULL,
  `disk_used_gb` bigint unsigned DEFAULT NULL,
  `io_read_mb` bigint unsigned DEFAULT NULL,
  `io_write_mb` bigint unsigned DEFAULT NULL,
  `io_read_bytes_total` bigint unsigned DEFAULT NULL,
  `io_write_bytes_total` bigint unsigned DEFAULT NULL,
  `net_in_mb` bigint unsigned DEFAULT NULL,
  `net_out_mb` bigint unsigned DEFAULT NULL,
  `net_in_bytes_total` bigint unsigned DEFAULT NULL,
  `net_out_bytes_total` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `system_metrics_source_captured_at_index` (`source`,`captured_at`),
  KEY `system_metrics_captured_at_index` (`captured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `transaction_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` bigint NOT NULL,
  `date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `transactions_invoice_id_foreign` (`invoice_id`),
  KEY `transactions_user_id_foreign` (`user_id`),
  CONSTRAINT `transactions_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `transactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `balance` bigint NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enhanced_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `stripe_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pm_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pm_last_four` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `payment_method` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_contact` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `currency` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `client_group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notify_general` tinyint(1) NOT NULL DEFAULT '1',
  `notify_invoice` tinyint(1) NOT NULL DEFAULT '1',
  `notify_support` tinyint(1) NOT NULL DEFAULT '1',
  `notify_product` tinyint(1) NOT NULL DEFAULT '1',
  `notify_domain` tinyint(1) NOT NULL DEFAULT '1',
  `notify_affiliate` tinyint(1) NOT NULL DEFAULT '1',
  `allow_late_fees` tinyint(1) NOT NULL DEFAULT '1',
  `send_overdue_notices` tinyint(1) NOT NULL DEFAULT '1',
  `tax_exempt` tinyint(1) NOT NULL DEFAULT '0',
  `separate_invoices` tinyint(1) NOT NULL DEFAULT '0',
  `disable_cc_processing` tinyint(1) NOT NULL DEFAULT '0',
  `marketing_emails_opt_in` tinyint(1) NOT NULL DEFAULT '0',
  `status_update_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `allow_sso` tinyint(1) NOT NULL DEFAULT '1',
  `admin_notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_stripe_id_index` (`stripe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_job_chunks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_job_chunks` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verification_job_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `chunk_no` int unsigned NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `input_disk` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input_key` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_disk` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invalid_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risky_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_count` int unsigned NOT NULL DEFAULT '0',
  `valid_count` int unsigned DEFAULT NULL,
  `invalid_count` int unsigned DEFAULT NULL,
  `risky_count` int unsigned DEFAULT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '0',
  `engine_server_id` bigint unsigned DEFAULT NULL,
  `assigned_worker_id` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `claim_expires_at` timestamp NULL DEFAULT NULL,
  `available_at` timestamp NULL DEFAULT NULL,
  `retry_attempt` int unsigned NOT NULL DEFAULT '0',
  `retry_parent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claim_token` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_job_chunks_verification_job_id_chunk_no_unique` (`verification_job_id`,`chunk_no`),
  KEY `verification_job_chunks_verification_job_id_status_index` (`verification_job_id`,`status`),
  KEY `verification_job_chunks_status_index` (`status`),
  KEY `verification_job_chunks_engine_server_id_index` (`engine_server_id`),
  KEY `verification_job_chunks_claim_expires_at_index` (`claim_expires_at`),
  KEY `verification_job_chunks_available_at_index` (`available_at`),
  KEY `verification_job_chunks_retry_parent_id_index` (`retry_parent_id`),
  CONSTRAINT `verification_job_chunks_engine_server_id_foreign` FOREIGN KEY (`engine_server_id`) REFERENCES `engine_servers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_job_chunks_verification_job_id_foreign` FOREIGN KEY (`verification_job_id`) REFERENCES `verification_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_job_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_job_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `verification_job_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `context` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `verification_job_logs_user_id_foreign` (`user_id`),
  KEY `verification_job_logs_verification_job_id_created_at_index` (`verification_job_id`,`created_at`),
  CONSTRAINT `verification_job_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_job_logs_verification_job_id_foreign` FOREIGN KEY (`verification_job_id`) REFERENCES `verification_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_job_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_job_metrics` (
  `verification_job_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phase` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progress_percent` tinyint unsigned NOT NULL DEFAULT '0',
  `processed_emails` int unsigned NOT NULL DEFAULT '0',
  `total_emails` int unsigned DEFAULT NULL,
  `cache_hit_count` int unsigned NOT NULL DEFAULT '0',
  `cache_miss_count` int unsigned NOT NULL DEFAULT '0',
  `writeback_written_count` int unsigned NOT NULL DEFAULT '0',
  `peak_cpu_percent` decimal(5,2) DEFAULT NULL,
  `cpu_time_ms` bigint unsigned DEFAULT NULL,
  `cpu_sampled_at` timestamp NULL DEFAULT NULL,
  `peak_memory_mb` decimal(8,2) DEFAULT NULL,
  `phase_started_at` timestamp NULL DEFAULT NULL,
  `phase_updated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`verification_job_id`),
  CONSTRAINT `verification_job_metrics_verification_job_id_foreign` FOREIGN KEY (`verification_job_id`) REFERENCES `verification_jobs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_jobs` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `engine_server_id` bigint unsigned DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `claim_expires_at` timestamp NULL DEFAULT NULL,
  `claim_token` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `engine_attempts` int unsigned NOT NULL DEFAULT '0',
  `status` enum('pending','processing','completed','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `verification_mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard',
  `origin` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'list_upload',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input_disk` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input_key` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `output_disk` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valid_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invalid_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `risky_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cached_valid_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cached_invalid_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cached_risky_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cache_miss_key` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  `failure_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `prepared_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `total_emails` int unsigned DEFAULT NULL,
  `valid_count` int unsigned DEFAULT NULL,
  `invalid_count` int unsigned DEFAULT NULL,
  `risky_count` int unsigned DEFAULT NULL,
  `unknown_count` int unsigned DEFAULT NULL,
  `cached_count` int unsigned DEFAULT NULL,
  `single_result_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `single_result_sub_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `single_result_score` int DEFAULT NULL,
  `single_result_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `single_result_verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_jobs_claim_token_unique` (`claim_token`),
  KEY `verification_jobs_user_id_index` (`user_id`),
  KEY `verification_jobs_status_index` (`status`),
  KEY `verification_jobs_created_at_index` (`created_at`),
  KEY `verification_jobs_failure_source_index` (`failure_source`),
  KEY `verification_jobs_engine_server_id_index` (`engine_server_id`),
  KEY `verification_jobs_claim_expires_at_index` (`claim_expires_at`),
  CONSTRAINT `verification_jobs_engine_server_id_foreign` FOREIGN KEY (`engine_server_id`) REFERENCES `engine_servers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_jobs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `legacy_uuid` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned NOT NULL,
  `verification_job_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checkout_intent_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pricing_plan_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `input_disk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `input_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_count` int unsigned NOT NULL,
  `amount_cents` int unsigned NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refunded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_orders_order_number_unique` (`order_number`),
  KEY `verification_orders_verification_job_id_foreign` (`verification_job_id`),
  KEY `verification_orders_checkout_intent_id_foreign` (`checkout_intent_id`),
  KEY `verification_orders_pricing_plan_id_foreign` (`pricing_plan_id`),
  KEY `verification_orders_user_id_index` (`user_id`),
  KEY `verification_orders_status_index` (`status`),
  KEY `verification_orders_created_at_index` (`created_at`),
  CONSTRAINT `verification_orders_checkout_intent_id_foreign` FOREIGN KEY (`checkout_intent_id`) REFERENCES `checkout_intents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_orders_pricing_plan_id_foreign` FOREIGN KEY (`pricing_plan_id`) REFERENCES `pricing_plans` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `verification_orders_verification_job_id_foreign` FOREIGN KEY (`verification_job_id`) REFERENCES `verification_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verification_workers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_workers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `worker_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `engine_server_id` bigint unsigned NOT NULL,
  `version` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `current_job_chunk_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_workers_worker_id_unique` (`worker_id`),
  KEY `verification_workers_current_job_chunk_id_foreign` (`current_job_chunk_id`),
  KEY `verification_workers_engine_server_id_index` (`engine_server_id`),
  KEY `verification_workers_last_seen_at_index` (`last_seen_at`),
  CONSTRAINT `verification_workers_current_job_chunk_id_foreign` FOREIGN KEY (`current_job_chunk_id`) REFERENCES `verification_job_chunks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `verification_workers_engine_server_id_foreign` FOREIGN KEY (`engine_server_id`) REFERENCES `engine_servers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `verifier_domains`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `verifier_domains` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `verifier_domains_domain_unique` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_01_06_093852_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_01_06_093912_create_customer_columns',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_01_06_093913_create_subscriptions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_01_06_093914_create_subscription_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_01_06_093915_add_meter_id_to_subscription_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_01_06_093916_add_meter_event_name_to_subscription_items_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_01_06_094022_create_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_01_06_114653_create_permission_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_01_06_120857_create_verification_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_01_06_134514_add_status_enum_to_verification_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_01_07_140000_create_verification_job_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_01_10_090000_create_engine_servers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_01_10_110000_create_pricing_plans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_01_10_110010_create_retention_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_01_10_110020_create_support_tickets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_01_10_110030_create_admin_audit_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_01_12_120000_add_tier_range_to_pricing_plans_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_01_12_121000_create_checkout_intents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_01_12_122000_create_verification_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_01_12_123000_add_stripe_fields_to_checkout_intents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_01_12_124500_add_input_to_verification_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_01_12_130000_add_failure_metadata_to_verification_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_01_12_130500_backfill_admin_failure_metadata_for_jobs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_01_12_131500_add_order_number_and_refund_to_verification_orders_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_01_12_131600_backfill_order_numbers_for_verification_orders',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_01_12_131700_add_payment_method_to_checkout_intents_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_01_12_132000_convert_verification_orders_to_bigint_ids',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_01_15_041039_create_support_tickets_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_01_15_041041_create_support_messages_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_01_14_120000_add_engine_claim_fields_to_verification_jobs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_01_15_000000_create_verification_job_chunks_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_01_15_000100_add_preparation_fields_to_verification_jobs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_01_16_000000_create_verification_workers_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_01_17_000000_add_final_result_keys_to_verification_jobs_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_01_18_000000_add_verification_mode_to_verification_jobs_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_01_18_000010_add_missing_fields_to_support_tickets_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_01_18_000020_create_email_verification_outcomes_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_01_18_000030_create_email_verification_outcome_imports_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2026_01_18_000040_create_email_verification_outcome_ingestions_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2026_01_19_000000_create_engine_verification_policies_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2026_01_19_000010_create_engine_settings_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2026_01_19_000020_add_engine_server_safety_fields',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2026_01_20_121049_create_portal_settings_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2026_01_21_062525_add_order_id_to_support_tickets_table',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2026_01_20_000000_add_role_account_settings_to_engine_settings_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2026_01_21_000000_add_catch_all_detection_to_engine_verification_policies_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2026_01_21_120000_add_identity_fields_to_engine_servers_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2026_01_21_121000_create_verifier_domains_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2026_01_21_121010_add_verifier_domain_id_to_engine_servers_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2026_01_25_121200_add_user_profile_fields',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2026_01_22_000000_create_engine_server_provisioning_bundles_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2026_01_22_010000_add_catch_all_output_policy_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2026_01_23_000000_add_single_check_fields_to_verification_jobs_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2026_01_23_000100_add_enhanced_enabled_to_users_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2026_01_25_121330_add_provider_policies_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2026_01_25_123717_add_retry_and_reputation_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2026_01_25_123725_add_retry_fields_to_verification_job_chunks_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2026_01_25_123736_create_engine_server_reputation_samples_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2026_01_25_130926_add_show_single_checks_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2026_01_25_140320_add_monitor_settings_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2026_01_25_140333_create_engine_server_reputation_checks_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2026_01_25_140342_create_engine_server_blacklist_events_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2026_01_25_140353_create_engine_server_delist_requests_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2026_01_26_000000_add_monitor_dns_settings_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2026_01_27_123500_add_cache_only_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2026_01_29_120000_add_cache_read_controls_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2026_02_01_130000_add_cache_miss_key_to_verification_jobs_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2026_02_01_130500_add_cache_writeback_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2026_02_01_131000_add_cache_writeback_test_mode_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2026_02_02_100000_create_verification_job_metrics_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2026_02_02_100010_create_system_metrics_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2026_02_02_100020_create_queue_metrics_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2026_02_02_100030_add_metrics_source_to_engine_settings_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2026_02_05_055548_add_credit_applied_to_checkout_intents_table',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2026_02_05_122000_create_billing_system_tables',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2026_02_05_130000_emergency_fix_billing',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2026_02_07_052447_create_credits_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2026_02_07_140000_fix_credits_table_schema',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2026_02_08_120000_add_whmcs_invoice_fields',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2026_02_15_054954_add_is_published_to_invoices_table',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2026_02_18_071057_add_invoice_id_to_checkout_intents_table',14);
