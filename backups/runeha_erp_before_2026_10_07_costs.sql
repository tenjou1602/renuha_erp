-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: runeha_erp
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accomplishments`
--

DROP TABLE IF EXISTS `accomplishments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accomplishments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `description` text NOT NULL,
  `completion_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_acc_project` (`project_id`),
  KEY `idx_acc_creator` (`created_by`),
  CONSTRAINT `fk_acc_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_acc_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accomplishments`
--

LOCK TABLES `accomplishments` WRITE;
/*!40000 ALTER TABLE `accomplishments` DISABLE KEYS */;
/*!40000 ALTER TABLE `accomplishments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `module` varchar(100) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  CONSTRAINT `fk_activity_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=416 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
INSERT INTO `activity_log` VALUES (1,1,'login','auth','User logged in','::1','2026-09-03 08:34:56'),(2,1,'Created project','Projects','Project: PRJ-2026-001','::1','2026-09-03 09:57:08'),(3,1,'Updated project','Projects','Project: PRJ-2026-001','::1','2026-09-03 09:58:21'),(4,1,'login','auth','User logged in','::1','2026-09-09 13:25:07'),(5,1,'login','auth','User logged in','::1','2026-09-13 06:16:47'),(6,1,'Deleted user','Admin','User ID: 3','::1','2026-09-13 06:17:42'),(7,1,'Deleted user','Admin','User ID: 5','::1','2026-09-13 06:17:47'),(8,1,'Deleted user','Admin','User ID: 7','::1','2026-09-13 06:17:51'),(9,1,'Deleted user','Admin','User ID: 9','::1','2026-09-13 06:17:56'),(10,1,'login','auth','User logged in','::1','2026-09-13 06:18:46'),(11,1,'logout','auth','User logged out','::1','2026-09-13 06:40:18'),(12,1,'login','auth','User logged in','::1','2026-09-13 06:40:33'),(13,1,'logout','auth','User logged out','::1','2026-09-13 06:40:46'),(14,1,'login','auth','User logged in','::1','2026-09-13 06:54:39'),(15,1,'login','auth','User logged in','::1','2026-09-13 06:54:59'),(16,1,'logout','auth','User logged out','::1','2026-09-13 07:05:46'),(17,1,'login','auth','User logged in','::1','2026-09-13 07:32:58'),(18,1,'login','auth','User logged in','::1','2026-09-13 12:00:07'),(19,1,'login','auth','User logged in','::1','2026-09-13 12:51:10'),(20,NULL,'Email skipped','Mail','SMTP credentials are not configured. To: admin@runeha.com | Your RUNEHA ERP login code','::1','2026-09-14 02:06:32'),(21,NULL,'Email skipped','Mail','SMTP credentials are not configured. To: admin@runeha.com | Your RUNEHA ERP login code','::1','2026-09-14 02:07:56'),(22,NULL,'Email skipped','Mail','SMTP credentials are not configured. To: admin@runeha.com | Your RUNEHA ERP login code','::1','2026-09-14 02:08:51'),(23,NULL,'Email skipped','Mail','SMTP credentials are not configured. To: admin@runeha.com | Your RUNEHA ERP login code','::1','2026-09-14 02:09:08'),(24,NULL,'Email skipped','Mail','SMTP credentials are not configured. To: engineering@runeha.com | Your RUNEHA ERP login code','::1','2026-09-14 02:09:11'),(25,NULL,'Email skipped','Mail','SMTP credentials are not configured. To: warehouse@runeha.com | Your RUNEHA ERP login code','::1','2026-09-14 02:09:12'),(26,NULL,'Email skipped','Mail','SMTP credentials are not configured. To: accounting@runeha.com | Your RUNEHA ERP login code','::1','2026-09-14 02:09:13'),(27,NULL,'Email skipped','Mail','SMTP credentials are not configured. To: admin@runeha.com | Your RUNEHA ERP login code','::1','2026-09-14 02:11:02'),(28,1,'login','auth','User logged in (OTP skipped: SMTP not configured)','::1','2026-09-14 02:11:12'),(29,1,'logout','auth','User logged out','::1','2026-09-14 02:11:13'),(30,NULL,'login','auth','User logged in (OTP skipped: SMTP not configured)','::1','2026-09-14 02:11:13'),(31,NULL,'Access denied','Security','Attempted access to /runeha_erp/modules/procurement/purchase_orders.php | allowed: procurement | department: projects','::1','2026-09-14 02:11:13'),(32,NULL,'logout','auth','User logged out','::1','2026-09-14 02:11:13'),(33,NULL,'login','auth','User logged in (OTP skipped: SMTP not configured)','::1','2026-09-14 02:11:14'),(34,NULL,'Access denied','Security','Attempted access to /runeha_erp/modules/accounting/invoices.php | allowed: accounting | department: warehouse','::1','2026-09-14 02:11:14'),(35,NULL,'logout','auth','User logged out','::1','2026-09-14 02:11:14'),(36,NULL,'login','auth','User logged in (OTP skipped: SMTP not configured)','::1','2026-09-14 02:11:14'),(37,1,'login','auth','User logged in (OTP skipped: SMTP not configured)','::1','2026-09-14 02:21:25'),(38,1,'logout','auth','User logged out','::1','2026-09-14 02:21:26'),(39,NULL,'login','auth','User logged in (OTP skipped: SMTP not configured)','::1','2026-09-14 02:21:26'),(40,NULL,'Access denied','Security','Attempted access to /runeha_erp/modules/procurement/purchase_orders.php | allowed: procurement | department: projects','::1','2026-09-14 02:21:26'),(41,NULL,'logout','auth','User logged out','::1','2026-09-14 02:21:26'),(42,NULL,'login','auth','User logged in (OTP skipped: SMTP not configured)','::1','2026-09-14 02:21:27'),(43,NULL,'Access denied','Security','Attempted access to /runeha_erp/modules/accounting/invoices.php | allowed: accounting | department: warehouse','::1','2026-09-14 02:21:27'),(44,NULL,'logout','auth','User logged out','::1','2026-09-14 02:21:27'),(45,NULL,'login','auth','User logged in (OTP skipped: SMTP not configured)','::1','2026-09-14 02:21:28'),(46,1,'login','auth','User logged in via password (OTP skipped: SMTP not configured)','::1','2026-09-14 02:27:25'),(47,1,'logout','auth','User logged out','::1','2026-09-14 02:27:28'),(48,NULL,'login','auth','User logged in via password (OTP skipped: SMTP not configured)','::1','2026-09-14 02:27:28'),(49,NULL,'Access denied','Security','Attempted access to /runeha_erp/modules/accounting/invoices.php | allowed: accounting | department: projects','::1','2026-09-14 02:27:29'),(50,1,'login','auth','User logged in via email OTP','::1','2026-09-14 02:39:19'),(51,1,'login','auth','User logged in via email OTP','::1','2026-09-14 02:41:17'),(53,1,'login','auth','User logged in via email OTP','::1','2026-09-15 15:37:24'),(54,1,'Deleted user','Admin','User ID: 8','::1','2026-09-16 01:58:41'),(55,1,'Deleted user','Admin','User ID: 6','::1','2026-09-16 01:58:46'),(56,1,'Deleted user','Admin','User ID: 4','::1','2026-09-16 01:58:55'),(57,1,'Deleted user','Admin','User ID: 2','::1','2026-09-16 01:58:57'),(58,1,'Created user','Admin','User: warehouse','::1','2026-09-16 02:01:07'),(59,1,'logout','auth','User logged out','::1','2026-09-16 02:01:12'),(60,10,'login','auth','User logged in via email OTP','::1','2026-09-16 02:01:53'),(61,10,'logout','auth','User logged out','::1','2026-09-16 02:03:41'),(62,1,'login','auth','User logged in via email OTP','::1','2026-09-16 02:04:14'),(63,1,'logout','auth','User logged out','::1','2026-09-16 06:21:22'),(64,1,'login','auth','User logged in via email OTP','::1','2026-09-16 06:23:27'),(65,1,'login','auth','User logged in via email OTP','::1','2026-09-16 06:25:49'),(66,1,'logout','auth','User logged out','::1','2026-09-16 06:30:30'),(67,1,'logout','auth','User logged out','::1','2026-09-16 06:33:58'),(68,1,'login','auth','User logged in via email OTP','::1','2026-09-16 10:11:44'),(69,1,'Downloaded project PDF','Projects','Project: PRJ-2026-001','::1','2026-09-16 10:12:40'),(70,1,'logout','auth','User logged out','::1','2026-09-16 10:13:49'),(71,10,'login','auth','User logged in via email OTP','::1','2026-09-16 10:14:31'),(72,10,'logout','auth','User logged out','::1','2026-09-16 10:19:46'),(73,1,'login','auth','User logged in via email OTP','::1','2026-09-16 10:20:20'),(74,NULL,'Email failed','Mail','SMTP Error: Could not connect to SMTP host. Failed to connect to server | To: joramvillanueva02+admin@gmail.com | RUNEHA ERP login code','::1','2026-09-19 04:30:03'),(75,NULL,'Email failed','Mail','SMTP Error: Could not connect to SMTP host. Failed to connect to server | To: joramvillanueva02+admin@gmail.com | RUNEHA ERP login code','::1','2026-09-19 04:30:29'),(76,1,'login','auth','User logged in via email OTP','::1','2026-09-19 04:43:52'),(77,1,'Created material','Procurement','Material: MAT-0001','::1','2026-09-19 04:50:41'),(78,1,'logout','auth','User logged out','::1','2026-09-19 04:59:07'),(79,10,'login','auth','User logged in via email OTP','::1','2026-09-19 04:59:53'),(80,10,'logout','auth','User logged out','::1','2026-09-19 05:04:22'),(81,1,'login','auth','User logged in via email OTP','::1','2026-09-19 05:05:54'),(82,1,'Created user','Admin','User: procurement','::1','2026-09-19 05:07:02'),(83,1,'logout','auth','User logged out','::1','2026-09-19 05:07:04'),(84,11,'login','auth','User logged in via email OTP','::1','2026-09-19 05:07:37'),(85,11,'logout','auth','User logged out','::1','2026-09-19 05:10:00'),(86,1,'login','auth','User logged in via email OTP','::1','2026-09-19 05:10:30'),(87,1,'login','auth','User logged in via email OTP','::1','2026-09-22 18:19:53'),(88,1,'login','auth','User logged in via email OTP','::1','2026-09-24 15:24:38'),(89,1,'Created user','Admin','User: accounting','::1','2026-09-24 15:35:48'),(90,1,'logout','auth','User logged out','::1','2026-09-24 15:35:53'),(91,12,'login','auth','User logged in via email OTP','::1','2026-09-24 15:36:34'),(92,12,'Created expense','Accounting','EXP: EXP-202609-0001','::1','2026-09-24 15:37:29'),(93,12,'logout','auth','User logged out','::1','2026-09-24 15:37:38'),(94,1,'login','auth','User logged in via email OTP','::1','2026-09-24 15:38:24'),(95,1,'logout','auth','User logged out','::1','2026-09-24 16:02:10'),(96,1,'login','auth','User logged in via email OTP','::1','2026-09-24 16:02:48'),(97,1,'Created user','Admin','User: engineering','::1','2026-09-24 16:05:18'),(98,1,'logout','auth','User logged out','::1','2026-09-24 16:05:22'),(99,NULL,'login','auth','User logged in via email OTP','::1','2026-09-24 16:06:23'),(100,NULL,'logout','auth','User logged out','::1','2026-09-24 16:06:45'),(101,1,'login','auth','User logged in via email OTP','::1','2026-09-24 16:07:17'),(102,1,'Deleted user','Admin','User ID: 13','::1','2026-09-24 16:07:30'),(103,1,'Created user','Admin','User: engineering','::1','2026-09-24 16:08:34'),(104,1,'logout','auth','User logged out','::1','2026-09-24 16:08:38'),(105,NULL,'login','auth','User logged in via email OTP','::1','2026-09-24 16:10:30'),(106,NULL,'logout','auth','User logged out','::1','2026-09-24 16:13:01'),(107,1,'login','auth','User logged in via email OTP','::1','2026-09-24 16:13:31'),(108,1,'Updated user','Admin','User: engineering','::1','2026-09-24 16:13:50'),(109,1,'login','auth','User logged in via email OTP','::1','2026-09-25 08:21:49'),(110,1,'logout','auth','User logged out','::1','2026-09-25 08:22:33'),(111,NULL,'login','auth','User logged in via email OTP','::1','2026-09-25 08:23:20'),(112,1,'login','auth','User logged in via email OTP','::1','2026-09-25 09:37:41'),(113,NULL,'logout','auth','User logged out','::1','2026-09-25 09:46:38'),(114,NULL,'login','auth','User logged in via email OTP','::1','2026-09-25 09:47:28'),(115,NULL,'logout','auth','User logged out','::1','2026-09-25 09:50:49'),(116,NULL,'login','auth','User logged in via email OTP','::1','2026-09-25 09:51:52'),(117,NULL,'login','auth','User logged in via email OTP','::1','2026-09-25 09:54:25'),(118,1,'Deleted user','Admin','User ID: 14','::1','2026-09-25 09:54:50'),(119,1,'Created user','Admin','User: engineering','::1','2026-09-25 09:55:31'),(120,1,'Deleted user','Admin','User ID: 15','::1','2026-09-25 09:58:00'),(121,1,'logout','auth','User logged out','::1','2026-09-25 09:58:03'),(123,1,'login','auth','User logged in via email OTP','::1','2026-09-25 09:58:50'),(124,1,'Created user','Admin','User: projects','::1','2026-09-25 10:02:10'),(125,1,'Created user','Admin','User: engineering','::1','2026-09-25 10:09:37'),(126,17,'login','auth','User logged in via email OTP','::1','2026-09-25 10:10:16'),(127,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:10:41'),(128,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:10:44'),(129,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:10:53'),(130,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:11:01'),(131,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:11:11'),(132,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:11:18'),(133,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:11:21'),(134,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:11:23'),(135,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:11:25'),(136,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:11:26'),(137,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/project_details.php | allowed: projects | department: engineering','::1','2026-09-25 10:11:32'),(138,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:12:56'),(139,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:14:34'),(140,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:14:36'),(141,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:14:37'),(142,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:14:38'),(143,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:14:39'),(144,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:15:04'),(145,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:15:06'),(146,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:18:58'),(147,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:19:00'),(148,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:19:01'),(149,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:19:02'),(150,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/project_details.php | allowed: projects | department: engineering','::1','2026-09-25 10:19:05'),(151,17,'logout','auth','User logged out','::1','2026-09-25 10:21:52'),(152,17,'login','auth','User logged in via email OTP','::1','2026-09-25 10:22:38'),(153,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:22:41'),(154,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:22:43'),(155,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:22:45'),(156,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:29:17'),(157,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:29:20'),(158,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:29:21'),(159,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:31:21'),(160,17,'logout','auth','User logged out','::1','2026-09-25 10:33:28'),(161,17,'login','auth','User logged in via email OTP','::1','2026-09-25 10:33:59'),(162,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/dashboard.php | allowed: projects | department: engineering','::1','2026-09-25 10:34:03'),(163,17,'Access denied','Security','Attempted access to /runeha_erp/modules/projects/projects.php | allowed: projects | department: engineering','::1','2026-09-25 10:34:05'),(164,17,'logout','auth','User logged out','::1','2026-09-25 10:37:18'),(165,17,'login','auth','User logged in via email OTP','::1','2026-09-25 10:37:57'),(166,1,'logout','auth','User logged out','::1','2026-09-25 12:04:28'),(167,1,'login','auth','User logged in via email OTP','::1','2026-09-25 12:05:15'),(168,1,'login','auth','User logged in via email OTP','::1','2026-09-25 12:10:30'),(169,1,'login','auth','User logged in via email OTP','::1','2026-09-25 12:17:44'),(170,1,'logout','auth','User logged out','::1','2026-09-25 12:25:11'),(171,1,'login','auth','User logged in via email OTP','::1','2026-09-25 12:26:05'),(172,1,'login','auth','User logged in via email OTP','::1','2026-09-25 12:29:32'),(173,1,'login','auth','User logged in via email OTP','::1','2026-09-25 12:39:30'),(174,1,'login','auth','User logged in via email OTP','::1','2026-09-25 12:51:51'),(175,11,'login','auth','User logged in via email OTP','::1','2026-09-25 13:05:42'),(176,1,'login','auth','User logged in via email OTP','::1','2026-09-25 13:30:59'),(177,17,'Created project','Projects','Project: PRJ-2026-1.844674407371E+19','::1','2026-09-25 14:11:31'),(178,17,'Downloaded project PDF','Projects','Project: PRJ-2026-1.844674407371E+19','::1','2026-09-25 14:13:11'),(179,17,'logout','auth','User logged out','::1','2026-09-25 14:13:36'),(180,11,'login','auth','User logged in via email OTP','::1','2026-09-25 14:14:55'),(181,11,'Created supplier','Procurement','Supplier: garlan','::1','2026-09-25 14:18:24'),(182,11,'logout','auth','User logged out','::1','2026-09-25 14:29:42'),(183,1,'login','auth','User logged in via email OTP','::1','2026-09-25 14:30:30'),(184,1,'login','auth','User logged in via email OTP','::1','2026-09-25 15:31:35'),(185,10,'login','auth','User logged in via email OTP','::1','2026-09-25 15:46:01'),(186,10,'Database error','Warehouse','SQLSTATE[HY000]: General error: 1005 Can\'t create table `runeha_erp`.`material_requests` (errno: 150 \"Foreign key constraint is incorrectly formed\")','::1','2026-09-25 15:50:32'),(187,10,'Database error','Warehouse','SQLSTATE[HY000]: General error: 1005 Can\'t create table `runeha_erp`.`material_requests` (errno: 150 \"Foreign key constraint is incorrectly formed\")','::1','2026-09-25 15:50:50'),(188,10,'Database error','Warehouse','SQLSTATE[HY000]: General error: 1005 Can\'t create table `runeha_erp`.`material_requests` (errno: 150 \"Foreign key constraint is incorrectly formed\")','::1','2026-09-25 15:52:16'),(189,10,'Low stock alert','Warehouse','motor at 1 (min 1)','::1','2026-09-25 15:53:53'),(190,10,'Issued stock','Warehouse','Material ID: 1, Qty: 2','::1','2026-09-25 15:53:57'),(191,11,'Created supplier','Procurement','Supplier: joram','::1','2026-09-25 16:03:13'),(192,1,'logout','auth','User logged out','::1','2026-09-25 16:43:44'),(193,17,'login','auth','User logged in via email OTP','::1','2026-09-25 16:44:35'),(194,17,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-25 16:49:21'),(195,11,'login','auth','User logged in via email OTP','::1','2026-09-25 16:53:29'),(196,17,'logout','auth','User logged out','::1','2026-09-25 16:54:04'),(197,1,'login','auth','User logged in via email OTP','::1','2026-09-25 16:54:25'),(198,1,'Deleted user','Admin','User ID: 16','::1','2026-09-25 17:52:05'),(199,10,'logout','auth','User logged out','::1','2026-09-25 18:40:10'),(200,1,'logout','auth','User logged out','::1','2026-09-25 22:40:05'),(201,11,'logout','auth','User logged out','::1','2026-09-25 22:41:13'),(202,1,'login','auth','User logged in via password (OTP skipped: SMTP not configured)','::1','2026-09-25 22:55:21'),(203,1,'logout','auth','User logged out','::1','2026-09-25 22:56:01'),(204,1,'login','auth','User logged in via email OTP','::1','2026-09-25 23:05:42'),(205,1,'logout','auth','User logged out','::1','2026-09-25 23:08:27'),(206,11,'logout','auth','User logged out','::1','2026-09-25 23:33:04'),(207,1,'login','auth','User logged in via email OTP','::1','2026-09-26 00:19:32'),(208,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-26 00:21:44'),(209,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-26 00:21:53'),(210,1,'Database error','Warehouse','SQLSTATE[HY000]: General error: 1005 Can\'t create table `runeha_erp`.`material_requests` (errno: 150 \"Foreign key constraint is incorrectly formed\")','::1','2026-09-26 00:22:42'),(211,1,'logout','auth','User logged out','::1','2026-09-26 00:33:32'),(212,1,'login','auth','User logged in via email OTP','::1','2026-09-26 00:34:03'),(213,12,'login','auth','User logged in via email OTP','::1','2026-09-26 01:29:56'),(214,1,'logout','auth','User logged out','::1','2026-09-26 01:46:16'),(215,10,'login','auth','User logged in via email OTP','::1','2026-09-26 01:47:03'),(216,12,'logout','auth','User logged out','::1','2026-09-26 02:10:52'),(217,1,'login','auth','User logged in via email OTP','::1','2026-09-26 16:41:43'),(218,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-26 16:42:02'),(219,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-26 16:42:18'),(220,11,'login','auth','User logged in via email OTP','::1','2026-09-26 16:46:18'),(221,11,'Access denied','Security','Attempted access to /runeha_erp/modules/procurement/materials.php | allowed: warehouse | department: procurement','::1','2026-09-26 16:47:55'),(222,11,'Access denied','Security','Attempted access to /runeha_erp/modules/procurement/materials.php | allowed: warehouse | department: procurement','::1','2026-09-27 10:11:18'),(223,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-27 10:16:04'),(224,1,'Downloaded project PDF','Projects','Project: PRJ-2026-002','::1','2026-09-27 10:17:15'),(225,1,'Downloaded project PDF','Projects','Project: PRJ-2026-001','::1','2026-09-27 10:17:37'),(226,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-27 10:18:28'),(227,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-27 10:24:14'),(228,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-27 10:25:40'),(229,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'total_required\' not supported (reference to group function)','::1','2026-09-27 10:25:51'),(230,11,'Access denied','Security','Attempted access to /runeha_erp/modules/procurement/materials.php | allowed: warehouse | department: procurement','::1','2026-09-27 10:35:27'),(231,1,'logout','auth','User logged out','::1','2026-09-27 14:04:02'),(232,1,'login','auth','User logged in via email OTP','::1','2026-09-27 14:04:26'),(233,1,'logout','auth','User logged out','::1','2026-09-27 14:11:04'),(234,1,'login','auth','User logged in via password (OTP skipped: SMTP not configured)','::1','2026-09-27 14:11:12'),(235,1,'login','auth','User logged in via password (OTP skipped: SMTP not configured)','::1','2026-09-27 14:12:13'),(236,11,'Access denied','Security','Attempted access to /runeha_erp/modules/procurement/materials.php | allowed: warehouse | department: procurement','::1','2026-09-27 14:21:58'),(237,1,'logout','auth','User logged out','::1','2026-09-27 15:04:06'),(238,1,'login','auth','User logged in via password (OTP skipped: SMTP not configured)','::1','2026-09-27 15:04:18'),(239,1,'logout','auth','User logged out','::1','2026-09-28 13:14:24'),(240,11,'login','auth','User logged in via password (OTP skipped: SMTP not configured)','::1','2026-09-28 13:14:32'),(241,11,'logout','auth','User logged out','::1','2026-09-28 13:15:49'),(242,12,'login','auth','User logged in via password (OTP skipped: SMTP not configured)','::1','2026-09-28 13:15:57'),(243,11,'Access denied','Security','Attempted access to /runeha_erp/modules/procurement/materials.php | allowed: warehouse | department: procurement','::1','2026-09-28 13:26:20'),(244,11,'Created supplier','Procurement','Supplier: Villanueva Joram S','::1','2026-09-28 13:27:41'),(245,11,'logout','auth','User logged out','::1','2026-09-28 13:28:17'),(246,1,'login','auth','User logged in via OTP','::1','2026-09-28 13:30:51'),(247,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'demand_count\' not supported (reference to group function)','::1','2026-09-28 14:34:27'),(248,1,'Database error','System','SQLSTATE[HY000]: General error: 1267 Illegal mix of collations (utf8mb4_unicode_ci,IMPLICIT) and (utf8mb4_general_ci,IMPLICIT) for operation \'=\'','::1','2026-09-28 15:03:05'),(249,1,'Database error','System','SQLSTATE[HY000]: General error: 1267 Illegal mix of collations (utf8mb4_unicode_ci,IMPLICIT) and (utf8mb4_general_ci,IMPLICIT) for operation \'=\'','::1','2026-09-28 15:03:14'),(250,1,'login','auth','User logged in via OTP','::1','2026-09-30 13:59:47'),(251,10,'login','auth','User logged in via OTP','::1','2026-09-30 14:03:35'),(252,1,'login','auth','User logged in via OTP','::1','2026-10-01 17:18:48'),(253,10,'logout','auth','User logged out','::1','2026-10-01 17:30:54'),(254,1,'login','auth','User logged in via OTP','::1','2026-10-01 17:32:12'),(255,1,'Enrolled project','Projects','Project: PRJ-2026-003','::1','2026-10-01 18:37:17'),(256,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 19:10:53'),(257,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 19:10:55'),(258,1,'logout','auth','User logged out','::1','2026-10-01 20:39:12'),(259,1,'login','auth','User logged in via OTP','::1','2026-10-01 20:41:45'),(260,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:41:52'),(261,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:45:43'),(262,1,'logout','auth','User logged out','::1','2026-10-01 20:46:40'),(263,12,'login','auth','User logged in via OTP','::1','2026-10-01 20:50:22'),(264,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:50:22'),(265,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:50:27'),(266,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:50:30'),(267,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:50:35'),(268,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:50:39'),(269,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:50:39'),(270,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:50:40'),(271,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:50:41'),(272,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:51:15'),(273,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:51:18'),(274,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 20:51:20'),(275,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 21:10:55'),(276,12,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 21:14:56'),(277,12,'logout','auth','User logged out','::1','2026-10-01 21:15:34'),(278,1,'login','auth','User logged in via OTP','::1','2026-10-01 21:31:28'),(279,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 21:31:28'),(280,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 21:31:42'),(281,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 21:34:19'),(282,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 21:35:44'),(283,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 21:41:53'),(284,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-01 21:41:54'),(285,1,'logout','auth','User logged out','::1','2026-10-02 02:06:11'),(286,1,'login','auth','User logged in via OTP','::1','2026-10-02 02:07:08'),(287,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-02 02:07:09'),(288,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-02 02:11:01'),(289,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-02 02:11:15'),(290,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-02 02:15:02'),(291,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.invoices\' doesn\'t exist','::1','2026-10-02 02:39:05'),(292,1,'logout','auth','User logged out','::1','2026-10-02 02:57:46'),(293,1,'login','auth','User logged in via OTP','::1','2026-10-02 03:00:28'),(294,1,'Database error','System','SQLSTATE[42S22]: Column not found: 1247 Reference \'demand_count\' not supported (reference to group function)','::1','2026-10-02 07:41:33'),(295,1,'Created project','Projects','Project: PRJ-2026-004','::1','2026-10-02 07:43:38'),(296,1,'Created project','Projects','Project: PRJ-2026-005','::1','2026-10-02 07:46:18'),(297,1,'logout','auth','User logged out','::1','2026-10-02 07:47:24'),(298,12,'login','auth','User logged in via OTP','::1','2026-10-02 07:48:53'),(299,1,'login','auth','User logged in via OTP','::1','2026-10-02 07:50:11'),(300,17,'login','auth','User logged in via OTP','::1','2026-10-02 07:52:44'),(301,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 07:54:58'),(302,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 07:55:11'),(303,17,'Updated project','Projects','Project: PRJ-2026-005','::1','2026-10-02 07:55:24'),(304,1,'Created project','Projects','Project: PRJ-2026-006','::1','2026-10-02 07:58:49'),(305,1,'Created project','Projects','Project: PRJ-2026-007','::1','2026-10-02 09:53:42'),(306,12,'Created client quotation','Accounting','Q: Q-2026-001','::1','2026-10-02 09:57:07'),(307,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 09:58:00'),(308,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 09:58:12'),(309,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 09:58:44'),(310,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 10:23:32'),(311,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 10:47:48'),(312,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 11:29:15'),(313,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 11:29:35'),(314,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 11:31:01'),(315,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 11:32:06'),(316,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 11:32:17'),(317,1,'Created project','Projects','Project: PRJ-2026-008','::1','2026-10-02 11:48:39'),(318,12,'Created contract','Accounting','Contract: ','::1','2026-10-02 11:56:40'),(319,12,'Created labor budget','Accounting','Budget: DESIGN AND BUILD OF FIRE PROTECTION SYSTEM labor','::1','2026-10-02 12:15:54'),(320,12,'Created labor budget','Accounting','Budget: DESIGN AND BUILD OF FIRE PROTECTION SYSTEM labor','::1','2026-10-02 12:15:55'),(321,12,'Assigned project in-charge','Accounting','johnpaul to project 8','::1','2026-10-02 12:18:39'),(322,12,'Created fund','Accounting','Fund: PRJ-2026-008 - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','::1','2026-10-02 12:25:40'),(323,17,'Requested materials','Projects','PR-202610-0001 for PRJ-2026-008','::1','2026-10-02 12:42:46'),(324,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 12:43:54'),(325,17,'logout','auth','User logged out','::1','2026-10-02 12:44:33'),(326,11,'login','auth','User logged in via OTP','::1','2026-10-02 12:45:20'),(327,11,'Approved purchase request','Procurement','ID: 1, Status: approved','::1','2026-10-02 12:47:48'),(328,11,'Approved purchase request','Procurement','ID: 1, Status: approved','::1','2026-10-02 12:47:49'),(329,11,'Access denied','Security','Attempted access to /runeha_erp/modules/warehouse/receive_purchases.php | allowed: warehouse | department: procurement','::1','2026-10-02 12:53:11'),(330,11,'Created quotation','Procurement','QT: QT-202610-0001','::1','2026-10-02 13:03:38'),(331,11,'Created purchase order','Procurement','PO: PO-202610-0001','::1','2026-10-02 13:03:53'),(332,11,'Created purchase order','Procurement','PO: PO-202610-0002','::1','2026-10-02 13:04:25'),(333,11,'Marked PO ready for warehouse','Procurement','PO id 4','::1','2026-10-02 13:04:37'),(334,11,'logout','auth','User logged out','::1','2026-10-02 13:05:28'),(335,10,'login','auth','User logged in via OTP','::1','2026-10-02 13:06:07'),(336,10,'Received purchase order','Warehouse','PO: PO-202610-0002','::1','2026-10-02 13:06:18'),(337,10,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 13:50:17'),(338,1,'Deleted project','Projects','Project ID: 8','::1','2026-10-02 14:03:14'),(339,1,'Created project','Projects','Project: PRJ-2026-001','::1','2026-10-02 14:03:55'),(340,1,'Deleted project','Projects','Project ID: 9','::1','2026-10-02 14:04:36'),(341,1,'Created project','Projects','Project: PRJ-2026-001','::1','2026-10-02 14:14:17'),(342,1,'Updated project','Projects','Project: PRJ-2026-001','::1','2026-10-02 14:15:35'),(343,12,'Created labor budget','Accounting','Budget: DESIGN AND BUILD OF FIRE PROTECTION SYSTEM labor','::1','2026-10-02 14:20:23'),(344,12,'Created contract','Accounting','Contract: ','::1','2026-10-02 14:23:18'),(345,12,'Created fund','Accounting','Fund: PRJ-2026-001 - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','::1','2026-10-02 14:25:26'),(346,17,'login','auth','User logged in via OTP','::1','2026-10-02 14:27:49'),(347,17,'Requested materials','Projects','PR-202610-0002 for PRJ-2026-001','::1','2026-10-02 14:28:40'),(348,17,'logout','auth','User logged out','::1','2026-10-02 14:28:54'),(349,11,'login','auth','User logged in via OTP','::1','2026-10-02 14:29:37'),(350,11,'Created quotation','Procurement','QT: QT-202610-0001','::1','2026-10-02 14:30:49'),(351,11,'Updated quotation','Procurement','QT: QT-202610-0001','::1','2026-10-02 14:32:12'),(352,11,'Updated quotation','Procurement','QT: QT-202610-0001','::1','2026-10-02 14:37:55'),(353,11,'Created purchase order','Procurement','PO: PO-202610-0003','::1','2026-10-02 14:38:19'),(354,11,'Created purchase order','Procurement','PO: PO-202610-0004','::1','2026-10-02 14:38:39'),(355,11,'Updated quotation','Procurement','QT: QT-202610-0001','::1','2026-10-02 14:47:52'),(356,11,'Created purchase order','Procurement','PO: PO-202610-0005','::1','2026-10-02 14:48:09'),(357,11,'Access denied','Security','Attempted access to /runeha_erp/modules/warehouse/receive_purchases.php | allowed: warehouse | department: procurement','::1','2026-10-02 14:48:55'),(358,11,'Access denied','Security','Attempted access to /runeha_erp/modules/warehouse/receive_purchases.php | allowed: warehouse | department: procurement','::1','2026-10-02 14:49:40'),(359,11,'logout','auth','User logged out','::1','2026-10-02 14:50:53'),(360,10,'login','auth','User logged in via OTP','::1','2026-10-02 14:52:37'),(361,10,'Received purchase order','Warehouse','PO: PO-202610-0005','::1','2026-10-02 14:52:58'),(362,10,'Released inventory','Warehouse','PRJ-2026-001 DESIGN AND BUILD OF FIRE PROTECTION SYSTEM: 100mmØ 3 no/s (FIFO-20261002-001 × 3), 100mmØ 3 no/s (FIFO-20261002-002 × 3)','::1','2026-10-02 15:02:59'),(363,10,'logout','auth','User logged out','::1','2026-10-02 15:08:34'),(364,17,'login','auth','User logged in via OTP','::1','2026-10-02 15:09:35'),(365,17,'Engineer received site materials','Projects','PR-202610-0002','::1','2026-10-02 15:09:45'),(366,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 16:06:52'),(367,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:08:56'),(368,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:09:51'),(369,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:10:06'),(370,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:10:10'),(371,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:10:23'),(372,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:16:32'),(373,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:16:33'),(374,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:16:36'),(375,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:16:44'),(376,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:17:01'),(377,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:17:08'),(378,1,'Database error','System','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'FROM materials m\r\n        WHERE m.status = \'active\'\r\n        ORDER BY \r\n     ...\' at line 10','::1','2026-10-02 16:17:14'),(379,12,'logout','auth','User logged out','::1','2026-10-02 23:02:32'),(380,1,'login','auth','User logged in via OTP','::1','2026-10-02 23:04:02'),(381,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-02 23:57:50'),(382,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-03 00:01:38'),(383,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-03 00:04:12'),(384,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-03 00:05:59'),(385,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-03 00:07:12'),(386,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-03 00:14:23'),(387,1,'Downloaded project PDF','Projects','Project: PRJ-2026-001','::1','2026-10-03 00:16:42'),(388,1,'Created user','Admin','User: joram','::1','2026-10-03 00:45:35'),(389,1,'Created project','Projects','Project: PRJ-2026-002','::1','2026-10-03 01:01:23'),(390,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-03 01:02:16'),(391,1,'logout','auth','User logged out','::1','2026-10-03 01:03:46'),(392,12,'login','auth','User logged in via OTP','::1','2026-10-03 01:04:17'),(393,1,'Created project','Projects','Project: PRJ-2026-003','::1','2026-10-03 01:06:32'),(394,1,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-03 01:22:21'),(395,1,'Requested materials','Projects','PR-202610-0003 for PRJ-2026-003','::1','2026-10-03 01:46:32'),(396,1,'logout','auth','User logged out','::1','2026-10-03 01:48:06'),(397,1,'login','auth','User logged in via OTP','::1','2026-10-03 02:04:13'),(398,1,'Created project','Projects','Project: PRJ-2026-004','::1','2026-10-03 02:16:20'),(399,1,'Created project','Projects','Project: PRJ-2026-005','::1','2026-10-03 02:19:04'),(400,1,'Created project','Projects','Project: PRJ-2026-006','::1','2026-10-03 02:27:24'),(401,1,'login','auth','User logged in via OTP','::1','2026-10-06 13:50:38'),(402,1,'Created project','Projects','Project: PRJ-2026-007','::1','2026-10-06 15:08:39'),(403,12,'login','auth','User logged in via OTP','::1','2026-10-06 15:17:56'),(404,1,'Created project','Projects','Project: PRJ-2026-008','::1','2026-10-07 10:52:22'),(405,12,'logout','auth','User logged out','::1','2026-10-07 10:58:08'),(406,17,'login','auth','User logged in via OTP','::1','2026-10-07 10:58:43'),(407,17,'Database error','System','SQLSTATE[42S02]: Base table or view not found: 1146 Table \'runeha_erp.personnel\' doesn\'t exist','::1','2026-10-07 10:59:01'),(408,17,'Requested materials','Projects','PR-202610-0004 for PRJ-2026-008','::1','2026-10-07 11:04:11'),(409,11,'login','auth','User logged in via OTP','::1','2026-10-07 11:06:10'),(410,11,'logout','auth','User logged out','::1','2026-10-07 11:20:56'),(411,10,'login','auth','User logged in via OTP','::1','2026-10-07 11:21:43'),(412,12,'Assigned project in-charge','Accounting','Joram Villanueva to project 17','::1','2026-10-07 11:36:48'),(413,12,'Database error','System','SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'\' for key \'uq_contract_number\'','::1','2026-10-07 11:37:09'),(414,12,'Database error','System','SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'\' for key \'uq_contract_number\'','::1','2026-10-07 11:37:21'),(415,10,'Updated material','Procurement','Material: MAT-F346B6','::1','2026-10-07 11:39:30');
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attachments`
--

DROP TABLE IF EXISTS `attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attachments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `file_size` int(10) unsigned DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_att_project` (`project_id`),
  KEY `idx_att_uploader` (`uploaded_by`),
  CONSTRAINT `fk_att_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attachments`
--

LOCK TABLES `attachments` WRITE;
/*!40000 ALTER TABLE `attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `chart_of_accounts`
--

DROP TABLE IF EXISTS `chart_of_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `chart_of_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `account_type` enum('asset','income') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coa_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `chart_of_accounts`
--

LOCK TABLES `chart_of_accounts` WRITE;
/*!40000 ALTER TABLE `chart_of_accounts` DISABLE KEYS */;
INSERT INTO `chart_of_accounts` VALUES (1,'1000','Cash','asset','2026-10-01 19:10:48'),(2,'1200','Accounts Receivable','asset','2026-10-01 19:10:48'),(3,'4000','Sales/Service Revenue','income','2026-10-01 19:10:48');
/*!40000 ALTER TABLE `chart_of_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_quotation_items`
--

DROP TABLE IF EXISTS `client_quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_quotation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `line_type` enum('material','labor','other') NOT NULL DEFAULT 'other',
  `item_name` varchar(255) DEFAULT NULL,
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `description` text NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_cqi_quotation` (`quotation_id`)
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_quotation_items`
--

LOCK TABLES `client_quotation_items` WRITE;
/*!40000 ALTER TABLE `client_quotation_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `client_quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `client_quotations`
--

DROP TABLE IF EXISTS `client_quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `client_quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL,
  `client_name` varchar(200) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `scope_of_work` text DEFAULT NULL,
  `validity_date` date DEFAULT NULL,
  `status` enum('draft','sent','approved','rejected','converted') NOT NULL DEFAULT 'draft',
  `subtotal` decimal(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_client_quotation_number` (`quotation_number`),
  KEY `idx_cq_project` (`project_id`),
  KEY `idx_cq_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `client_quotations`
--

LOCK TABLES `client_quotations` WRITE;
/*!40000 ALTER TABLE `client_quotations` DISABLE KEYS */;
/*!40000 ALTER TABLE `client_quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_clients_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (1,'john',NULL,NULL,NULL,NULL,NULL,'active',12,'2026-10-02 08:57:33',NULL),(2,'J.E. MANALO & CO., INC.',NULL,NULL,NULL,NULL,NULL,'active',12,'2026-10-02 09:57:07',NULL);
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contracts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `contract_number` varchar(50) NOT NULL,
  `contract_name` varchar(200) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `client` varchar(200) NOT NULL,
  `contract_value` decimal(15,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('draft','active','completed','cancelled','expired') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_number` (`contract_number`),
  KEY `idx_contracts_project` (`project_id`),
  KEY `idx_contracts_creator` (`created_by`),
  CONSTRAINT `fk_contracts_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_contracts_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contracts`
--

LOCK TABLES `contracts` WRITE;
/*!40000 ALTER TABLE `contracts` DISABLE KEYS */;
INSERT INTO `contracts` VALUES (2,'','DESIGN AND BUILD OF FIRE PROTECTION SYSTEM',10,'J.E. MANALO & CO., INC.',48121012.80,'0000-00-00','0000-00-00','From quotation. Material cost ₱12,540,730.00. Labor cost ₱2,172,860.00.','draft',12,'2026-10-02 14:23:18',NULL);
/*!40000 ALTER TABLE `contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `expense_number` varchar(50) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `expense_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_expense_number` (`expense_number`),
  UNIQUE KEY `uq_expenses_po` (`purchase_order_id`),
  KEY `idx_expenses_project` (`project_id`),
  KEY `idx_expenses_creator` (`created_by`),
  CONSTRAINT `fk_expenses_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (2,'EXP-202610-0001',10,'Materials','Purchased materials from Villanueva Joram S (PO-202610-0005)',14999.94,'2026-10-02','Purchase order','PO-202610-0005','paid',11,'2026-10-02 15:18:11','2026-10-03 01:16:07',7);
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `funds`
--

DROP TABLE IF EXISTS `funds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `funds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `fund_name` varchar(200) NOT NULL,
  `fund_type` enum('general','project','reserve','emergency') NOT NULL DEFAULT 'general',
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive','frozen') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_funds_project` (`project_id`),
  KEY `idx_funds_creator` (`created_by`),
  CONSTRAINT `fk_funds_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_funds_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `funds`
--

LOCK TABLES `funds` WRITE;
/*!40000 ALTER TABLE `funds` DISABLE KEYS */;
INSERT INTO `funds` VALUES (2,'PRJ-2026-001 - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','project',48121012.80,28249.94,10,'Quotation total for this project. Material ₱12,540,730.00. Labor ₱2,172,860.00.','active',12,'2026-10-02 14:25:26','2026-10-07 12:18:36');
/*!40000 ALTER TABLE `funds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `line_type` enum('material','labor','other') NOT NULL DEFAULT 'other',
  `material_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_ii_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `quotation_id` bigint(20) unsigned DEFAULT NULL,
  `client` varchar(200) NOT NULL,
  `client_id` bigint(20) unsigned DEFAULT NULL,
  `client_name` varchar(200) DEFAULT NULL,
  `invoice_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT NULL,
  `paid_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','sent','partial','paid','overdue','cancelled') NOT NULL DEFAULT 'sent',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  UNIQUE KEY `uq_invoices_quotation` (`quotation_id`),
  KEY `idx_invoices_project` (`project_id`),
  KEY `idx_invoices_client` (`client_id`),
  KEY `idx_invoices_creator` (`created_by`),
  CONSTRAINT `fk_invoices_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_invoices_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_entries`
--

DROP TABLE IF EXISTS `journal_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entry_date` date NOT NULL,
  `reference_type` enum('invoice','payment') NOT NULL,
  `reference_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_journal_ref` (`reference_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_entries`
--

LOCK TABLES `journal_entries` WRITE;
/*!40000 ALTER TABLE `journal_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `journal_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `journal_lines`
--

DROP TABLE IF EXISTS `journal_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `journal_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `journal_entry_id` bigint(20) unsigned NOT NULL,
  `account_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_jl_entry` (`journal_entry_id`),
  KEY `idx_jl_account` (`account_id`),
  KEY `idx_jl_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `journal_lines`
--

LOCK TABLES `journal_lines` WRITE;
/*!40000 ALTER TABLE `journal_lines` DISABLE KEYS */;
/*!40000 ALTER TABLE `journal_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `labor_budget_lines`
--

DROP TABLE IF EXISTS `labor_budget_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `labor_budget_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `labor_budget_id` bigint(20) unsigned NOT NULL,
  `role_key` varchar(40) NOT NULL,
  `role_name` varchar(150) NOT NULL,
  `headcount` int(10) unsigned NOT NULL DEFAULT 0,
  `wage` decimal(15,2) NOT NULL DEFAULT 0.00,
  `assigned_count` int(10) unsigned NOT NULL DEFAULT 0,
  `line_total` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_lbl_budget` (`labor_budget_id`),
  CONSTRAINT `fk_lbl_budget` FOREIGN KEY (`labor_budget_id`) REFERENCES `labor_budgets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labor_budget_lines`
--

LOCK TABLES `labor_budget_lines` WRITE;
/*!40000 ALTER TABLE `labor_budget_lines` DISABLE KEYS */;
INSERT INTO `labor_budget_lines` VALUES (11,3,'engineer','Engineer',1,1000.00,1,1000.00),(12,3,'architect','Architect',1,1000.00,1,1000.00),(13,3,'foreman','Foreman',4,800.00,4,3200.00),(14,3,'skilled','Skilled Workers',5,700.00,5,3500.00),(15,3,'helper','Construction Helpers',7,650.00,7,4550.00);
/*!40000 ALTER TABLE `labor_budget_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `labor_budgets`
--

DROP TABLE IF EXISTS `labor_budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `labor_budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `budget_name` varchar(200) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `position` varchar(150) DEFAULT NULL,
  `allocated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `used_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive','completed') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_labor_budgets_project` (`project_id`),
  KEY `idx_labor_budgets_user` (`user_id`),
  KEY `idx_labor_budgets_creator` (`created_by`),
  CONSTRAINT `fk_labor_budgets_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_labor_budgets_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_labor_budgets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `labor_budgets`
--

LOCK TABLES `labor_budgets` WRITE;
/*!40000 ALTER TABLE `labor_budgets` DISABLE KEYS */;
INSERT INTO `labor_budgets` VALUES (3,'DESIGN AND BUILD OF FIRE PROTECTION SYSTEM labor',10,NULL,'Engineer x1, Architect x1, Foreman x4, Skilled Workers x5, Construction Helpers x7',2172860.00,13250.00,'2026-10-31','2026-11-30','','active',12,'2026-10-02 14:20:23','2026-10-02 14:24:01');
/*!40000 ALTER TABLE `labor_budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `material_requests`
--

DROP TABLE IF EXISTS `material_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `material_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `request_date` date NOT NULL,
  `status` enum('pending','approved','rejected','partial') NOT NULL DEFAULT 'pending',
  `flow_status` enum('procurement','accounting','warehouse','released','complete') NOT NULL DEFAULT 'procurement',
  `requested_by` bigint(20) unsigned NOT NULL,
  `notes` text DEFAULT NULL,
  `released_quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `released_by` bigint(20) unsigned DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `purchase_request_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_order_item_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mr_po_item` (`purchase_order_item_id`),
  KEY `idx_mr_project` (`project_id`),
  KEY `idx_mr_material` (`material_id`),
  KEY `fk_mr_requested_by` (`requested_by`),
  KEY `fk_mr_released_by` (`released_by`),
  CONSTRAINT `fk_mr_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mr_released_by` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_mr_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `material_requests`
--

LOCK TABLES `material_requests` WRITE;
/*!40000 ALTER TABLE `material_requests` DISABLE KEYS */;
INSERT INTO `material_requests` VALUES (1,10,2,3,'2026-10-02','approved','released',17,'Request ni Engineering PR-202610-0002, na-order at natanggap sa PO-202610-0005.',3,10,'2026-10-02 23:02:59','2026-10-02 15:00:37','2026-10-07 10:50:14',2,15),(2,10,2,3,'2026-10-02','approved','released',17,'Request ni Engineering PR-202610-0002, na-order at natanggap sa PO-202610-0005.',3,10,'2026-10-02 23:02:59','2026-10-02 15:00:37','2026-10-07 10:50:14',2,16);
/*!40000 ALTER TABLE `material_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `materials`
--

DROP TABLE IF EXISTS `materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `cost_per_unit` decimal(15,2) NOT NULL DEFAULT 0.00,
  `min_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `max_stock` int(10) unsigned NOT NULL DEFAULT 0,
  `current_stock` int(11) NOT NULL DEFAULT 0,
  `supplier` varchar(200) DEFAULT NULL,
  `supplier_id` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_materials_code` (`material_code`),
  KEY `idx_materials_supplier` (`supplier_id`),
  KEY `idx_materials_creator` (`created_by`),
  CONSTRAINT `fk_materials_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_materials_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `materials`
--

LOCK TABLES `materials` WRITE;
/*!40000 ALTER TABLE `materials` DISABLE KEYS */;
INSERT INTO `materials` VALUES (1,'MAT-0001','motor','','vehicle','nmax',50000.00,1,1,1,'motortrade',NULL,'active',1,'2026-09-19 04:50:41','2026-09-25 15:53:53'),(2,'MAT-4048E9','100mmØ',NULL,NULL,'no/s',18000.00,0,0,12,NULL,NULL,'active',17,'2026-10-02 12:42:46','2026-10-02 15:02:59'),(3,'MAT-208347','Pendent Sprinkler Heads (Viking)',NULL,NULL,'no/s',750.00,0,0,400,NULL,NULL,'active',17,'2026-10-02 12:42:46','2026-10-02 13:06:18'),(4,'MAT-8A6890','150mmØ',NULL,NULL,'no/s',44400.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(5,'MAT-CBF54A','40mmØ',NULL,NULL,'no/s',2000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(6,'MAT-68B370','50mmØ',NULL,NULL,'no/s',5700.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(7,'MAT-37B1A7','As-Built Drawings & Other Documents',NULL,NULL,'lot',35000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(8,'MAT-594C67','B.I Fittings',NULL,NULL,'lot',650000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(9,'MAT-01A5E4','B.I Flange, ø40mm',NULL,NULL,'no/s',353.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(10,'MAT-1DC5CE','B.I Union Patente, 25mmØ',NULL,NULL,'no/s',1200.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(11,'MAT-B57FF2','Ball Valve, 25mmØ',NULL,NULL,'no/s',4500.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(12,'MAT-DE6904','Ball Valve, 25Ø',NULL,NULL,'no/s',3500.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(13,'MAT-77A1C0','Blind Flange, 150mmØ',NULL,NULL,'no/s',2850.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(14,'MAT-290C64','Bonds & Insurances',NULL,NULL,'lot',130000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(15,'MAT-E2500D','Builders Work & Excavation',NULL,NULL,'lot',600000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(16,'MAT-E02657','Check Valve, 150Ø',NULL,NULL,'no/s',37000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(17,'MAT-BBCCFA','Control Wiring (Only from Fire Pump Panel to Pump)',NULL,NULL,'lot',610000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(18,'MAT-B9821C','Equipment Shiffing Fee, Hauling and Handling of Materials',NULL,NULL,'lot',210000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(19,'MAT-E79CFD','FDC with Cap & Chain, 150x65x65x65',NULL,NULL,'no/s',50000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(20,'MAT-7C1BAD','Fire Hose Cabinet',NULL,NULL,'set/s',35000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(21,'MAT-D061A3','Fire Hose Cabinet & Hydrant',NULL,NULL,'set/s',86500.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(22,'MAT-494611','Fire Hose Valve with Cap & Chain, 65mmØ',NULL,NULL,'no/s',10500.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(23,'MAT-40FAB0','FM 200',NULL,NULL,'lot',1650000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(24,'MAT-38226B','HDPE Flange, ø65mm',NULL,NULL,'no/s',352.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(25,'MAT-7DE6BD','Health & Safety Requirement & PPE\'s',NULL,NULL,'lot',90000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(26,'MAT-4DF7F4','High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)',NULL,NULL,'no/s',125.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(27,'MAT-E25FB2','Hydro Test',NULL,NULL,'lot',55000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(28,'MAT-336B46','Inspector Test Valve, 25mmØ',NULL,NULL,'no/s',4500.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(29,'MAT-00F011','Miscellaneous & Consumables',NULL,NULL,'lot',450000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(30,'MAT-C8DB7A','Mobilization / Demobilization',NULL,NULL,'lot',140000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(31,'MAT-DD8ECC','MVAC Design Calculation, Drafting & Coordination Fee',NULL,NULL,'lot',250000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(32,'MAT-FACDE7','Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.',NULL,NULL,'no/s',900.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(33,'MAT-32E2A2','Painting Works',NULL,NULL,'lot',40000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(34,'MAT-8513EB','Permits/Clearances',NULL,NULL,'lot',0.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(35,'MAT-1E9D72','Pipe Supports & Hangers',NULL,NULL,'lot',200000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(36,'MAT-BEDE49','Portable Fire Extinguisher',NULL,NULL,'set/s',2000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(37,'MAT-F1ADD5','Power & Water Consumption',NULL,NULL,'lot',90000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(38,'MAT-ECE6C6','Pressure Gauge',NULL,NULL,'no/s',2800.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(39,'MAT-E04DCB','Pump Mounting Accessories',NULL,NULL,'lot',25000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(40,'MAT-1AEC17','Pump Test, 150x65x65x65',NULL,NULL,'no/s',16000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(41,'MAT-5FD5B0','Rigid Plate pipe support with anchor bolt on concerete pad',NULL,NULL,'lot',5250.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(42,'MAT-7C2BDD','Scaffoldings & Platforms',NULL,NULL,'lot',55000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(43,'MAT-27C1F7','Sensing Line Assembly',NULL,NULL,'set/s',21000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(44,'MAT-05EB7A','Sight Glass',NULL,NULL,'no/s',5000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(45,'MAT-F741B4','Sight Glass, 25mmØ',NULL,NULL,'no/s',1800.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(46,'MAT-08774B','Site Expenses, Accomodation & Temfacil',NULL,NULL,'lot',230000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(47,'MAT-7E24ED','Site Supervision & Management',NULL,NULL,'lot',550000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(48,'MAT-4B18F4','Slip-On Flange, 100mmØ',NULL,NULL,'no/s',1950.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(49,'MAT-86704A','Slip-On Flange, 150mmØ',NULL,NULL,'no/s',2200.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(50,'MAT-A08092','Slip-On Flange, 200mmØ',NULL,NULL,'no/s',3500.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(51,'MAT-5A5887','Supervisory Switch',NULL,NULL,'no/s',11000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(52,'MAT-368144','Testing / Commissioning',NULL,NULL,'lot',275000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(53,'MAT-8F761E','Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.',NULL,NULL,'lot',950000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(54,'MAT-F895D9','Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.',NULL,NULL,'no/s',3125000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(55,'MAT-1052D4','Wall Chipping, Pipe Sleeving, Grouting & Sealing',NULL,NULL,'lot',45000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(56,'MAT-147279','Waste & Debris Disposal',NULL,NULL,'lot',55000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(57,'MAT-2F9E91','ø100mm',NULL,NULL,'m',4000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(58,'MAT-390F64','ø100mm x ø150mm',NULL,NULL,'no/s',3300.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(59,'MAT-4284FD','ø100mm x ø65mm',NULL,NULL,'no/s',2100.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(60,'MAT-03EC25','ø150mm',NULL,NULL,'m',5500.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(61,'MAT-A4A380','ø150mm x ø100mm',NULL,NULL,'no/s',4700.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(62,'MAT-A88D7D','ø25mm',NULL,NULL,'m',750.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(63,'MAT-DA5E88','ø32mm',NULL,NULL,'m',650.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(64,'MAT-F5419F','ø32mm x ø50mm',NULL,NULL,'no/s',1000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(65,'MAT-7BC8BA','ø40mm',NULL,NULL,'m',800.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(66,'MAT-A92CEF','ø50mm',NULL,NULL,'m',1000.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(67,'MAT-A899A6','ø65mm',NULL,NULL,'m',850.00,0,0,0,NULL,NULL,'active',1,'2026-10-03 01:46:32',NULL),(68,'MAT-F346B6','Vertical Turbine Diesel Engine Fire Pump, 750GPM, 100PSI, 125HP, 220V complete with Fire Pump Controller & Accessories such as Fuel Tank 130 Gallons.','','','no/s',4525000.00,5,4,4,'',NULL,'active',17,'2026-10-07 11:04:11','2026-10-07 11:39:30');
/*!40000 ALTER TABLE `materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_number` varchar(50) NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_number` (`payment_number`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_creator` (`created_by`),
  CONSTRAINT `fk_payments_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll`
--

DROP TABLE IF EXISTS `payroll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `payroll_number` varchar(50) NOT NULL,
  `employee_name` varchar(200) NOT NULL,
  `employee_position` varchar(150) DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `basic_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_pay` decimal(15,2) NOT NULL DEFAULT 0.00,
  `status` enum('draft','approved','paid') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payroll_number` (`payroll_number`),
  KEY `idx_payroll_user` (`user_id`),
  KEY `idx_payroll_creator` (`created_by`),
  CONSTRAINT `fk_payroll_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payroll_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll`
--

LOCK TABLES `payroll` WRITE;
/*!40000 ALTER TABLE `payroll` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_financials`
--

DROP TABLE IF EXISTS `project_financials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_financials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `contract_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `additional_budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `recorded_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_financials_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_financials`
--

LOCK TABLES `project_financials` WRITE;
/*!40000 ALTER TABLE `project_financials` DISABLE KEYS */;
/*!40000 ALTER TABLE `project_financials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `project_quotation_items`
--

DROP TABLE IF EXISTS `project_quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `project_quotation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(50) DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `line_type` enum('material','labor','other') NOT NULL DEFAULT 'other',
  PRIMARY KEY (`id`),
  KEY `idx_pqi_project` (`project_id`)
) ENGINE=InnoDB AUTO_INCREMENT=846 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `project_quotation_items`
--

LOCK TABLES `project_quotation_items` WRITE;
/*!40000 ALTER TABLE `project_quotation_items` DISABLE KEYS */;
INSERT INTO `project_quotation_items` VALUES (203,10,'Mobilization / Demobilization','GENERAL REQUIREMENTS',2.00,'lot',140000.00,'other'),(204,10,'Permits/Clearances','GENERAL REQUIREMENTS',2.00,'lot',0.00,'other'),(205,10,'Power & Water Consumption','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(206,10,'Bonds & Insurances','GENERAL REQUIREMENTS',2.00,'lot',130000.00,'other'),(207,10,'Site Expenses, Accomodation & Temfacil','GENERAL REQUIREMENTS',2.00,'lot',230000.00,'other'),(208,10,'Health & Safety Requirement & PPE\'s','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(209,10,'Waste & Debris Disposal','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(210,10,'Equipment Shiffing Fee, Hauling and Handling of Materials','GENERAL REQUIREMENTS',2.00,'lot',210000.00,'other'),(211,10,'Scaffoldings & Platforms','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(212,10,'Site Supervision & Management','GENERAL REQUIREMENTS',2.00,'lot',550000.00,'other'),(213,10,'MVAC Design Calculation, Drafting & Coordination Fee','GENERAL REQUIREMENTS',2.00,'lot',250000.00,'other'),(214,10,'As-Built Drawings & Other Documents','GENERAL REQUIREMENTS',2.00,'lot',35000.00,'other'),(215,10,'Vertical Turbine Diesel Engine Fire Pump, 750GPM, 100PSI, 125HP, 220V complete with Fire Pump Controller & Accessories such as Fuel Tank 130 Gallons.','FIRE PROTECTION WORKS — Fire Pump No. 1 (UL/FM) - DIESEL',2.00,'no/s',4525000.00,'other'),(216,10,'Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.','FIRE PROTECTION WORKS — Fire Pump No. 2 (UL/FM) - ELECTRIC',2.00,'no/s',3125000.00,'other'),(217,10,'Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.','FIRE PROTECTION WORKS — Jockey Pump (3 no/s)',1.00,'lot',950000.00,'other'),(218,10,'Pump Mounting Accessories','FIRE PROTECTION WORKS — Jockey Pump',3.00,'lot',25000.00,'other'),(219,10,'Control Wiring (Only from Fire Pump Panel to Pump)','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',610000.00,'other'),(220,10,'Testing / Commissioning','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',275000.00,'other'),(221,10,'ø150mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',35.00,'m',5500.00,'other'),(222,10,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',15.00,'m',4000.00,'other'),(223,10,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',8.00,'m',1000.00,'other'),(224,10,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',20.00,'no/s',2350.00,'other'),(225,10,'ø100mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',2250.00,'other'),(226,10,'ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',1000.00,'other'),(227,10,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Equal Tee',15.00,'no/s',3800.00,'other'),(228,10,'ø100mm x ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',3300.00,'other'),(229,10,'ø32mm x ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',1000.00,'other'),(230,10,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',800.00,'m',3000.00,'other'),(231,10,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',500.00,'m',1500.00,'other'),(232,10,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',70.00,'m',850.00,'other'),(233,10,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',15.00,'no/s',3700.00,'other'),(234,10,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',22.00,'no/s',1600.00,'other'),(235,10,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',55.00,'no/s',850.00,'other'),(236,10,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',12.00,'no/s',2700.00,'other'),(237,10,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',15.00,'no/s',1600.00,'other'),(238,10,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',13.00,'no/s',800.00,'other'),(239,10,'ø150mm x ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',4700.00,'other'),(240,10,'ø100mm x ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',28.00,'no/s',2100.00,'other'),(241,10,'HDPE Flange, ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',352.00,'other'),(242,10,'B.I Flange, ø40mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',25.00,'no/s',353.00,'other'),(243,10,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',125.00,'m',3600.00,'other'),(244,10,'ø65mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',25.00,'m',1600.00,'other'),(245,10,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',30.00,'m',1100.00,'other'),(246,10,'ø40mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',105.00,'m',800.00,'other'),(247,10,'ø32mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',115.00,'m',650.00,'other'),(248,10,'ø25mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',220.00,'m',750.00,'other'),(249,10,'B.I Fittings','FIRE PROTECTION WORKS — B.I Fittings (MECH)',3.00,'lot',650000.00,'other'),(250,10,'Pendent Sprinkler Heads (Viking)','FIRE PROTECTION WORKS — Sprinkler Heads',400.00,'no/s',750.00,'other'),(251,10,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — OS&Y Gate Valve',10.00,'no/s',44400.00,'other'),(252,10,'100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',18000.00,'other'),(253,10,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',5700.00,'other'),(254,10,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',3.00,'no/s',40000.00,'other'),(255,10,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',2.00,'no/s',6700.00,'other'),(256,10,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Alarm Check Valve',3.00,'no/s',96000.00,'other'),(257,10,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Wye Strainer',3.00,'no/s',6700.00,'other'),(258,10,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Water Flow Meter',3.00,'no/s',5000.00,'other'),(259,10,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Pressure Reducing Valve',3.00,'no/s',5000.00,'other'),(260,10,'40mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Air Release Valve',3.00,'no/s',2000.00,'other'),(261,10,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Waste Cone',3.00,'no/s',5000.00,'other'),(262,10,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',5.00,'no/s',3250.00,'other'),(263,10,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',3.00,'no/s',2250.00,'other'),(264,10,'Supervisory Switch','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',11000.00,'other'),(265,10,'Pressure Gauge','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',15.00,'no/s',2800.00,'other'),(266,10,'Sensing Line Assembly','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'set/s',21000.00,'other'),(267,10,'Sight Glass','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',5000.00,'other'),(268,10,'Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',10.00,'no/s',900.00,'other'),(269,10,'Slip-On Flange, 200mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',5.00,'no/s',3500.00,'other'),(270,10,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',50.00,'no/s',2200.00,'other'),(271,10,'Slip-On Flange, 100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',20.00,'no/s',1950.00,'other'),(272,10,'Blind Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',4.00,'no/s',2850.00,'other'),(273,10,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',400.00,'no/s',125.00,'other'),(274,10,'Rigid Plate pipe support with anchor bolt on concerete pad','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',2.00,'lot',5250.00,'other'),(275,10,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Supervisory Valve',3.00,'no/s',44000.00,'other'),(276,10,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Water Flow Detector',3.00,'no/s',21000.00,'other'),(277,10,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',37000.00,'other'),(278,10,'Supervisory Switch','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',11000.00,'other'),(279,10,'Pressure Gauge','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',2800.00,'other'),(280,10,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',9.00,'no/s',2200.00,'other'),(281,10,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',65.00,'no/s',125.00,'other'),(282,10,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',1800.00,'other'),(283,10,'Inspector Test Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(284,10,'Ball Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(285,10,'B.I Union Patente, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',3.00,'no/s',1200.00,'other'),(286,10,'FDC with Cap & Chain, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',50000.00,'other'),(287,10,'Check Valve, 150Ø','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',37000.00,'other'),(288,10,'Pump Test, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',16000.00,'other'),(289,10,'Fire Hose Valve with Cap & Chain, 65mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',10500.00,'other'),(290,10,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',12.00,'no/s',1750.00,'other'),(291,10,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Main Pipe Risers',22.00,'no/s',85.00,'other'),(292,10,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',2000.00,'other'),(293,10,'Ball Valve, 25Ø','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',3500.00,'other'),(294,10,'Fire Hose Cabinet','FIRE PROTECTION WORKS\nmade of Ga.# 18 galvanized iron steel painted (interior and exterior body) with one coat of red lead primer before painting with 2 coat of fire engine red color\n1/8\" thk. Clear Glass\n30m x 40Ø fire hose, double jacketed\n40Ø angle valve, FHV, UL/FM\n40Ø adjustable pistol type (tight grip nozzle)\nSemi-automatic hose rack\nPiano Hinge\nBrass nipple, adaptor and coupling\n9 lbs. Fire Extinguisher, dry chemical, class ABC',15.00,'set/s',35000.00,'other'),(295,10,'Fire Hose Cabinet & Hydrant','FIRE PROTECTION WORKS',35.00,'set/s',86500.00,'other'),(296,10,'Portable Fire Extinguisher','FIRE PROTECTION WORKS\n10 lbs. FE Dry Chemical, class ABC',35.00,'set/s',2000.00,'other'),(297,10,'FM 200','FIRE PROTECTION WORKS — FIRE SUPPRESSION SYSTEM (EE ROOM)',3.00,'lot',1650000.00,'other'),(298,10,'Builders Work & Excavation','OTHER ITEMS',3.00,'lot',600000.00,'other'),(299,10,'Wall Chipping, Pipe Sleeving, Grouting & Sealing','OTHER ITEMS',3.00,'lot',45000.00,'other'),(300,10,'Pipe Supports & Hangers','OTHER ITEMS',3.00,'lot',200000.00,'other'),(301,10,'Painting Works','OTHER ITEMS',3.00,'lot',40000.00,'other'),(302,10,'Hydro Test','OTHER ITEMS',3.00,'lot',55000.00,'other'),(303,10,'Miscellaneous & Consumables','OTHER ITEMS',3.00,'lot',450000.00,'other'),(304,11,'Mobilization / Demobilization','GENERAL REQUIREMENTS',2.00,'lot',140000.00,'other'),(305,11,'Permits/Clearances','GENERAL REQUIREMENTS',2.00,'lot',0.00,'other'),(306,11,'Power & Water Consumption','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(307,11,'Bonds & Insurances','GENERAL REQUIREMENTS',2.00,'lot',130000.00,'other'),(308,11,'Site Expenses, Accomodation & Temfacil','GENERAL REQUIREMENTS',2.00,'lot',230000.00,'other'),(309,11,'Health & Safety Requirement & PPE\'s','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(310,11,'Waste & Debris Disposal','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(311,11,'Equipment Shiffing Fee, Hauling and Handling of Materials','GENERAL REQUIREMENTS',2.00,'lot',210000.00,'other'),(312,11,'Scaffoldings & Platforms','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(313,11,'Site Supervision & Management','GENERAL REQUIREMENTS',2.00,'lot',550000.00,'other'),(314,11,'MVAC Design Calculation, Drafting & Coordination Fee','GENERAL REQUIREMENTS',2.00,'lot',250000.00,'other'),(315,11,'As-Built Drawings & Other Documents','GENERAL REQUIREMENTS',2.00,'lot',35000.00,'other'),(316,11,'Vertical Turbine Diesel Engine Fire Pump, 750GPM, 100PSI, 125HP, 220V complete with Fire Pump Controller & Accessories such as Fuel Tank 130 Gallons.','FIRE PROTECTION WORKS — Fire Pump No. 1 (UL/FM) - DIESEL',2.00,'no/s',4525000.00,'other'),(317,11,'Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.','FIRE PROTECTION WORKS — Fire Pump No. 2 (UL/FM) - ELECTRIC',2.00,'no/s',3125000.00,'other'),(318,11,'Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.','FIRE PROTECTION WORKS — Jockey Pump (3 no/s)',1.00,'lot',950000.00,'other'),(319,11,'Pump Mounting Accessories','FIRE PROTECTION WORKS — Jockey Pump',3.00,'lot',25000.00,'other'),(320,11,'Control Wiring (Only from Fire Pump Panel to Pump)','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',610000.00,'other'),(321,11,'Testing / Commissioning','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',275000.00,'other'),(322,11,'ø150mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',35.00,'m',5500.00,'other'),(323,11,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',15.00,'m',4000.00,'other'),(324,11,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',8.00,'m',1000.00,'other'),(325,11,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',20.00,'no/s',2350.00,'other'),(326,11,'ø100mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',2250.00,'other'),(327,11,'ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',1000.00,'other'),(328,11,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Equal Tee',15.00,'no/s',3800.00,'other'),(329,11,'ø100mm x ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',3300.00,'other'),(330,11,'ø32mm x ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',1000.00,'other'),(331,11,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',800.00,'m',3000.00,'other'),(332,11,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',500.00,'m',1500.00,'other'),(333,11,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',70.00,'m',850.00,'other'),(334,11,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',15.00,'no/s',3700.00,'other'),(335,11,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',22.00,'no/s',1600.00,'other'),(336,11,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',55.00,'no/s',850.00,'other'),(337,11,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',12.00,'no/s',2700.00,'other'),(338,11,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',15.00,'no/s',1600.00,'other'),(339,11,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',13.00,'no/s',800.00,'other'),(340,11,'ø150mm x ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',4700.00,'other'),(341,11,'ø100mm x ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',28.00,'no/s',2100.00,'other'),(342,11,'HDPE Flange, ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',352.00,'other'),(343,11,'B.I Flange, ø40mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',25.00,'no/s',353.00,'other'),(344,11,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',125.00,'m',3600.00,'other'),(345,11,'ø65mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',25.00,'m',1600.00,'other'),(346,11,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',30.00,'m',1100.00,'other'),(347,11,'ø40mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',105.00,'m',800.00,'other'),(348,11,'ø32mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',115.00,'m',650.00,'other'),(349,11,'ø25mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',220.00,'m',750.00,'other'),(350,11,'B.I Fittings','FIRE PROTECTION WORKS — B.I Fittings (MECH)',3.00,'lot',650000.00,'other'),(351,11,'Pendent Sprinkler Heads (Viking)','FIRE PROTECTION WORKS — Sprinkler Heads',400.00,'no/s',750.00,'other'),(352,11,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — OS&Y Gate Valve',10.00,'no/s',44400.00,'other'),(353,11,'100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',18000.00,'other'),(354,11,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',5700.00,'other'),(355,11,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',3.00,'no/s',40000.00,'other'),(356,11,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',2.00,'no/s',6700.00,'other'),(357,11,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Alarm Check Valve',3.00,'no/s',96000.00,'other'),(358,11,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Wye Strainer',3.00,'no/s',6700.00,'other'),(359,11,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Water Flow Meter',3.00,'no/s',5000.00,'other'),(360,11,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Pressure Reducing Valve',3.00,'no/s',5000.00,'other'),(361,11,'40mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Air Release Valve',3.00,'no/s',2000.00,'other'),(362,11,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Waste Cone',3.00,'no/s',5000.00,'other'),(363,11,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',5.00,'no/s',3250.00,'other'),(364,11,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',3.00,'no/s',2250.00,'other'),(365,11,'Supervisory Switch','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',11000.00,'other'),(366,11,'Pressure Gauge','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',15.00,'no/s',2800.00,'other'),(367,11,'Sensing Line Assembly','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'set/s',21000.00,'other'),(368,11,'Sight Glass','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',5000.00,'other'),(369,11,'Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',10.00,'no/s',900.00,'other'),(370,11,'Slip-On Flange, 200mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',5.00,'no/s',3500.00,'other'),(371,11,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',50.00,'no/s',2200.00,'other'),(372,11,'Slip-On Flange, 100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',20.00,'no/s',1950.00,'other'),(373,11,'Blind Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',4.00,'no/s',2850.00,'other'),(374,11,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',400.00,'no/s',125.00,'other'),(375,11,'Rigid Plate pipe support with anchor bolt on concerete pad','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',2.00,'lot',5250.00,'other'),(376,11,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Supervisory Valve',3.00,'no/s',44000.00,'other'),(377,11,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Water Flow Detector',3.00,'no/s',21000.00,'other'),(378,11,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',37000.00,'other'),(379,11,'Supervisory Switch','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',11000.00,'other'),(380,11,'Pressure Gauge','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',2800.00,'other'),(381,11,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',9.00,'no/s',2200.00,'other'),(382,11,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',65.00,'no/s',125.00,'other'),(383,11,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',1800.00,'other'),(384,11,'Inspector Test Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(385,11,'Ball Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(386,11,'B.I Union Patente, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',3.00,'no/s',1200.00,'other'),(387,11,'FDC with Cap & Chain, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',50000.00,'other'),(388,11,'Check Valve, 150Ø','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',37000.00,'other'),(389,11,'Pump Test, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',16000.00,'other'),(390,11,'Fire Hose Valve with Cap & Chain, 65mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',10500.00,'other'),(391,11,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',12.00,'no/s',1750.00,'other'),(392,11,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Main Pipe Risers',22.00,'no/s',85.00,'other'),(393,11,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',2000.00,'other'),(394,11,'Ball Valve, 25Ø','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',3500.00,'other'),(395,11,'Fire Hose Cabinet','FIRE PROTECTION WORKS\nmade of Ga.# 18 galvanized iron steel painted (interior and exterior body) with one coat of red lead primer before painting with 2 coat of fire engine red color\n1/8\" thk. Clear Glass\n30m x 40Ø fire hose, double jacketed\n40Ø angle valve, FHV, UL/FM\n40Ø adjustable pistol type (tight grip nozzle)\nSemi-automatic hose rack\nPiano Hinge\nBrass nipple, adaptor and coupling\n9 lbs. Fire Extinguisher, dry chemical, class ABC',15.00,'set/s',35000.00,'other'),(396,11,'Fire Hose Cabinet & Hydrant','FIRE PROTECTION WORKS',35.00,'set/s',86500.00,'other'),(397,11,'Portable Fire Extinguisher','FIRE PROTECTION WORKS\n10 lbs. FE Dry Chemical, class ABC',35.00,'set/s',2000.00,'other'),(398,11,'FM 200','FIRE PROTECTION WORKS — FIRE SUPPRESSION SYSTEM (EE ROOM)',3.00,'lot',1650000.00,'other'),(399,11,'Builders Work & Excavation','OTHER ITEMS',3.00,'lot',600000.00,'other'),(400,11,'Wall Chipping, Pipe Sleeving, Grouting & Sealing','OTHER ITEMS',3.00,'lot',45000.00,'other'),(401,11,'Pipe Supports & Hangers','OTHER ITEMS',3.00,'lot',200000.00,'other'),(402,11,'Painting Works','OTHER ITEMS',3.00,'lot',40000.00,'other'),(403,11,'Hydro Test','OTHER ITEMS',3.00,'lot',55000.00,'other'),(404,11,'Miscellaneous & Consumables','OTHER ITEMS',3.00,'lot',450000.00,'other'),(405,12,'Mobilization / Demobilization','GENERAL REQUIREMENTS',2.00,'lot',140000.00,'other'),(406,12,'Permits/Clearances','GENERAL REQUIREMENTS',2.00,'lot',0.00,'other'),(407,12,'Power & Water Consumption','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(408,12,'Bonds & Insurances','GENERAL REQUIREMENTS',2.00,'lot',130000.00,'other'),(409,12,'Site Expenses, Accomodation & Temfacil','GENERAL REQUIREMENTS',2.00,'lot',230000.00,'other'),(410,12,'Health & Safety Requirement & PPE\'s','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(411,12,'Waste & Debris Disposal','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(412,12,'Equipment Shiffing Fee, Hauling and Handling of Materials','GENERAL REQUIREMENTS',2.00,'lot',210000.00,'other'),(413,12,'Scaffoldings & Platforms','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(414,12,'Site Supervision & Management','GENERAL REQUIREMENTS',2.00,'lot',550000.00,'other'),(415,12,'MVAC Design Calculation, Drafting & Coordination Fee','GENERAL REQUIREMENTS',2.00,'lot',250000.00,'other'),(416,12,'As-Built Drawings & Other Documents','GENERAL REQUIREMENTS',2.00,'lot',35000.00,'other'),(417,12,'Vertical Turbine Diesel Engine Fire Pump, 750GPM, 100PSI, 125HP, 220V complete with Fire Pump Controller & Accessories such as Fuel Tank 130 Gallons.','FIRE PROTECTION WORKS — Fire Pump No. 1 (UL/FM) - DIESEL',2.00,'no/s',4525000.00,'other'),(418,12,'Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.','FIRE PROTECTION WORKS — Fire Pump No. 2 (UL/FM) - ELECTRIC',2.00,'no/s',3125000.00,'other'),(419,12,'Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.','FIRE PROTECTION WORKS — Jockey Pump (3 no/s)',1.00,'lot',950000.00,'other'),(420,12,'Pump Mounting Accessories','FIRE PROTECTION WORKS — Jockey Pump',3.00,'lot',25000.00,'other'),(421,12,'Control Wiring (Only from Fire Pump Panel to Pump)','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',610000.00,'other'),(422,12,'Testing / Commissioning','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',275000.00,'other'),(423,12,'ø150mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',35.00,'m',5500.00,'other'),(424,12,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',15.00,'m',4000.00,'other'),(425,12,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',8.00,'m',1000.00,'other'),(426,12,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',20.00,'no/s',2350.00,'other'),(427,12,'ø100mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',2250.00,'other'),(428,12,'ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',1000.00,'other'),(429,12,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Equal Tee',15.00,'no/s',3800.00,'other'),(430,12,'ø100mm x ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',3300.00,'other'),(431,12,'ø32mm x ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',1000.00,'other'),(432,12,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',800.00,'m',3000.00,'other'),(433,12,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',500.00,'m',1500.00,'other'),(434,12,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',70.00,'m',850.00,'other'),(435,12,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',15.00,'no/s',3700.00,'other'),(436,12,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',22.00,'no/s',1600.00,'other'),(437,12,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',55.00,'no/s',850.00,'other'),(438,12,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',12.00,'no/s',2700.00,'other'),(439,12,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',15.00,'no/s',1600.00,'other'),(440,12,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',13.00,'no/s',800.00,'other'),(441,12,'ø150mm x ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',4700.00,'other'),(442,12,'ø100mm x ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',28.00,'no/s',2100.00,'other'),(443,12,'HDPE Flange, ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',352.00,'other'),(444,12,'B.I Flange, ø40mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',25.00,'no/s',353.00,'other'),(445,12,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',125.00,'m',3600.00,'other'),(446,12,'ø65mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',25.00,'m',1600.00,'other'),(447,12,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',30.00,'m',1100.00,'other'),(448,12,'ø40mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',105.00,'m',800.00,'other'),(449,12,'ø32mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',115.00,'m',650.00,'other'),(450,12,'ø25mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',220.00,'m',750.00,'other'),(451,12,'B.I Fittings','FIRE PROTECTION WORKS — B.I Fittings (MECH)',3.00,'lot',650000.00,'other'),(452,12,'Pendent Sprinkler Heads (Viking)','FIRE PROTECTION WORKS — Sprinkler Heads',400.00,'no/s',750.00,'other'),(453,12,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — OS&Y Gate Valve',10.00,'no/s',44400.00,'other'),(454,12,'100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',18000.00,'other'),(455,12,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',5700.00,'other'),(456,12,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',3.00,'no/s',40000.00,'other'),(457,12,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',2.00,'no/s',6700.00,'other'),(458,12,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Alarm Check Valve',3.00,'no/s',96000.00,'other'),(459,12,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Wye Strainer',3.00,'no/s',6700.00,'other'),(460,12,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Water Flow Meter',3.00,'no/s',5000.00,'other'),(461,12,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Pressure Reducing Valve',3.00,'no/s',5000.00,'other'),(462,12,'40mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Air Release Valve',3.00,'no/s',2000.00,'other'),(463,12,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Waste Cone',3.00,'no/s',5000.00,'other'),(464,12,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',5.00,'no/s',3250.00,'other'),(465,12,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',3.00,'no/s',2250.00,'other'),(466,12,'Supervisory Switch','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',11000.00,'other'),(467,12,'Pressure Gauge','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',15.00,'no/s',2800.00,'other'),(468,12,'Sensing Line Assembly','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'set/s',21000.00,'other'),(469,12,'Sight Glass','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',5000.00,'other'),(470,12,'Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',10.00,'no/s',900.00,'other'),(471,12,'Slip-On Flange, 200mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',5.00,'no/s',3500.00,'other'),(472,12,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',50.00,'no/s',2200.00,'other'),(473,12,'Slip-On Flange, 100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',20.00,'no/s',1950.00,'other'),(474,12,'Blind Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',4.00,'no/s',2850.00,'other'),(475,12,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',400.00,'no/s',125.00,'other'),(476,12,'Rigid Plate pipe support with anchor bolt on concerete pad','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',2.00,'lot',5250.00,'other'),(477,12,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Supervisory Valve',3.00,'no/s',44000.00,'other'),(478,12,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Water Flow Detector',3.00,'no/s',21000.00,'other'),(479,12,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',37000.00,'other'),(480,12,'Supervisory Switch','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',11000.00,'other'),(481,12,'Pressure Gauge','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',2800.00,'other'),(482,12,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',9.00,'no/s',2200.00,'other'),(483,12,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',65.00,'no/s',125.00,'other'),(484,12,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',1800.00,'other'),(485,12,'Inspector Test Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(486,12,'Ball Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(487,12,'B.I Union Patente, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',3.00,'no/s',1200.00,'other'),(488,12,'FDC with Cap & Chain, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',50000.00,'other'),(489,12,'Check Valve, 150Ø','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',37000.00,'other'),(490,12,'Pump Test, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',16000.00,'other'),(491,12,'Fire Hose Valve with Cap & Chain, 65mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',10500.00,'other'),(492,12,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',12.00,'no/s',1750.00,'other'),(493,12,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Main Pipe Risers',22.00,'no/s',85.00,'other'),(494,12,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',2000.00,'other'),(495,12,'Ball Valve, 25Ø','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',3500.00,'other'),(496,12,'Fire Hose Cabinet','FIRE PROTECTION WORKS\nmade of Ga.# 18 galvanized iron steel painted (interior and exterior body) with one coat of red lead primer before painting with 2 coat of fire engine red color\n1/8\" thk. Clear Glass\n30m x 40Ø fire hose, double jacketed\n40Ø angle valve, FHV, UL/FM\n40Ø adjustable pistol type (tight grip nozzle)\nSemi-automatic hose rack\nPiano Hinge\nBrass nipple, adaptor and coupling\n9 lbs. Fire Extinguisher, dry chemical, class ABC',15.00,'set/s',35000.00,'other'),(497,12,'Fire Hose Cabinet & Hydrant','FIRE PROTECTION WORKS',35.00,'set/s',86500.00,'other'),(498,12,'Portable Fire Extinguisher','FIRE PROTECTION WORKS\n10 lbs. FE Dry Chemical, class ABC',35.00,'set/s',2000.00,'other'),(499,12,'FM 200','FIRE PROTECTION WORKS — FIRE SUPPRESSION SYSTEM (EE ROOM)',3.00,'lot',1650000.00,'other'),(500,12,'Builders Work & Excavation','OTHER ITEMS',3.00,'lot',600000.00,'other'),(501,12,'Wall Chipping, Pipe Sleeving, Grouting & Sealing','OTHER ITEMS',3.00,'lot',45000.00,'other'),(502,12,'Pipe Supports & Hangers','OTHER ITEMS',3.00,'lot',200000.00,'other'),(503,12,'Painting Works','OTHER ITEMS',3.00,'lot',40000.00,'other'),(504,12,'Hydro Test','OTHER ITEMS',3.00,'lot',55000.00,'other'),(505,12,'Miscellaneous & Consumables','OTHER ITEMS',3.00,'lot',450000.00,'other'),(506,13,'Mobilization / Demobilization','GENERAL REQUIREMENTS',2.00,'lot',140000.00,'other'),(507,13,'Permits/Clearances','GENERAL REQUIREMENTS',2.00,'lot',0.00,'other'),(508,13,'Power & Water Consumption','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(509,13,'Bonds & Insurances','GENERAL REQUIREMENTS',2.00,'lot',130000.00,'other'),(510,13,'Site Expenses, Accomodation & Temfacil','GENERAL REQUIREMENTS',2.00,'lot',230000.00,'other'),(511,13,'Health & Safety Requirement & PPE\'s','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(512,13,'Waste & Debris Disposal','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(513,13,'Equipment Shiffing Fee, Hauling and Handling of Materials','GENERAL REQUIREMENTS',2.00,'lot',210000.00,'other'),(514,13,'Scaffoldings & Platforms','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(515,13,'Site Supervision & Management','GENERAL REQUIREMENTS',2.00,'lot',550000.00,'other'),(516,13,'MVAC Design Calculation, Drafting & Coordination Fee','GENERAL REQUIREMENTS',2.00,'lot',250000.00,'other'),(517,13,'As-Built Drawings & Other Documents','GENERAL REQUIREMENTS',2.00,'lot',35000.00,'other'),(518,13,'Vertical Turbine Diesel Engine Fire Pump, 750GPM, 100PSI, 125HP, 220V complete with Fire Pump Controller & Accessories such as Fuel Tank 130 Gallons.','FIRE PROTECTION WORKS — Fire Pump No. 1 (UL/FM) - DIESEL',2.00,'no/s',4525000.00,'other'),(519,13,'Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.','FIRE PROTECTION WORKS — Fire Pump No. 2 (UL/FM) - ELECTRIC',2.00,'no/s',3125000.00,'other'),(520,13,'Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.','FIRE PROTECTION WORKS — Jockey Pump (3 no/s)',1.00,'lot',950000.00,'other'),(521,13,'Pump Mounting Accessories','FIRE PROTECTION WORKS — Jockey Pump',3.00,'lot',25000.00,'other'),(522,13,'Control Wiring (Only from Fire Pump Panel to Pump)','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',610000.00,'other'),(523,13,'Testing / Commissioning','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',275000.00,'other'),(524,13,'ø150mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',35.00,'m',5500.00,'other'),(525,13,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',15.00,'m',4000.00,'other'),(526,13,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',8.00,'m',1000.00,'other'),(527,13,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',20.00,'no/s',2350.00,'other'),(528,13,'ø100mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',2250.00,'other'),(529,13,'ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',1000.00,'other'),(530,13,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Equal Tee',15.00,'no/s',3800.00,'other'),(531,13,'ø100mm x ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',3300.00,'other'),(532,13,'ø32mm x ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',1000.00,'other'),(533,13,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',800.00,'m',3000.00,'other'),(534,13,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',500.00,'m',1500.00,'other'),(535,13,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',70.00,'m',850.00,'other'),(536,13,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',15.00,'no/s',3700.00,'other'),(537,13,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',22.00,'no/s',1600.00,'other'),(538,13,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',55.00,'no/s',850.00,'other'),(539,13,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',12.00,'no/s',2700.00,'other'),(540,13,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',15.00,'no/s',1600.00,'other'),(541,13,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',13.00,'no/s',800.00,'other'),(542,13,'ø150mm x ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',4700.00,'other'),(543,13,'ø100mm x ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',28.00,'no/s',2100.00,'other'),(544,13,'HDPE Flange, ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',352.00,'other'),(545,13,'B.I Flange, ø40mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',25.00,'no/s',353.00,'other'),(546,13,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',125.00,'m',3600.00,'other'),(547,13,'ø65mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',25.00,'m',1600.00,'other'),(548,13,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',30.00,'m',1100.00,'other'),(549,13,'ø40mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',105.00,'m',800.00,'other'),(550,13,'ø32mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',115.00,'m',650.00,'other'),(551,13,'ø25mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',220.00,'m',750.00,'other'),(552,13,'B.I Fittings','FIRE PROTECTION WORKS — B.I Fittings (MECH)',3.00,'lot',650000.00,'other'),(553,13,'Pendent Sprinkler Heads (Viking)','FIRE PROTECTION WORKS — Sprinkler Heads',400.00,'no/s',750.00,'other'),(554,13,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — OS&Y Gate Valve',10.00,'no/s',44400.00,'other'),(555,13,'100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',18000.00,'other'),(556,13,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',5700.00,'other'),(557,13,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',3.00,'no/s',40000.00,'other'),(558,13,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',2.00,'no/s',6700.00,'other'),(559,13,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Alarm Check Valve',3.00,'no/s',96000.00,'other'),(560,13,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Wye Strainer',3.00,'no/s',6700.00,'other'),(561,13,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Water Flow Meter',3.00,'no/s',5000.00,'other'),(562,13,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Pressure Reducing Valve',3.00,'no/s',5000.00,'other'),(563,13,'40mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Air Release Valve',3.00,'no/s',2000.00,'other'),(564,13,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Waste Cone',3.00,'no/s',5000.00,'other'),(565,13,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',5.00,'no/s',3250.00,'other'),(566,13,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',3.00,'no/s',2250.00,'other'),(567,13,'Supervisory Switch','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',11000.00,'other'),(568,13,'Pressure Gauge','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',15.00,'no/s',2800.00,'other'),(569,13,'Sensing Line Assembly','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'set/s',21000.00,'other'),(570,13,'Sight Glass','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',5000.00,'other'),(571,13,'Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',10.00,'no/s',900.00,'other'),(572,13,'Slip-On Flange, 200mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',5.00,'no/s',3500.00,'other'),(573,13,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',50.00,'no/s',2200.00,'other'),(574,13,'Slip-On Flange, 100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',20.00,'no/s',1950.00,'other'),(575,13,'Blind Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',4.00,'no/s',2850.00,'other'),(576,13,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',400.00,'no/s',125.00,'other'),(577,13,'Rigid Plate pipe support with anchor bolt on concerete pad','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',2.00,'lot',5250.00,'other'),(578,13,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Supervisory Valve',3.00,'no/s',44000.00,'other'),(579,13,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Water Flow Detector',3.00,'no/s',21000.00,'other'),(580,13,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',37000.00,'other'),(581,13,'Supervisory Switch','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',11000.00,'other'),(582,13,'Pressure Gauge','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',2800.00,'other'),(583,13,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',9.00,'no/s',2200.00,'other'),(584,13,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',65.00,'no/s',125.00,'other'),(585,13,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',1800.00,'other'),(586,13,'Inspector Test Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(587,13,'Ball Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(588,13,'B.I Union Patente, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',3.00,'no/s',1200.00,'other'),(589,13,'FDC with Cap & Chain, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',50000.00,'other'),(590,13,'Check Valve, 150Ø','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',37000.00,'other'),(591,13,'Pump Test, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',16000.00,'other'),(592,13,'Fire Hose Valve with Cap & Chain, 65mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',10500.00,'other'),(593,13,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',12.00,'no/s',1750.00,'other'),(594,13,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Main Pipe Risers',22.00,'no/s',85.00,'other'),(595,13,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',2000.00,'other'),(596,13,'Ball Valve, 25Ø','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',3500.00,'other'),(597,13,'Fire Hose Cabinet','FIRE PROTECTION WORKS\nmade of Ga.# 18 galvanized iron steel painted (interior and exterior body) with one coat of red lead primer before painting with 2 coat of fire engine red color\n1/8\" thk. Clear Glass\n30m x 40Ø fire hose, double jacketed\n40Ø angle valve, FHV, UL/FM\n40Ø adjustable pistol type (tight grip nozzle)\nSemi-automatic hose rack\nPiano Hinge\nBrass nipple, adaptor and coupling\n9 lbs. Fire Extinguisher, dry chemical, class ABC',15.00,'set/s',35000.00,'other'),(598,13,'Fire Hose Cabinet & Hydrant','FIRE PROTECTION WORKS',35.00,'set/s',86500.00,'other'),(599,13,'Portable Fire Extinguisher','FIRE PROTECTION WORKS\n10 lbs. FE Dry Chemical, class ABC',35.00,'set/s',2000.00,'other'),(600,13,'FM 200','FIRE PROTECTION WORKS — FIRE SUPPRESSION SYSTEM (EE ROOM)',3.00,'lot',1650000.00,'other'),(601,13,'Builders Work & Excavation','OTHER ITEMS',3.00,'lot',600000.00,'other'),(602,13,'Wall Chipping, Pipe Sleeving, Grouting & Sealing','OTHER ITEMS',3.00,'lot',45000.00,'other'),(603,13,'Pipe Supports & Hangers','OTHER ITEMS',3.00,'lot',200000.00,'other'),(604,13,'Painting Works','OTHER ITEMS',3.00,'lot',40000.00,'other'),(605,13,'Hydro Test','OTHER ITEMS',3.00,'lot',55000.00,'other'),(606,13,'Miscellaneous & Consumables','OTHER ITEMS',3.00,'lot',450000.00,'other'),(607,14,'Mobilization / Demobilization','GENERAL REQUIREMENTS',1.00,'lot',0.00,'other'),(608,14,'Site preparation, clearing and layout','GENERAL REQUIREMENTS',1.00,'lot',0.00,'other'),(609,14,'Temporary facilities, safety and protection','GENERAL REQUIREMENTS',1.00,'lot',0.00,'other'),(610,14,'Permits / Clearances','GENERAL REQUIREMENTS',1.00,'lot',0.00,'other'),(611,14,'Site supervision and management','GENERAL REQUIREMENTS',1.00,'lot',0.00,'other'),(612,14,'Waste and debris disposal','GENERAL REQUIREMENTS',1.00,'lot',0.00,'other'),(613,14,'Pool excavation and disposal of excess soil','EXCAVATION AND EARTHWORKS',45.00,'m³',0.00,'other'),(614,14,'Trenching for pool piping and drainage','EXCAVATION AND EARTHWORKS',30.00,'m',0.00,'other'),(615,14,'Backfilling and compaction','EXCAVATION AND EARTHWORKS',18.00,'m³',0.00,'other'),(616,14,'Lean concrete / blinding','STRUCTURAL / CONCRETE WORKS',5.00,'m³',0.00,'other'),(617,14,'Reinforced concrete pool base','STRUCTURAL / CONCRETE WORKS',10.00,'m³',0.00,'other'),(618,14,'Reinforced concrete pool walls','STRUCTURAL / CONCRETE WORKS',12.00,'m³',0.00,'other'),(619,14,'Reinforcement bars and tie wire','STRUCTURAL / CONCRETE WORKS',1800.00,'kg',0.00,'other'),(620,14,'Formworks and supports','STRUCTURAL / CONCRETE WORKS',110.00,'m²',0.00,'other'),(621,14,'Waterproofing / protective coating','STRUCTURAL / CONCRETE WORKS',110.00,'m²',0.00,'other'),(622,14,'Pool plaster / cement finish','POOL FINISHES',110.00,'m²',0.00,'other'),(623,14,'Pool tiles / mosaic finish','POOL FINISHES',95.00,'m²',0.00,'other'),(624,14,'Tile adhesive and grout','POOL FINISHES',95.00,'m²',0.00,'other'),(625,14,'Coping / pool edge finish','POOL FINISHES',32.00,'m',0.00,'other'),(626,14,'Pool circulation piping and fittings','PLUMBING AND FILTRATION SYSTEM',1.00,'lot',0.00,'other'),(627,14,'Main drain and fittings','PLUMBING AND FILTRATION SYSTEM',1.00,'set',0.00,'other'),(628,14,'Skimmer / suction fittings','PLUMBING AND FILTRATION SYSTEM',2.00,'set',0.00,'other'),(629,14,'Return inlets / wall fittings','PLUMBING AND FILTRATION SYSTEM',4.00,'set',0.00,'other'),(630,14,'Pool pump','PLUMBING AND FILTRATION SYSTEM',1.00,'set',0.00,'other'),(631,14,'Sand / cartridge filter','PLUMBING AND FILTRATION SYSTEM',1.00,'set',0.00,'other'),(632,14,'Valves and control fittings','PLUMBING AND FILTRATION SYSTEM',1.00,'lot',0.00,'other'),(633,14,'Pool pump electrical connection','ELECTRICAL WORKS',1.00,'lot',0.00,'other'),(634,14,'Conduits, wires and accessories','ELECTRICAL WORKS',1.00,'lot',0.00,'other'),(635,14,'Pool lighting fixtures and installation','ELECTRICAL WORKS',6.00,'set',0.00,'other'),(636,14,'Grounding / bonding system','ELECTRICAL WORKS',1.00,'lot',0.00,'other'),(637,14,'Deck / surrounding concrete works','DRAINAGE / SURROUNDING WORKS',55.00,'m²',0.00,'other'),(638,14,'Drainage channel / deck drain','DRAINAGE / SURROUNDING WORKS',25.00,'m',0.00,'other'),(639,14,'Final grading and site restoration','DRAINAGE / SURROUNDING WORKS',1.00,'lot',0.00,'other'),(640,14,'Pressure / leak testing of piping','TESTING, COMMISSIONING AND HANDOVER',1.00,'lot',0.00,'other'),(641,14,'System testing and commissioning','TESTING, COMMISSIONING AND HANDOVER',1.00,'lot',0.00,'other'),(642,14,'Cleaning, final inspection and turnover','TESTING, COMMISSIONING AND HANDOVER',1.00,'lot',0.00,'other'),(643,14,'OVERHEAD / CONTINGENCY (%)','TESTING, COMMISSIONING AND HANDOVER — DIRECT COST TOTAL\nEstimated project budget is based on the BOQ Grand Total. The value in I67 is provided as the project budget value for system/import use.',10.00,'%',0.00,'other'),(644,16,'Mobilization / Demobilization','GENERAL REQUIREMENTS',2.00,'lot',140000.00,'other'),(645,16,'Permits/Clearances','GENERAL REQUIREMENTS',2.00,'lot',0.00,'other'),(646,16,'Power & Water Consumption','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(647,16,'Bonds & Insurances','GENERAL REQUIREMENTS',2.00,'lot',130000.00,'other'),(648,16,'Site Expenses, Accomodation & Temfacil','GENERAL REQUIREMENTS',2.00,'lot',230000.00,'other'),(649,16,'Health & Safety Requirement & PPE\'s','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(650,16,'Waste & Debris Disposal','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(651,16,'Equipment Shiffing Fee, Hauling and Handling of Materials','GENERAL REQUIREMENTS',2.00,'lot',210000.00,'other'),(652,16,'Scaffoldings & Platforms','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(653,16,'Site Supervision & Management','GENERAL REQUIREMENTS',2.00,'lot',550000.00,'other'),(654,16,'MVAC Design Calculation, Drafting & Coordination Fee','GENERAL REQUIREMENTS',2.00,'lot',250000.00,'other'),(655,16,'As-Built Drawings & Other Documents','GENERAL REQUIREMENTS',2.00,'lot',35000.00,'other'),(656,16,'Vertical Turbine Diesel Engine Fire Pump, 750GPM, 100PSI, 125HP, 220V complete with Fire Pump Controller & Accessories such as Fuel Tank 130 Gallons.','FIRE PROTECTION WORKS — Fire Pump No. 1 (UL/FM) - DIESEL',2.00,'no/s',4525000.00,'other'),(657,16,'Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.','FIRE PROTECTION WORKS — Fire Pump No. 2 (UL/FM) - ELECTRIC',2.00,'no/s',3125000.00,'other'),(658,16,'Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.','FIRE PROTECTION WORKS — Jockey Pump (3 no/s)',1.00,'lot',950000.00,'other'),(659,16,'Pump Mounting Accessories','FIRE PROTECTION WORKS — Jockey Pump',3.00,'lot',25000.00,'other'),(660,16,'Control Wiring (Only from Fire Pump Panel to Pump)','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',610000.00,'other'),(661,16,'Testing / Commissioning','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',275000.00,'other'),(662,16,'ø150mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',35.00,'m',5500.00,'other'),(663,16,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',15.00,'m',4000.00,'other'),(664,16,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',8.00,'m',1000.00,'other'),(665,16,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',20.00,'no/s',2350.00,'other'),(666,16,'ø100mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',2250.00,'other'),(667,16,'ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',1000.00,'other'),(668,16,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Equal Tee',15.00,'no/s',3800.00,'other'),(669,16,'ø100mm x ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',3300.00,'other'),(670,16,'ø32mm x ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',1000.00,'other'),(671,16,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',800.00,'m',3000.00,'other'),(672,16,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',500.00,'m',1500.00,'other'),(673,16,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',70.00,'m',850.00,'other'),(674,16,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',15.00,'no/s',3700.00,'other'),(675,16,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',22.00,'no/s',1600.00,'other'),(676,16,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',55.00,'no/s',850.00,'other'),(677,16,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',12.00,'no/s',2700.00,'other'),(678,16,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',15.00,'no/s',1600.00,'other'),(679,16,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',13.00,'no/s',800.00,'other'),(680,16,'ø150mm x ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',4700.00,'other'),(681,16,'ø100mm x ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',28.00,'no/s',2100.00,'other'),(682,16,'HDPE Flange, ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',352.00,'other'),(683,16,'B.I Flange, ø40mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',25.00,'no/s',353.00,'other'),(684,16,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',125.00,'m',3600.00,'other'),(685,16,'ø65mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',25.00,'m',1600.00,'other'),(686,16,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',30.00,'m',1100.00,'other'),(687,16,'ø40mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',105.00,'m',800.00,'other'),(688,16,'ø32mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',115.00,'m',650.00,'other'),(689,16,'ø25mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',220.00,'m',750.00,'other'),(690,16,'B.I Fittings','FIRE PROTECTION WORKS — B.I Fittings (MECH)',3.00,'lot',650000.00,'other'),(691,16,'Pendent Sprinkler Heads (Viking)','FIRE PROTECTION WORKS — Sprinkler Heads',400.00,'no/s',750.00,'other'),(692,16,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — OS&Y Gate Valve',10.00,'no/s',44400.00,'other'),(693,16,'100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',18000.00,'other'),(694,16,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',5700.00,'other'),(695,16,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',3.00,'no/s',40000.00,'other'),(696,16,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',2.00,'no/s',6700.00,'other'),(697,16,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Alarm Check Valve',3.00,'no/s',96000.00,'other'),(698,16,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Wye Strainer',3.00,'no/s',6700.00,'other'),(699,16,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Water Flow Meter',3.00,'no/s',5000.00,'other'),(700,16,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Pressure Reducing Valve',3.00,'no/s',5000.00,'other'),(701,16,'40mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Air Release Valve',3.00,'no/s',2000.00,'other'),(702,16,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Waste Cone',3.00,'no/s',5000.00,'other'),(703,16,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',5.00,'no/s',3250.00,'other'),(704,16,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',3.00,'no/s',2250.00,'other'),(705,16,'Supervisory Switch','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',11000.00,'other'),(706,16,'Pressure Gauge','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',15.00,'no/s',2800.00,'other'),(707,16,'Sensing Line Assembly','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'set/s',21000.00,'other'),(708,16,'Sight Glass','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',5000.00,'other'),(709,16,'Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',10.00,'no/s',900.00,'other'),(710,16,'Slip-On Flange, 200mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',5.00,'no/s',3500.00,'other'),(711,16,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',50.00,'no/s',2200.00,'other'),(712,16,'Slip-On Flange, 100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',20.00,'no/s',1950.00,'other'),(713,16,'Blind Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',4.00,'no/s',2850.00,'other'),(714,16,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',400.00,'no/s',125.00,'other'),(715,16,'Rigid Plate pipe support with anchor bolt on concerete pad','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',2.00,'lot',5250.00,'other'),(716,16,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Supervisory Valve',3.00,'no/s',44000.00,'other'),(717,16,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Water Flow Detector',3.00,'no/s',21000.00,'other'),(718,16,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',37000.00,'other'),(719,16,'Supervisory Switch','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',11000.00,'other'),(720,16,'Pressure Gauge','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',2800.00,'other'),(721,16,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',9.00,'no/s',2200.00,'other'),(722,16,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',65.00,'no/s',125.00,'other'),(723,16,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',1800.00,'other'),(724,16,'Inspector Test Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(725,16,'Ball Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(726,16,'B.I Union Patente, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',3.00,'no/s',1200.00,'other'),(727,16,'FDC with Cap & Chain, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',50000.00,'other'),(728,16,'Check Valve, 150Ø','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',37000.00,'other'),(729,16,'Pump Test, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',16000.00,'other'),(730,16,'Fire Hose Valve with Cap & Chain, 65mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',10500.00,'other'),(731,16,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',12.00,'no/s',1750.00,'other'),(732,16,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Main Pipe Risers',22.00,'no/s',85.00,'other'),(733,16,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',2000.00,'other'),(734,16,'Ball Valve, 25Ø','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',3500.00,'other'),(735,16,'Fire Hose Cabinet','FIRE PROTECTION WORKS\nmade of Ga.# 18 galvanized iron steel painted (interior and exterior body) with one coat of red lead primer before painting with 2 coat of fire engine red color\n1/8\" thk. Clear Glass\n30m x 40Ø fire hose, double jacketed\n40Ø angle valve, FHV, UL/FM\n40Ø adjustable pistol type (tight grip nozzle)\nSemi-automatic hose rack\nPiano Hinge\nBrass nipple, adaptor and coupling\n9 lbs. Fire Extinguisher, dry chemical, class ABC',15.00,'set/s',35000.00,'other'),(736,16,'Fire Hose Cabinet & Hydrant','FIRE PROTECTION WORKS',35.00,'set/s',86500.00,'other'),(737,16,'Portable Fire Extinguisher','FIRE PROTECTION WORKS\n10 lbs. FE Dry Chemical, class ABC',35.00,'set/s',2000.00,'other'),(738,16,'FM 200','FIRE PROTECTION WORKS — FIRE SUPPRESSION SYSTEM (EE ROOM)',3.00,'lot',1650000.00,'other'),(739,16,'Builders Work & Excavation','OTHER ITEMS',3.00,'lot',600000.00,'other'),(740,16,'Wall Chipping, Pipe Sleeving, Grouting & Sealing','OTHER ITEMS',3.00,'lot',45000.00,'other'),(741,16,'Pipe Supports & Hangers','OTHER ITEMS',3.00,'lot',200000.00,'other'),(742,16,'Painting Works','OTHER ITEMS',3.00,'lot',40000.00,'other'),(743,16,'Hydro Test','OTHER ITEMS',3.00,'lot',55000.00,'other'),(744,16,'Miscellaneous & Consumables','OTHER ITEMS',3.00,'lot',450000.00,'other'),(745,17,'Mobilization / Demobilization','GENERAL REQUIREMENTS',2.00,'lot',140000.00,'other'),(746,17,'Permits/Clearances','GENERAL REQUIREMENTS',2.00,'lot',0.00,'other'),(747,17,'Power & Water Consumption','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(748,17,'Bonds & Insurances','GENERAL REQUIREMENTS',2.00,'lot',130000.00,'other'),(749,17,'Site Expenses, Accomodation & Temfacil','GENERAL REQUIREMENTS',2.00,'lot',230000.00,'other'),(750,17,'Health & Safety Requirement & PPE\'s','GENERAL REQUIREMENTS',2.00,'lot',90000.00,'other'),(751,17,'Waste & Debris Disposal','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(752,17,'Equipment Shiffing Fee, Hauling and Handling of Materials','GENERAL REQUIREMENTS',2.00,'lot',210000.00,'other'),(753,17,'Scaffoldings & Platforms','GENERAL REQUIREMENTS',2.00,'lot',55000.00,'other'),(754,17,'Site Supervision & Management','GENERAL REQUIREMENTS',2.00,'lot',550000.00,'other'),(755,17,'MVAC Design Calculation, Drafting & Coordination Fee','GENERAL REQUIREMENTS',2.00,'lot',250000.00,'other'),(756,17,'As-Built Drawings & Other Documents','GENERAL REQUIREMENTS',2.00,'lot',35000.00,'other'),(757,17,'Vertical Turbine Diesel Engine Fire Pump, 750GPM, 100PSI, 125HP, 220V complete with Fire Pump Controller & Accessories such as Fuel Tank 130 Gallons.','FIRE PROTECTION WORKS — Fire Pump No. 1 (UL/FM) - DIESEL',2.00,'no/s',4525000.00,'other'),(758,17,'Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.','FIRE PROTECTION WORKS — Fire Pump No. 2 (UL/FM) - ELECTRIC',2.00,'no/s',3125000.00,'other'),(759,17,'Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.','FIRE PROTECTION WORKS — Jockey Pump (3 no/s)',1.00,'lot',950000.00,'other'),(760,17,'Pump Mounting Accessories','FIRE PROTECTION WORKS — Jockey Pump',3.00,'lot',25000.00,'other'),(761,17,'Control Wiring (Only from Fire Pump Panel to Pump)','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',610000.00,'other'),(762,17,'Testing / Commissioning','FIRE PROTECTION WORKS — Jockey Pump (3 lot)',1.00,'lot',275000.00,'other'),(763,17,'ø150mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',35.00,'m',5500.00,'other'),(764,17,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',15.00,'m',4000.00,'other'),(765,17,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Pump Room)',8.00,'m',1000.00,'other'),(766,17,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',20.00,'no/s',2350.00,'other'),(767,17,'ø100mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',2250.00,'other'),(768,17,'ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Elbow 90',3.00,'no/s',1000.00,'other'),(769,17,'ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Equal Tee',15.00,'no/s',3800.00,'other'),(770,17,'ø100mm x ø150mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',3300.00,'other'),(771,17,'ø32mm x ø50mm','FIRE PROTECTION WORKS — B.I Fittings (MECH) — Reducer',3.00,'no/s',1000.00,'other'),(772,17,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',800.00,'m',3000.00,'other'),(773,17,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',500.00,'m',1500.00,'other'),(774,17,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — HDPE PIPE',70.00,'m',850.00,'other'),(775,17,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',15.00,'no/s',3700.00,'other'),(776,17,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',22.00,'no/s',1600.00,'other'),(777,17,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 90',55.00,'no/s',850.00,'other'),(778,17,'ø150mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',12.00,'no/s',2700.00,'other'),(779,17,'ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',15.00,'no/s',1600.00,'other'),(780,17,'ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Elbow 45',13.00,'no/s',800.00,'other'),(781,17,'ø150mm x ø100mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',4700.00,'other'),(782,17,'ø100mm x ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',28.00,'no/s',2100.00,'other'),(783,17,'HDPE Flange, ø65mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',35.00,'no/s',352.00,'other'),(784,17,'B.I Flange, ø40mm','FIRE PROTECTION WORKS — UNDERGROUND PIPES (LOOP PIPING) — Tee Reducer',25.00,'no/s',353.00,'other'),(785,17,'ø100mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',125.00,'m',3600.00,'other'),(786,17,'ø65mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',25.00,'m',1600.00,'other'),(787,17,'ø50mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',30.00,'m',1100.00,'other'),(788,17,'ø40mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',105.00,'m',800.00,'other'),(789,17,'ø32mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',115.00,'m',650.00,'other'),(790,17,'ø25mm','FIRE PROTECTION WORKS — B.I Pipes, Sched. 40 (Admin Building)',220.00,'m',750.00,'other'),(791,17,'B.I Fittings','FIRE PROTECTION WORKS — B.I Fittings (MECH)',3.00,'lot',650000.00,'other'),(792,17,'Pendent Sprinkler Heads (Viking)','FIRE PROTECTION WORKS — Sprinkler Heads',400.00,'no/s',750.00,'other'),(793,17,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — OS&Y Gate Valve',10.00,'no/s',44400.00,'other'),(794,17,'100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',18000.00,'other'),(795,17,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Gate Valve',3.00,'no/s',5700.00,'other'),(796,17,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',3.00,'no/s',40000.00,'other'),(797,17,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Check Valve',2.00,'no/s',6700.00,'other'),(798,17,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Alarm Check Valve',3.00,'no/s',96000.00,'other'),(799,17,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Wye Strainer',3.00,'no/s',6700.00,'other'),(800,17,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Water Flow Meter',3.00,'no/s',5000.00,'other'),(801,17,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Pressure Reducing Valve',3.00,'no/s',5000.00,'other'),(802,17,'40mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Air Release Valve',3.00,'no/s',2000.00,'other'),(803,17,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Waste Cone',3.00,'no/s',5000.00,'other'),(804,17,'150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',5.00,'no/s',3250.00,'other'),(805,17,'50mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Mechanical Flexible Coupling',3.00,'no/s',2250.00,'other'),(806,17,'Supervisory Switch','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',11000.00,'other'),(807,17,'Pressure Gauge','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',15.00,'no/s',2800.00,'other'),(808,17,'Sensing Line Assembly','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'set/s',21000.00,'other'),(809,17,'Sight Glass','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',3.00,'no/s',5000.00,'other'),(810,17,'Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',10.00,'no/s',900.00,'other'),(811,17,'Slip-On Flange, 200mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',5.00,'no/s',3500.00,'other'),(812,17,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',50.00,'no/s',2200.00,'other'),(813,17,'Slip-On Flange, 100mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',20.00,'no/s',1950.00,'other'),(814,17,'Blind Flange, 150mmØ','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',4.00,'no/s',2850.00,'other'),(815,17,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',400.00,'no/s',125.00,'other'),(816,17,'Rigid Plate pipe support with anchor bolt on concerete pad','FIRE PROTECTION WORKS — Valves & Accessories — Essentric Reducer',2.00,'lot',5250.00,'other'),(817,17,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Supervisory Valve',3.00,'no/s',44000.00,'other'),(818,17,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Water Flow Detector',3.00,'no/s',21000.00,'other'),(819,17,'100mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',37000.00,'other'),(820,17,'Supervisory Switch','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',11000.00,'other'),(821,17,'Pressure Gauge','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',3.00,'no/s',2800.00,'other'),(822,17,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',9.00,'no/s',2200.00,'other'),(823,17,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Floor Control Valve — Check Valve',65.00,'no/s',125.00,'other'),(824,17,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',1800.00,'other'),(825,17,'Inspector Test Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(826,17,'Ball Valve, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',1.00,'no/s',4500.00,'other'),(827,17,'B.I Union Patente, 25mmØ','FIRE PROTECTION WORKS — Auxillary Drain Assembly',3.00,'no/s',1200.00,'other'),(828,17,'FDC with Cap & Chain, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',50000.00,'other'),(829,17,'Check Valve, 150Ø','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',37000.00,'other'),(830,17,'Pump Test, 150x65x65x65','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',16000.00,'other'),(831,17,'Fire Hose Valve with Cap & Chain, 65mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',3.00,'no/s',10500.00,'other'),(832,17,'Slip-On Flange, 150mmØ','FIRE PROTECTION WORKS — Main Pipe Risers',12.00,'no/s',1750.00,'other'),(833,17,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','FIRE PROTECTION WORKS — Main Pipe Risers',22.00,'no/s',85.00,'other'),(834,17,'Sight Glass, 25mmØ','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',2000.00,'other'),(835,17,'Ball Valve, 25Ø','FIRE PROTECTION WORKS — Inspector Test Connection',3.00,'no/s',3500.00,'other'),(836,17,'Fire Hose Cabinet','FIRE PROTECTION WORKS\nmade of Ga.# 18 galvanized iron steel painted (interior and exterior body) with one coat of red lead primer before painting with 2 coat of fire engine red color\n1/8\" thk. Clear Glass\n30m x 40Ø fire hose, double jacketed\n40Ø angle valve, FHV, UL/FM\n40Ø adjustable pistol type (tight grip nozzle)\nSemi-automatic hose rack\nPiano Hinge\nBrass nipple, adaptor and coupling\n9 lbs. Fire Extinguisher, dry chemical, class ABC',15.00,'set/s',35000.00,'other'),(837,17,'Fire Hose Cabinet & Hydrant','FIRE PROTECTION WORKS',35.00,'set/s',86500.00,'other'),(838,17,'Portable Fire Extinguisher','FIRE PROTECTION WORKS\n10 lbs. FE Dry Chemical, class ABC',35.00,'set/s',2000.00,'other'),(839,17,'FM 200','FIRE PROTECTION WORKS — FIRE SUPPRESSION SYSTEM (EE ROOM)',3.00,'lot',1650000.00,'other'),(840,17,'Builders Work & Excavation','OTHER ITEMS',3.00,'lot',600000.00,'other'),(841,17,'Wall Chipping, Pipe Sleeving, Grouting & Sealing','OTHER ITEMS',3.00,'lot',45000.00,'other'),(842,17,'Pipe Supports & Hangers','OTHER ITEMS',3.00,'lot',200000.00,'other'),(843,17,'Painting Works','OTHER ITEMS',3.00,'lot',40000.00,'other'),(844,17,'Hydro Test','OTHER ITEMS',3.00,'lot',55000.00,'other'),(845,17,'Miscellaneous & Consumables','OTHER ITEMS',3.00,'lot',450000.00,'other');
/*!40000 ALTER TABLE `project_quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_code` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `client` varchar(200) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('draft','quotation','approved','accounting_setup','assigned','planning','material_request','procurement','material_available','implementation','ongoing','completed','on_hold') NOT NULL DEFAULT 'approved',
  `estimated_budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `actual_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_to` bigint(20) unsigned DEFAULT NULL,
  `in_charge_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `source_quotation_id` bigint(20) unsigned DEFAULT NULL,
  `source_contract_id` bigint(20) unsigned DEFAULT NULL,
  `quotation_path` varchar(255) DEFAULT NULL,
  `quotation_name` varchar(255) DEFAULT NULL,
  `subject` text DEFAULT NULL,
  `accounting_received_at` datetime DEFAULT NULL,
  `accounting_status` enum('pending','received','quoted','rejected') NOT NULL DEFAULT 'pending',
  `engineering_received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projects_code` (`project_code`),
  KEY `idx_projects_creator` (`created_by`),
  KEY `idx_projects_pic` (`in_charge_id`),
  KEY `idx_projects_assigned_to` (`assigned_to`),
  CONSTRAINT `fk_projects_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_projects_dates` CHECK (`end_date` is null or `start_date` is null or `end_date` >= `start_date`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
INSERT INTO `projects` VALUES (10,'PRJ-2026-001','DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','','66.5 MLD SEAWATER DESALINATION FACILITY at Brgy. Ingore, La Paz, Iloilo','J.E. MANALO & CO., INC.',NULL,NULL,'assigned',48121012.80,14999.94,1,17,17,'2026-10-02 14:14:17','2026-10-07 10:49:41',NULL,NULL,'uploads/project_quotations/p10_81b8aa377a05.xlsx','Fire-Protection_BOQ-Breakdown_30-Oct-2024-3.xlsx','BILL OF QUANTITES (BOQ) - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','2026-10-02 22:18:57','received','2026-10-02 22:27:56'),(11,'PRJ-2026-002','DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','','66.5 MLD SEAWATER DESALINATION FACILITY at Brgy. Ingore, La Paz, Iloilo','J.E. MANALO & CO., INC.','2026-10-03','2026-12-25','planning',48121012.80,0.00,1,18,18,'2026-10-03 01:01:23','2026-10-03 01:01:23',NULL,NULL,'uploads/project_quotations/p11_42da9c17c0f8.xlsx','Fire-Protection_BOQ-Breakdown_30-Oct-2024-3 (1).xlsx','BILL OF QUANTITES (BOQ) - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM',NULL,'pending',NULL),(12,'PRJ-2026-003','DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','','66.5 MLD SEAWATER DESALINATION FACILITY at Brgy. Ingore, La Paz, Iloilo','J.E. MANALO & CO., INC.','2026-10-03','2027-01-11','planning',48121012.80,0.00,1,18,18,'2026-10-03 01:06:32','2026-10-03 01:46:32',NULL,NULL,'uploads/project_quotations/p12_16395574e101.xlsx','Fire-Protection_BOQ-Breakdown_30-Oct-2024-3 (1).xlsx','BILL OF QUANTITES (BOQ) - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM',NULL,'pending','2026-10-03 09:46:32'),(13,'PRJ-2026-004','DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','','66.5 MLD SEAWATER DESALINATION FACILITY at Brgy. Ingore, La Paz, Iloilo','J.E. MANALO & CO., INC.','2026-10-03','2026-12-04','planning',48121012.80,0.00,1,17,17,'2026-10-03 02:16:20','2026-10-03 02:16:20',NULL,NULL,'uploads/project_quotations/p13_e20a403c393a.xlsx','Fire-Protection_BOQ-Breakdown_30-Oct-2024-3 (1).xlsx','BILL OF QUANTITES (BOQ) - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM',NULL,'pending',NULL),(14,'PRJ-2026-005','POOL CONSTRUCTION PROJECT','','Residential Pool Construction','Greenfield Residential Development Corp.',NULL,NULL,'assigned',48121012.80,0.00,1,18,18,'2026-10-03 02:19:04','2026-10-07 10:43:07',NULL,NULL,'uploads/project_quotations/p14_16d762f0ef95.xlsx','Pool_Construction_BOQ_Greenfield_WITH_PROJECT_BUDGET.xlsx','BILL OF QUANTITES (BOQ) - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM',NULL,'pending',NULL),(15,'PRJ-2026-006','house','','Laguna City','Angel','2027-11-20',NULL,'planning',2000000.00,0.00,1,18,18,'2026-10-03 02:27:24','2026-10-07 10:43:07',NULL,NULL,NULL,NULL,'BILL OF QUANTITES (BOQ) - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM',NULL,'pending',NULL),(16,'PRJ-2026-007','DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','','66.5 MLD SEAWATER DESALINATION FACILITY at Brgy. Ingore, La Paz, Iloilo','J.E. MANALO & CO., INC.',NULL,NULL,'assigned',48121012.80,0.00,1,17,17,'2026-10-06 15:08:39','2026-10-07 10:43:07',NULL,NULL,'uploads/project_quotations/p16_142edbbe8a2f.xlsx','Fire-Protection_BOQ-Breakdown_30-Oct-2024-3 (1).xlsx','BILL OF QUANTITES (BOQ) - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM',NULL,'pending',NULL),(17,'PRJ-2026-008','DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','','66.5 MLD SEAWATER DESALINATION FACILITY at Brgy. Ingore, La Paz, Iloilo','J.E. MANALO & CO., INC.','2026-11-11','2026-11-11','planning',48121012.80,0.00,1,18,18,'2026-10-07 10:52:22','2026-10-07 11:04:11',NULL,NULL,'uploads/project_quotations/p17_0294c04bd87d.xlsx','Fire-Protection_BOQ-Breakdown_30-Oct-2024-3 (1).xlsx','BILL OF QUANTITES (BOQ) - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM',NULL,'pending','2026-10-07 19:04:11');
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  `received_quantity` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_poi_po` (`purchase_order_id`),
  KEY `idx_poi_material` (`material_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
INSERT INTO `purchase_order_items` VALUES (1,3,2,3,18000.00,0,'2026-10-02 13:03:53'),(2,3,2,3,44000.00,0,'2026-10-02 13:03:53'),(3,3,2,3,21000.00,0,'2026-10-02 13:03:53'),(4,3,2,3,37000.00,0,'2026-10-02 13:03:53'),(5,3,3,400,750.00,0,'2026-10-02 13:03:53'),(6,4,2,3,18000.00,3,'2026-10-02 13:04:25'),(7,4,2,3,44000.00,3,'2026-10-02 13:04:25'),(8,4,2,3,21000.00,3,'2026-10-02 13:04:25'),(9,4,2,3,37000.00,3,'2026-10-02 13:04:25'),(10,4,3,400,750.00,400,'2026-10-02 13:04:25'),(11,5,2,3,18000.00,0,'2026-10-02 14:38:19'),(12,5,2,3,44000.00,0,'2026-10-02 14:38:19'),(13,6,2,3,18000.00,0,'2026-10-02 14:38:39'),(14,6,2,3,44000.00,0,'2026-10-02 14:38:39'),(15,7,2,3,2500.00,3,'2026-10-02 14:48:09'),(16,7,2,3,2499.98,3,'2026-10-02 14:48:09');
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `purchase_request_id` bigint(20) unsigned DEFAULT NULL,
  `supplier` varchar(200) NOT NULL,
  `supplier_contact` varchar(150) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `payment_terms` varchar(100) DEFAULT NULL,
  `status` enum('draft','sent','confirmed','ready_for_warehouse','delivered','cancelled') NOT NULL DEFAULT 'draft',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `received_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_po_number` (`po_number`),
  KEY `idx_po_request` (`purchase_request_id`),
  KEY `idx_po_creator` (`created_by`),
  CONSTRAINT `fk_po_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_po_request` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
INSERT INTO `purchase_orders` VALUES (3,'PO-202610-0001',1,'garlan','john','2026-10-02',NULL,'','draft',11,'2026-10-02 13:03:53',NULL,NULL),(4,'PO-202610-0002',1,'garlan','john','2026-10-02','2026-10-31','cash','delivered',11,'2026-10-02 13:04:25','2026-10-02 13:06:18','2026-10-02 21:06:18'),(5,'PO-202610-0003',2,'Villanueva Joram S','+639615448081','2026-10-02',NULL,'','draft',11,'2026-10-02 14:38:19',NULL,NULL),(6,'PO-202610-0004',2,'Villanueva Joram S','+639615448081','2026-10-02',NULL,'','draft',11,'2026-10-02 14:38:39',NULL,NULL),(7,'PO-202610-0005',2,'Villanueva Joram S','+639615448081','2026-10-02',NULL,'','delivered',11,'2026-10-02 14:48:09','2026-10-02 14:52:58','2026-10-02 22:52:58');
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_request_items`
--

DROP TABLE IF EXISTS `purchase_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_request_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `estimated_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pri_request` (`purchase_request_id`),
  KEY `idx_pri_material` (`material_id`),
  CONSTRAINT `fk_pri_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pri_request` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_request_items`
--

LOCK TABLES `purchase_request_items` WRITE;
/*!40000 ALTER TABLE `purchase_request_items` DISABLE KEYS */;
INSERT INTO `purchase_request_items` VALUES (1,1,2,3,'no/s',18000.00,'100mmØ','2026-10-02 12:42:46'),(2,1,2,3,'no/s',44000.00,'100mmØ','2026-10-02 12:42:46'),(3,1,2,3,'no/s',21000.00,'100mmØ','2026-10-02 12:42:46'),(4,1,2,3,'no/s',37000.00,'100mmØ','2026-10-02 12:42:46'),(5,1,3,400,'no/s',750.00,'Pendent Sprinkler Heads (Viking)','2026-10-02 12:42:46'),(6,2,2,3,'no/s',18000.00,'100mmØ','2026-10-02 14:28:40'),(7,2,2,3,'no/s',44000.00,'100mmØ','2026-10-02 14:28:40'),(8,3,2,3,'no/s',18000.00,'100mmØ','2026-10-03 01:46:32'),(9,3,2,3,'no/s',44000.00,'100mmØ','2026-10-03 01:46:32'),(10,3,2,3,'no/s',21000.00,'100mmØ','2026-10-03 01:46:32'),(11,3,2,3,'no/s',37000.00,'100mmØ','2026-10-03 01:46:32'),(12,3,4,10,'no/s',44400.00,'150mmØ','2026-10-03 01:46:32'),(13,3,4,3,'no/s',40000.00,'150mmØ','2026-10-03 01:46:32'),(14,3,4,3,'no/s',96000.00,'150mmØ','2026-10-03 01:46:32'),(15,3,4,3,'no/s',5000.00,'150mmØ','2026-10-03 01:46:32'),(16,3,4,3,'no/s',5000.00,'150mmØ','2026-10-03 01:46:32'),(17,3,4,3,'no/s',5000.00,'150mmØ','2026-10-03 01:46:32'),(18,3,4,5,'no/s',3250.00,'150mmØ','2026-10-03 01:46:32'),(19,3,5,3,'no/s',2000.00,'40mmØ','2026-10-03 01:46:32'),(20,3,6,3,'no/s',5700.00,'50mmØ','2026-10-03 01:46:32'),(21,3,6,2,'no/s',6700.00,'50mmØ','2026-10-03 01:46:32'),(22,3,6,3,'no/s',6700.00,'50mmØ','2026-10-03 01:46:32'),(23,3,6,3,'no/s',2250.00,'50mmØ','2026-10-03 01:46:32'),(24,3,7,2,'lot',35000.00,'As-Built Drawings & Other Documents','2026-10-03 01:46:32'),(25,3,8,3,'lot',650000.00,'B.I Fittings','2026-10-03 01:46:32'),(26,3,9,25,'no/s',353.00,'B.I Flange, ø40mm','2026-10-03 01:46:32'),(27,3,10,3,'no/s',1200.00,'B.I Union Patente, 25mmØ','2026-10-03 01:46:32'),(28,3,11,1,'no/s',4500.00,'Ball Valve, 25mmØ','2026-10-03 01:46:32'),(29,3,12,3,'no/s',3500.00,'Ball Valve, 25Ø','2026-10-03 01:46:32'),(30,3,13,4,'no/s',2850.00,'Blind Flange, 150mmØ','2026-10-03 01:46:32'),(31,3,14,2,'lot',130000.00,'Bonds & Insurances','2026-10-03 01:46:32'),(32,3,15,3,'lot',600000.00,'Builders Work & Excavation','2026-10-03 01:46:32'),(33,3,16,3,'no/s',37000.00,'Check Valve, 150Ø','2026-10-03 01:46:32'),(34,3,17,1,'lot',610000.00,'Control Wiring (Only from Fire Pump Panel to Pump)','2026-10-03 01:46:32'),(35,3,18,2,'lot',210000.00,'Equipment Shiffing Fee, Hauling and Handling of Materials','2026-10-03 01:46:32'),(36,3,19,3,'no/s',50000.00,'FDC with Cap & Chain, 150x65x65x65','2026-10-03 01:46:32'),(37,3,20,15,'set/s',35000.00,'Fire Hose Cabinet','2026-10-03 01:46:32'),(38,3,21,35,'set/s',86500.00,'Fire Hose Cabinet & Hydrant','2026-10-03 01:46:32'),(39,3,22,3,'no/s',10500.00,'Fire Hose Valve with Cap & Chain, 65mmØ','2026-10-03 01:46:32'),(40,3,23,3,'lot',1650000.00,'FM 200','2026-10-03 01:46:32'),(41,3,24,35,'no/s',352.00,'HDPE Flange, ø65mm','2026-10-03 01:46:32'),(42,3,25,2,'lot',90000.00,'Health & Safety Requirement & PPE\'s','2026-10-03 01:46:32'),(43,3,26,400,'no/s',125.00,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','2026-10-03 01:46:32'),(44,3,26,65,'no/s',125.00,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','2026-10-03 01:46:32'),(45,3,26,22,'no/s',85.00,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','2026-10-03 01:46:32'),(46,3,27,3,'lot',55000.00,'Hydro Test','2026-10-03 01:46:32'),(47,3,28,1,'no/s',4500.00,'Inspector Test Valve, 25mmØ','2026-10-03 01:46:32'),(48,3,29,3,'lot',450000.00,'Miscellaneous & Consumables','2026-10-03 01:46:32'),(49,3,30,2,'lot',140000.00,'Mobilization / Demobilization','2026-10-03 01:46:32'),(50,3,31,2,'lot',250000.00,'MVAC Design Calculation, Drafting & Coordination Fee','2026-10-03 01:46:32'),(51,3,32,10,'no/s',900.00,'Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.','2026-10-03 01:46:32'),(52,3,33,3,'lot',40000.00,'Painting Works','2026-10-03 01:46:32'),(53,3,3,400,'no/s',750.00,'Pendent Sprinkler Heads (Viking)','2026-10-03 01:46:32'),(54,3,34,2,'lot',0.00,'Permits/Clearances','2026-10-03 01:46:32'),(55,3,35,3,'lot',200000.00,'Pipe Supports & Hangers','2026-10-03 01:46:32'),(56,3,36,35,'set/s',2000.00,'Portable Fire Extinguisher','2026-10-03 01:46:32'),(57,3,37,2,'lot',90000.00,'Power & Water Consumption','2026-10-03 01:46:32'),(58,3,38,15,'no/s',2800.00,'Pressure Gauge','2026-10-03 01:46:32'),(59,3,38,3,'no/s',2800.00,'Pressure Gauge','2026-10-03 01:46:32'),(60,3,39,3,'lot',25000.00,'Pump Mounting Accessories','2026-10-03 01:46:32'),(61,3,40,3,'no/s',16000.00,'Pump Test, 150x65x65x65','2026-10-03 01:46:32'),(62,3,41,2,'lot',5250.00,'Rigid Plate pipe support with anchor bolt on concerete pad','2026-10-03 01:46:32'),(63,3,42,2,'lot',55000.00,'Scaffoldings & Platforms','2026-10-03 01:46:32'),(64,3,43,3,'set/s',21000.00,'Sensing Line Assembly','2026-10-03 01:46:32'),(65,3,44,3,'no/s',5000.00,'Sight Glass','2026-10-03 01:46:32'),(66,3,45,1,'no/s',1800.00,'Sight Glass, 25mmØ','2026-10-03 01:46:32'),(67,3,45,3,'no/s',2000.00,'Sight Glass, 25mmØ','2026-10-03 01:46:32'),(68,3,46,2,'lot',230000.00,'Site Expenses, Accomodation & Temfacil','2026-10-03 01:46:32'),(69,3,47,2,'lot',550000.00,'Site Supervision & Management','2026-10-03 01:46:32'),(70,3,48,20,'no/s',1950.00,'Slip-On Flange, 100mmØ','2026-10-03 01:46:32'),(71,3,49,50,'no/s',2200.00,'Slip-On Flange, 150mmØ','2026-10-03 01:46:32'),(72,3,49,9,'no/s',2200.00,'Slip-On Flange, 150mmØ','2026-10-03 01:46:32'),(73,3,49,12,'no/s',1750.00,'Slip-On Flange, 150mmØ','2026-10-03 01:46:32'),(74,3,50,5,'no/s',3500.00,'Slip-On Flange, 200mmØ','2026-10-03 01:46:32'),(75,3,51,3,'no/s',11000.00,'Supervisory Switch','2026-10-03 01:46:32'),(76,3,51,3,'no/s',11000.00,'Supervisory Switch','2026-10-03 01:46:32'),(77,3,52,1,'lot',275000.00,'Testing / Commissioning','2026-10-03 01:46:32'),(78,3,53,1,'lot',950000.00,'Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.','2026-10-03 01:46:32'),(79,3,54,2,'no/s',3125000.00,'Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.','2026-10-03 01:46:32'),(80,3,55,3,'lot',45000.00,'Wall Chipping, Pipe Sleeving, Grouting & Sealing','2026-10-03 01:46:32'),(81,3,56,2,'lot',55000.00,'Waste & Debris Disposal','2026-10-03 01:46:32'),(82,3,57,15,'m',4000.00,'ø100mm','2026-10-03 01:46:32'),(83,3,57,3,'no/s',2250.00,'ø100mm','2026-10-03 01:46:32'),(84,3,57,500,'m',1500.00,'ø100mm','2026-10-03 01:46:32'),(85,3,57,22,'no/s',1600.00,'ø100mm','2026-10-03 01:46:32'),(86,3,57,15,'no/s',1600.00,'ø100mm','2026-10-03 01:46:32'),(87,3,57,125,'m',3600.00,'ø100mm','2026-10-03 01:46:32'),(88,3,58,3,'no/s',3300.00,'ø100mm x ø150mm','2026-10-03 01:46:32'),(89,3,59,28,'no/s',2100.00,'ø100mm x ø65mm','2026-10-03 01:46:32'),(90,3,60,35,'m',5500.00,'ø150mm','2026-10-03 01:46:32'),(91,3,60,20,'no/s',2350.00,'ø150mm','2026-10-03 01:46:32'),(92,3,60,15,'no/s',3800.00,'ø150mm','2026-10-03 01:46:32'),(93,3,60,800,'m',3000.00,'ø150mm','2026-10-03 01:46:32'),(94,3,60,15,'no/s',3700.00,'ø150mm','2026-10-03 01:46:32'),(95,3,60,12,'no/s',2700.00,'ø150mm','2026-10-03 01:46:32'),(96,3,61,35,'no/s',4700.00,'ø150mm x ø100mm','2026-10-03 01:46:32'),(97,3,62,220,'m',750.00,'ø25mm','2026-10-03 01:46:32'),(98,3,63,115,'m',650.00,'ø32mm','2026-10-03 01:46:32'),(99,3,64,3,'no/s',1000.00,'ø32mm x ø50mm','2026-10-03 01:46:32'),(100,3,65,105,'m',800.00,'ø40mm','2026-10-03 01:46:32'),(101,3,66,8,'m',1000.00,'ø50mm','2026-10-03 01:46:32'),(102,3,66,3,'no/s',1000.00,'ø50mm','2026-10-03 01:46:32'),(103,3,66,30,'m',1100.00,'ø50mm','2026-10-03 01:46:32'),(104,3,67,70,'m',850.00,'ø65mm','2026-10-03 01:46:32'),(105,3,67,55,'no/s',850.00,'ø65mm','2026-10-03 01:46:32'),(106,3,67,13,'no/s',800.00,'ø65mm','2026-10-03 01:46:32'),(107,3,67,25,'m',1600.00,'ø65mm','2026-10-03 01:46:32'),(108,4,2,3,'no/s',18000.00,'100mmØ','2026-10-07 11:04:10'),(109,4,2,3,'no/s',44000.00,'100mmØ','2026-10-07 11:04:10'),(110,4,2,3,'no/s',21000.00,'100mmØ','2026-10-07 11:04:10'),(111,4,2,3,'no/s',37000.00,'100mmØ','2026-10-07 11:04:10'),(112,4,4,10,'no/s',44400.00,'150mmØ','2026-10-07 11:04:10'),(113,4,4,3,'no/s',40000.00,'150mmØ','2026-10-07 11:04:10'),(114,4,4,3,'no/s',96000.00,'150mmØ','2026-10-07 11:04:10'),(115,4,4,3,'no/s',5000.00,'150mmØ','2026-10-07 11:04:10'),(116,4,4,3,'no/s',5000.00,'150mmØ','2026-10-07 11:04:10'),(117,4,4,3,'no/s',5000.00,'150mmØ','2026-10-07 11:04:10'),(118,4,4,5,'no/s',3250.00,'150mmØ','2026-10-07 11:04:10'),(119,4,5,3,'no/s',2000.00,'40mmØ','2026-10-07 11:04:10'),(120,4,6,3,'no/s',5700.00,'50mmØ','2026-10-07 11:04:10'),(121,4,6,2,'no/s',6700.00,'50mmØ','2026-10-07 11:04:10'),(122,4,6,3,'no/s',6700.00,'50mmØ','2026-10-07 11:04:10'),(123,4,6,3,'no/s',2250.00,'50mmØ','2026-10-07 11:04:10'),(124,4,7,2,'lot',35000.00,'As-Built Drawings & Other Documents','2026-10-07 11:04:10'),(125,4,8,3,'lot',650000.00,'B.I Fittings','2026-10-07 11:04:10'),(126,4,9,25,'no/s',353.00,'B.I Flange, ø40mm','2026-10-07 11:04:10'),(127,4,10,3,'no/s',1200.00,'B.I Union Patente, 25mmØ','2026-10-07 11:04:10'),(128,4,11,1,'no/s',4500.00,'Ball Valve, 25mmØ','2026-10-07 11:04:10'),(129,4,12,3,'no/s',3500.00,'Ball Valve, 25Ø','2026-10-07 11:04:10'),(130,4,13,4,'no/s',2850.00,'Blind Flange, 150mmØ','2026-10-07 11:04:10'),(131,4,14,2,'lot',130000.00,'Bonds & Insurances','2026-10-07 11:04:10'),(132,4,15,3,'lot',600000.00,'Builders Work & Excavation','2026-10-07 11:04:10'),(133,4,16,3,'no/s',37000.00,'Check Valve, 150Ø','2026-10-07 11:04:10'),(134,4,17,1,'lot',610000.00,'Control Wiring (Only from Fire Pump Panel to Pump)','2026-10-07 11:04:10'),(135,4,18,2,'lot',210000.00,'Equipment Shiffing Fee, Hauling and Handling of Materials','2026-10-07 11:04:10'),(136,4,19,3,'no/s',50000.00,'FDC with Cap & Chain, 150x65x65x65','2026-10-07 11:04:10'),(137,4,20,15,'set/s',35000.00,'Fire Hose Cabinet','2026-10-07 11:04:10'),(138,4,21,35,'set/s',86500.00,'Fire Hose Cabinet & Hydrant','2026-10-07 11:04:10'),(139,4,22,3,'no/s',10500.00,'Fire Hose Valve with Cap & Chain, 65mmØ','2026-10-07 11:04:10'),(140,4,23,3,'lot',1650000.00,'FM 200','2026-10-07 11:04:10'),(141,4,24,35,'no/s',352.00,'HDPE Flange, ø65mm','2026-10-07 11:04:10'),(142,4,25,2,'lot',90000.00,'Health & Safety Requirement & PPE\'s','2026-10-07 11:04:10'),(143,4,26,400,'no/s',125.00,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','2026-10-07 11:04:10'),(144,4,26,65,'no/s',125.00,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','2026-10-07 11:04:10'),(145,4,26,22,'no/s',85.00,'High Tensile Bolt & Nut with Washer, 3/4\"Ø x 3\" (L)','2026-10-07 11:04:10'),(146,4,27,3,'lot',55000.00,'Hydro Test','2026-10-07 11:04:10'),(147,4,28,1,'no/s',4500.00,'Inspector Test Valve, 25mmØ','2026-10-07 11:04:10'),(148,4,29,3,'lot',450000.00,'Miscellaneous & Consumables','2026-10-07 11:04:10'),(149,4,30,2,'lot',140000.00,'Mobilization / Demobilization','2026-10-07 11:04:10'),(150,4,31,2,'lot',250000.00,'MVAC Design Calculation, Drafting & Coordination Fee','2026-10-07 11:04:10'),(151,4,32,10,'no/s',900.00,'Neoprene Non-Skid Rubber Isolator, 6\" x 6\" x 3/4\" thk.','2026-10-07 11:04:10'),(152,4,33,3,'lot',40000.00,'Painting Works','2026-10-07 11:04:10'),(153,4,3,400,'no/s',750.00,'Pendent Sprinkler Heads (Viking)','2026-10-07 11:04:10'),(154,4,34,2,'lot',0.00,'Permits/Clearances','2026-10-07 11:04:10'),(155,4,35,3,'lot',200000.00,'Pipe Supports & Hangers','2026-10-07 11:04:10'),(156,4,36,35,'set/s',2000.00,'Portable Fire Extinguisher','2026-10-07 11:04:10'),(157,4,37,2,'lot',90000.00,'Power & Water Consumption','2026-10-07 11:04:10'),(158,4,38,15,'no/s',2800.00,'Pressure Gauge','2026-10-07 11:04:10'),(159,4,38,3,'no/s',2800.00,'Pressure Gauge','2026-10-07 11:04:10'),(160,4,39,3,'lot',25000.00,'Pump Mounting Accessories','2026-10-07 11:04:10'),(161,4,40,3,'no/s',16000.00,'Pump Test, 150x65x65x65','2026-10-07 11:04:10'),(162,4,41,2,'lot',5250.00,'Rigid Plate pipe support with anchor bolt on concerete pad','2026-10-07 11:04:10'),(163,4,42,2,'lot',55000.00,'Scaffoldings & Platforms','2026-10-07 11:04:10'),(164,4,43,3,'set/s',21000.00,'Sensing Line Assembly','2026-10-07 11:04:10'),(165,4,44,3,'no/s',5000.00,'Sight Glass','2026-10-07 11:04:10'),(166,4,45,1,'no/s',1800.00,'Sight Glass, 25mmØ','2026-10-07 11:04:10'),(167,4,45,3,'no/s',2000.00,'Sight Glass, 25mmØ','2026-10-07 11:04:10'),(168,4,46,2,'lot',230000.00,'Site Expenses, Accomodation & Temfacil','2026-10-07 11:04:10'),(169,4,47,2,'lot',550000.00,'Site Supervision & Management','2026-10-07 11:04:10'),(170,4,48,20,'no/s',1950.00,'Slip-On Flange, 100mmØ','2026-10-07 11:04:10'),(171,4,49,50,'no/s',2200.00,'Slip-On Flange, 150mmØ','2026-10-07 11:04:10'),(172,4,49,9,'no/s',2200.00,'Slip-On Flange, 150mmØ','2026-10-07 11:04:10'),(173,4,49,12,'no/s',1750.00,'Slip-On Flange, 150mmØ','2026-10-07 11:04:10'),(174,4,50,5,'no/s',3500.00,'Slip-On Flange, 200mmØ','2026-10-07 11:04:10'),(175,4,51,3,'no/s',11000.00,'Supervisory Switch','2026-10-07 11:04:10'),(176,4,51,3,'no/s',11000.00,'Supervisory Switch','2026-10-07 11:04:10'),(177,4,52,1,'lot',275000.00,'Testing / Commissioning','2026-10-07 11:04:10'),(178,4,53,1,'lot',950000.00,'Vertical In-Line JockeyPump, 30GPM, 110PSI, 5HP, 380V/3HP/60HZ complete with Jockey Pump Controller & Accessories.','2026-10-07 11:04:11'),(179,4,68,2,'no/s',4525000.00,'Vertical Turbine Diesel Engine Fire Pump, 750GPM, 100PSI, 125HP, 220V complete with Fire Pump Controller & Accessories such as Fuel Tank 130 Gallons.','2026-10-07 11:04:11'),(180,4,54,2,'no/s',3125000.00,'Vertical Turbine Motor Driven Fire Pump, 750GPM, 100PSI, 125HP, 380-400V/3PH/60HZ complete with Fire Pump Controller & Accessories.','2026-10-07 11:04:11'),(181,4,55,3,'lot',45000.00,'Wall Chipping, Pipe Sleeving, Grouting & Sealing','2026-10-07 11:04:11'),(182,4,56,2,'lot',55000.00,'Waste & Debris Disposal','2026-10-07 11:04:11'),(183,4,57,15,'m',4000.00,'ø100mm','2026-10-07 11:04:11'),(184,4,57,3,'no/s',2250.00,'ø100mm','2026-10-07 11:04:11'),(185,4,57,500,'m',1500.00,'ø100mm','2026-10-07 11:04:11'),(186,4,57,22,'no/s',1600.00,'ø100mm','2026-10-07 11:04:11'),(187,4,57,15,'no/s',1600.00,'ø100mm','2026-10-07 11:04:11'),(188,4,57,125,'m',3600.00,'ø100mm','2026-10-07 11:04:11'),(189,4,58,3,'no/s',3300.00,'ø100mm x ø150mm','2026-10-07 11:04:11'),(190,4,59,28,'no/s',2100.00,'ø100mm x ø65mm','2026-10-07 11:04:11'),(191,4,60,35,'m',5500.00,'ø150mm','2026-10-07 11:04:11'),(192,4,60,20,'no/s',2350.00,'ø150mm','2026-10-07 11:04:11'),(193,4,60,15,'no/s',3800.00,'ø150mm','2026-10-07 11:04:11'),(194,4,60,800,'m',3000.00,'ø150mm','2026-10-07 11:04:11'),(195,4,60,15,'no/s',3700.00,'ø150mm','2026-10-07 11:04:11'),(196,4,60,12,'no/s',2700.00,'ø150mm','2026-10-07 11:04:11'),(197,4,61,35,'no/s',4700.00,'ø150mm x ø100mm','2026-10-07 11:04:11'),(198,4,62,220,'m',750.00,'ø25mm','2026-10-07 11:04:11'),(199,4,63,115,'m',650.00,'ø32mm','2026-10-07 11:04:11'),(200,4,64,3,'no/s',1000.00,'ø32mm x ø50mm','2026-10-07 11:04:11'),(201,4,65,105,'m',800.00,'ø40mm','2026-10-07 11:04:11'),(202,4,66,8,'m',1000.00,'ø50mm','2026-10-07 11:04:11'),(203,4,66,3,'no/s',1000.00,'ø50mm','2026-10-07 11:04:11'),(204,4,66,30,'m',1100.00,'ø50mm','2026-10-07 11:04:11'),(205,4,67,70,'m',850.00,'ø65mm','2026-10-07 11:04:11'),(206,4,67,55,'no/s',850.00,'ø65mm','2026-10-07 11:04:11'),(207,4,67,13,'no/s',800.00,'ø65mm','2026-10-07 11:04:11'),(208,4,67,25,'m',1600.00,'ø65mm','2026-10-07 11:04:11');
/*!40000 ALTER TABLE `purchase_request_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_requests`
--

DROP TABLE IF EXISTS `purchase_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pr_number` varchar(50) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `requestor_id` bigint(20) unsigned NOT NULL,
  `purpose` text NOT NULL,
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `status` enum('draft','pending','approved','confirmed','rejected','ordered','received','completed') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `confirmed_by` int(11) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_number` (`pr_number`),
  KEY `idx_pr_project` (`project_id`),
  KEY `idx_pr_requestor` (`requestor_id`),
  KEY `idx_pr_approver` (`approved_by`),
  KEY `idx_pr_creator` (`created_by`),
  CONSTRAINT `fk_pr_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pr_requestor` FOREIGN KEY (`requestor_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_requests`
--

LOCK TABLES `purchase_requests` WRITE;
/*!40000 ALTER TABLE `purchase_requests` DISABLE KEYS */;
INSERT INTO `purchase_requests` VALUES (1,'PR-202610-0001',NULL,17,'Material request for PRJ-2026-008 - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','medium','received',660000.00,11,'2026-10-02 20:47:48',11,'2026-10-02 21:03:38',17,'2026-10-02 12:42:46','2026-10-02 13:06:18'),(2,'PR-202610-0002',10,17,'Material request for PRJ-2026-001 - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','medium','completed',14999.94,11,'2026-10-02 22:29:42',11,'2026-10-02 22:37:55',17,'2026-10-02 14:28:40','2026-10-02 15:09:45'),(3,'PR-202610-0003',12,1,'Material request for PRJ-2026-003 - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','medium','pending',33915190.00,NULL,NULL,NULL,NULL,1,'2026-10-03 01:46:32','2026-10-03 01:46:32'),(4,'PR-202610-0004',17,17,'Material request for PRJ-2026-008 - DESIGN AND BUILD OF FIRE PROTECTION SYSTEM','medium','approved',42965190.00,11,'2026-10-07 19:20:47',NULL,NULL,17,'2026-10-07 11:04:10','2026-10-07 11:20:47');
/*!40000 ALTER TABLE `purchase_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotation_items`
--

DROP TABLE IF EXISTS `quotation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotation_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_id` bigint(20) unsigned NOT NULL,
  `purchase_request_item_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `unit_price` decimal(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_qi_quotation` (`quotation_id`),
  KEY `idx_qi_pr_item` (`purchase_request_item_id`),
  KEY `idx_qi_material` (`material_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotation_items`
--

LOCK TABLES `quotation_items` WRITE;
/*!40000 ALTER TABLE `quotation_items` DISABLE KEYS */;
INSERT INTO `quotation_items` VALUES (1,2,6,2,3,'no/s',2500.00),(2,2,7,2,3,'no/s',2499.98);
/*!40000 ALTER TABLE `quotation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quotations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `quotation_number` varchar(50) NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `purchase_request_id` bigint(20) unsigned DEFAULT NULL,
  `supplier` varchar(200) NOT NULL,
  `supplier_contact` varchar(150) DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `status` enum('requested','received','accepted','rejected') NOT NULL DEFAULT 'requested',
  `total_amount` decimal(15,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_quotation_number` (`quotation_number`),
  KEY `idx_quotations_project` (`project_id`),
  KEY `idx_quotations_creator` (`created_by`),
  KEY `idx_quotations_pr` (`purchase_request_id`),
  CONSTRAINT `fk_quotations_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_quotations_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotations`
--

LOCK TABLES `quotations` WRITE;
/*!40000 ALTER TABLE `quotations` DISABLE KEYS */;
INSERT INTO `quotations` VALUES (2,'QT-202610-0001',10,2,'Villanueva Joram S','Villanueva Joram S','2026-10-31','accepted',14999.94,'',11,'2026-10-02 14:30:49','2026-10-02 14:47:52');
/*!40000 ALTER TABLE `quotations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `movement_type` enum('in','out','adjustment') NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_movements_material` (`material_id`),
  KEY `idx_movements_creator` (`created_by`),
  KEY `idx_movements_project` (`project_id`),
  CONSTRAINT `fk_movements_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_movements_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (1,1,NULL,NULL,'out',2,'Issued to Project: 1 - na',10,'2026-09-25 15:53:53'),(2,2,NULL,NULL,'in',3,'Received PO PO-202610-0002',10,'2026-10-02 13:06:18'),(3,2,NULL,NULL,'in',3,'Received PO PO-202610-0002',10,'2026-10-02 13:06:18'),(4,2,NULL,NULL,'in',3,'Received PO PO-202610-0002',10,'2026-10-02 13:06:18'),(5,2,NULL,NULL,'in',3,'Received PO PO-202610-0002',10,'2026-10-02 13:06:18'),(6,3,NULL,NULL,'in',400,'Received PO PO-202610-0002',10,'2026-10-02 13:06:18'),(7,2,NULL,'FIFO-20261002-006','in',3,'Received PO PO-202610-0005',10,'2026-10-02 14:52:58'),(8,2,NULL,'FIFO-20261002-007','in',3,'Received PO PO-202610-0005',10,'2026-10-02 14:52:58'),(9,2,10,'FIFO-20261002-001','out',3,'Released to project site PRJ-2026-001 DESIGN AND BUILD OF FIRE PROTECTION SYSTEM @ 66.5 MLD SEAWATER DESALINATION FACILITY at Brgy. Ingore, La Paz, Iloilo',10,'2026-10-02 15:02:59'),(10,2,10,'FIFO-20261002-002','out',3,'Released to project site PRJ-2026-001 DESIGN AND BUILD OF FIRE PROTECTION SYSTEM @ 66.5 MLD SEAWATER DESALINATION FACILITY at Brgy. Ingore, La Paz, Iloilo',10,'2026-10-02 15:02:59');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(200) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `tin_number` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_suppliers_creator` (`created_by`),
  CONSTRAINT `fk_suppliers_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'garlan','john','09913493193','januvillanueva@gmail.com','main road','0000000000000000000000','active',11,'2026-09-25 14:18:24',NULL),(2,'joram','john','09312082603','marojvillanue909@gmail.com','monumento','00000000000000','active',11,'2026-09-25 16:03:13',NULL),(3,'Villanueva Joram S','Villanueva Joram S','+639615448081','joramvillanueva02@gmail.com','','','active',11,'2026-09-28 13:27:41',NULL);
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(200) NOT NULL,
  `department` enum('admin','procurement','engineering','projects','accounting','warehouse') NOT NULL,
  `role` enum('admin','manager','staff') NOT NULL DEFAULT 'staff',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$0uFeIyy5iPLHEa5f0m8v5O9RHMW1evz/rQ4U7/Zmj6Hc45g7XnYea','Executive Admin','joramvillanueva02+admin@gmail.com','admin','admin','active','2026-09-03 08:31:53','2026-09-25 13:29:27'),(10,'warehouse','$2y$10$RhpyHLhz4HXPjxq7QHrrbu4LvNxv2g7Z7KwaD0wwHU2imo3GU.Rzy','marojvillanueva','marojvillanue909@gmail.com','warehouse','manager','active','2026-09-16 02:01:07',NULL),(11,'procurement','$2y$10$o5J.MslxJKg4XuTUVbnNueG4WJ79w1u/ZV1OdPJyx.UtNydBTn.ky','johnpaulgarlan','johnpaulgarlan6@gmail.com','procurement','manager','active','2026-09-19 05:07:02',NULL),(12,'accounting','$2y$10$sEd.N4gX56AwCFNigz0ycO0Li2ME4G4Lk2TBNhvqeIycaxJkMgR1q','joram villanueva','joramvillanueva30@gmail.com','accounting','manager','active','2026-09-24 15:35:48',NULL),(17,'engineering','$2y$10$4T.yk3IRRL0S1R4dATrb1eqXhK4rWlUQhtxjHwXYi6bFH4PdXNwJy','johnpaul','marksanchez7771@gmail.com','engineering','manager','active','2026-09-25 10:09:37',NULL),(18,'joram','$2y$10$AHICTywuXMuORoA/LUtj.eWxJ1itTt/vTkws7uC9meQYJ/8H9sUAG','Joram Villanueva','garlanj8@gmail.com','engineering','admin','active','2026-10-03 00:45:35',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouse_releases`
--

DROP TABLE IF EXISTS `warehouse_releases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouse_releases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_request_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned NOT NULL,
  `engineer_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `status` enum('released','received') NOT NULL DEFAULT 'released',
  `released_by` bigint(20) unsigned DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_wr_request` (`material_request_id`),
  KEY `idx_wr_project` (`project_id`),
  KEY `idx_wr_engineer` (`engineer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouse_releases`
--

LOCK TABLES `warehouse_releases` WRITE;
/*!40000 ALTER TABLE `warehouse_releases` DISABLE KEYS */;
/*!40000 ALTER TABLE `warehouse_releases` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `warehouse_stock`
--

DROP TABLE IF EXISTS `warehouse_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `warehouse_stock` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_id` bigint(20) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `location` varchar(150) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_material` (`material_id`),
  KEY `idx_stock_creator` (`created_by`),
  CONSTRAINT `fk_stock_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_material` FOREIGN KEY (`material_id`) REFERENCES `materials` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `warehouse_stock`
--

LOCK TABLES `warehouse_stock` WRITE;
/*!40000 ALTER TABLE `warehouse_stock` DISABLE KEYS */;
INSERT INTO `warehouse_stock` VALUES (1,1,1,NULL,'FIFO-20260919-001',NULL,'Opening balance for FIFO',NULL,'2026-09-19 04:50:41'),(2,2,0,NULL,'FIFO-20261002-001',NULL,'Received PO PO-202610-0002',NULL,'2026-10-02 13:06:18'),(3,2,0,NULL,'FIFO-20261002-002',NULL,'Received PO PO-202610-0002',NULL,'2026-10-02 13:06:18'),(4,2,3,NULL,'FIFO-20261002-003',NULL,'Received PO PO-202610-0002',NULL,'2026-10-02 13:06:18'),(5,2,3,NULL,'FIFO-20261002-004',NULL,'Received PO PO-202610-0002',NULL,'2026-10-02 13:06:18'),(6,3,400,NULL,'FIFO-20261002-005',NULL,'Received PO PO-202610-0002',NULL,'2026-10-02 13:06:18'),(7,2,3,NULL,'FIFO-20261002-006',NULL,NULL,10,'2026-10-02 14:52:58'),(8,2,3,NULL,'FIFO-20261002-007',NULL,NULL,10,'2026-10-02 14:52:58'),(9,68,4,NULL,'FIFO-20261007-001',NULL,'Opening balance for FIFO',NULL,'2026-10-07 11:04:11');
/*!40000 ALTER TABLE `warehouse_stock` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-07 20:38:05
