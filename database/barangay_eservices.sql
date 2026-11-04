-- Barangay E-Services and Community Concern Management System
-- Database export: structure + sample records. Import in phpMyAdmin (Import tab) or:
--   C:/xampp/mysql/bin/mysql.exe -u root < barangay_eservices.sql
-- All demo accounts use the password Demo@2026 (see DEMO_CREDENTIALS.txt).
-- No specific barangay is set; the administrator can enter one under Settings.

-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: barangay_eservices
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
-- Current Database: `barangay_eservices`
--

/*!40000 DROP DATABASE IF EXISTS `barangay_eservices`*/;

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `barangay_eservices` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `barangay_eservices`;

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` int(10) unsigned DEFAULT NULL,
  `details` varchar(500) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `fk_log_user` (`user_id`),
  KEY `idx_log_created` (`created_at`),
  KEY `idx_log_action` (`action`),
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` (`log_id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES (1,1,'user_created','user',2,'staff@barangayservice.com created as staff','127.0.0.1','2026-08-07 22:07:46'),(2,1,'user_created','user',3,'staff2@barangayservice.com created as staff','127.0.0.1','2026-08-07 22:07:46'),(3,1,'user_created','user',4,'staff3@barangayservice.com created as staff','127.0.0.1','2026-08-08 22:07:46'),(4,1,'document_type_created','document_type',6,'First-Time Jobseeker Certificate','127.0.0.1','2026-08-16 22:07:46'),(5,2,'login','user',2,'Signed in','127.0.0.1','2026-11-03 22:07:46'),(6,1,'login','user',1,'Signed in','127.0.0.1','2026-11-04 22:07:46'),(7,2,'request_status_changed','document_request',1,'REQ-2026-00001: -> Released','127.0.0.1','2026-11-04 22:07:47'),(8,3,'request_status_changed','document_request',2,'REQ-2026-00002: -> Released','127.0.0.1','2026-11-04 22:07:47'),(9,2,'request_status_changed','document_request',3,'REQ-2026-00003: -> Released','127.0.0.1','2026-10-10 07:11:52'),(10,2,'request_status_changed','document_request',4,'REQ-2026-00004: -> Released','127.0.0.1','2026-10-17 15:23:31'),(11,2,'request_status_changed','document_request',5,'REQ-2026-00005: -> Released','127.0.0.1','2026-10-25 02:35:51'),(12,3,'request_status_changed','document_request',6,'REQ-2026-00006: -> Released','127.0.0.1','2026-10-29 15:39:58'),(13,4,'request_status_changed','document_request',7,'REQ-2026-00007: -> Released','127.0.0.1','2026-11-02 04:02:35'),(14,4,'request_status_changed','document_request',8,'REQ-2026-00008: -> Rejected','127.0.0.1','2026-10-07 10:43:23'),(15,2,'request_status_changed','document_request',9,'REQ-2026-00009: -> Rejected','127.0.0.1','2026-10-24 11:06:57'),(16,4,'request_status_changed','document_request',12,'REQ-2026-00012: -> Ready for Pickup','127.0.0.1','2026-11-01 04:03:46'),(17,3,'request_status_changed','document_request',13,'REQ-2026-00013: -> Ready for Pickup','127.0.0.1','2026-11-03 03:12:02'),(18,2,'request_status_changed','document_request',14,'REQ-2026-00014: -> Processing','127.0.0.1','2026-11-03 11:30:14'),(19,3,'request_status_changed','document_request',15,'REQ-2026-00015: -> Processing','127.0.0.1','2026-11-04 19:49:42'),(20,3,'request_status_changed','document_request',16,'REQ-2026-00016: -> Approved','127.0.0.1','2026-11-04 04:42:35'),(21,2,'request_status_changed','document_request',17,'REQ-2026-00017: -> Under Review','127.0.0.1','2026-11-04 02:08:15'),(22,3,'request_status_changed','document_request',18,'REQ-2026-00018: -> Under Review','127.0.0.1','2026-11-04 13:52:41'),(23,2,'concern_status_changed','concern',7,'CON-2026-00007: -> Assigned','127.0.0.1','2026-11-03 20:10:09'),(24,3,'concern_status_changed','concern',8,'CON-2026-00008: -> In Progress','127.0.0.1','2026-10-29 21:24:08'),(25,4,'concern_status_changed','concern',9,'CON-2026-00009: -> In Progress','127.0.0.1','2026-11-01 10:44:18'),(26,2,'concern_status_changed','concern',10,'CON-2026-00010: -> Resolved','127.0.0.1','2026-10-24 04:32:49'),(27,3,'concern_status_changed','concern',11,'CON-2026-00011: -> Closed','127.0.0.1','2026-10-15 10:51:08'),(28,4,'concern_status_changed','concern',12,'CON-2026-00012: -> Closed','127.0.0.1','2026-10-19 21:17:57');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `barangay_settings`
--

DROP TABLE IF EXISTS `barangay_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `barangay_settings` (
  `settings_id` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `barangay_name` varchar(100) DEFAULT NULL,
  `municipality` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `office_hours` varchar(150) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`settings_id`),
  KEY `fk_settings_user` (`updated_by`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`updated_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `chk_settings_single` CHECK (`settings_id` = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `barangay_settings`
--

LOCK TABLES `barangay_settings` WRITE;
/*!40000 ALTER TABLE `barangay_settings` DISABLE KEYS */;
INSERT INTO `barangay_settings` (`settings_id`, `barangay_name`, `municipality`, `province`, `office_hours`, `logo_path`, `updated_by`, `updated_at`) VALUES (1,NULL,NULL,NULL,'Monday to Friday, 8:00 AM to 5:00 PM',NULL,NULL,'2026-11-04 22:07:46');
/*!40000 ALTER TABLE `barangay_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `concern_categories`
--

DROP TABLE IF EXISTS `concern_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `concern_categories` (
  `category_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) NOT NULL,
  `default_priority` tinyint(3) unsigned NOT NULL DEFAULT 2,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`category_id`),
  UNIQUE KEY `name` (`name`),
  CONSTRAINT `chk_cc_priority` CHECK (`default_priority` between 1 and 4)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `concern_categories`
--

LOCK TABLES `concern_categories` WRITE;
/*!40000 ALTER TABLE `concern_categories` DISABLE KEYS */;
INSERT INTO `concern_categories` (`category_id`, `name`, `description`, `default_priority`, `is_archived`, `created_at`, `updated_at`) VALUES (1,'Improper Waste Disposal','Uncollected garbage, illegal dumping, burning of trash, or unsegregated waste.',2,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(2,'Damaged Road or Sidewalk','Potholes, cracks, eroded road shoulders or broken sidewalks.',2,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(3,'Broken Streetlight','Streetlights that are not working, flickering or damaged.',2,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(4,'Clogged Drainage or Flooding','Blocked canals and drains, or areas that flood after rain.',3,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(5,'Fallen Tree or Electric Post Hazard','Fallen or leaning trees, sagging wires or damaged electric posts that endanger people.',4,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(6,'Noise or Public Disturbance','Excessive noise, videoke past curfew, or disturbances in public places.',1,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(7,'Stray Animals','Stray or aggressive dogs and other animals roaming in public areas.',2,0,'2026-08-06 22:07:46','2026-11-04 22:07:46');
/*!40000 ALTER TABLE `concern_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `concern_updates`
--

DROP TABLE IF EXISTS `concern_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `concern_updates` (
  `update_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `concern_id` int(10) unsigned NOT NULL,
  `from_status` varchar(20) DEFAULT NULL,
  `to_status` varchar(20) NOT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `changed_by` int(10) unsigned NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`update_id`),
  KEY `fk_cu_concern` (`concern_id`),
  KEY `fk_cu_user` (`changed_by`),
  CONSTRAINT `fk_cu_concern` FOREIGN KEY (`concern_id`) REFERENCES `concerns` (`concern_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cu_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `concern_updates`
--

LOCK TABLES `concern_updates` WRITE;
/*!40000 ALTER TABLE `concern_updates` DISABLE KEYS */;
INSERT INTO `concern_updates` (`update_id`, `concern_id`, `from_status`, `to_status`, `note`, `changed_by`, `changed_at`) VALUES (1,1,NULL,'Submitted','Concern reported',5,'2026-10-28 18:19:15'),(2,2,NULL,'Submitted','Concern reported',12,'2026-11-03 17:55:54'),(3,3,NULL,'Submitted','Concern reported',11,'2026-11-04 17:34:46'),(4,4,NULL,'Submitted','Concern reported',12,'2026-10-30 18:53:59'),(5,4,'Submitted','Under Review','Verified by phone with the resident.',2,'2026-10-31 20:53:59'),(6,5,NULL,'Submitted','Concern reported',12,'2026-11-02 16:34:15'),(7,5,'Submitted','Under Review','Verified by phone with the resident.',3,'2026-11-02 19:34:15'),(8,6,NULL,'Submitted','Concern reported',6,'2026-10-25 20:29:04'),(9,7,NULL,'Submitted','Concern reported',9,'2026-10-31 20:10:09'),(10,7,'Submitted','Under Review','Verified by phone with the resident.',2,'2026-11-01 22:10:09'),(11,7,'Under Review','Assigned','Assigned to Ramon Villanueva',2,'2026-11-02 14:10:09'),(12,8,NULL,'Submitted','Concern reported',7,'2026-10-26 17:24:08'),(13,8,'Submitted','Under Review','Verified by phone with the resident.',3,'2026-10-27 16:24:08'),(14,8,'Under Review','Assigned','Assigned to Liza Santos',3,'2026-10-27 23:24:08'),(15,8,'Assigned','In Progress','Coordinated with the barangay maintenance crew.',3,'2026-10-29 02:24:08'),(16,9,NULL,'Submitted','Concern reported',6,'2026-10-29 18:44:18'),(17,9,'Submitted','Under Review','Verified by phone with the resident.',4,'2026-10-30 08:44:18'),(18,9,'Under Review','Assigned','Assigned to Eduardo Ramos',4,'2026-10-30 19:44:18'),(19,9,'Assigned','In Progress','Coordinated with the barangay maintenance crew.',4,'2026-10-31 07:44:18'),(20,10,NULL,'Submitted','Concern reported',7,'2026-10-20 19:32:49'),(21,10,'Submitted','Under Review','Verified by phone with the resident.',2,'2026-10-22 00:32:49'),(22,10,'Under Review','Assigned','Assigned to Ramon Villanueva',2,'2026-10-22 21:32:49'),(23,10,'Assigned','In Progress','Coordinated with the barangay maintenance crew.',2,'2026-10-23 04:32:49'),(24,10,'In Progress','Resolved','The barangay maintenance crew repaired the problem and cleaned the area. Photos are on file at the barangay hall.',2,'2026-10-23 07:32:49'),(25,11,NULL,'Submitted','Concern reported',7,'2026-10-10 20:51:08'),(26,11,'Submitted','Under Review','Verified by phone with the resident.',3,'2026-10-11 20:51:08'),(27,11,'Under Review','Assigned','Assigned to Liza Santos',3,'2026-10-12 22:51:08'),(28,11,'Assigned','In Progress','Coordinated with the barangay maintenance crew.',3,'2026-10-13 12:51:08'),(29,11,'In Progress','Resolved','The barangay maintenance crew repaired the problem and cleaned the area. Photos are on file at the barangay hall.',3,'2026-10-14 09:51:08'),(30,11,'Resolved','Closed','Confirmed fixed by the resident.',7,'2026-10-15 01:51:08'),(31,12,NULL,'Submitted','Concern reported',11,'2026-10-15 16:17:57'),(32,12,'Submitted','Under Review','Verified by phone with the resident.',4,'2026-10-16 13:17:57'),(33,12,'Under Review','Assigned','Assigned to Eduardo Ramos',4,'2026-10-16 16:17:57'),(34,12,'Assigned','In Progress','Coordinated with the barangay maintenance crew.',4,'2026-10-17 14:17:57'),(35,12,'In Progress','Resolved','The barangay maintenance crew repaired the problem and cleaned the area. Photos are on file at the barangay hall.',4,'2026-10-18 10:17:57'),(36,12,'Resolved','Closed','Confirmed fixed by the resident.',11,'2026-10-18 19:17:57'),(37,13,NULL,'Submitted','Concern reported',10,'2026-10-22 20:32:56'),(38,13,'Submitted','Under Review','Verified by phone with the resident.',2,'2026-10-24 02:32:56'),(39,13,'Under Review','Rejected','This is a traffic matter under the municipal traffic office. The report was forwarded to them.',2,'2026-10-24 11:32:56'),(40,14,NULL,'Submitted','Concern reported',11,'2026-10-24 17:06:47'),(41,14,'Submitted','Withdrawn','Already fixed by the electric cooperative.',11,'2026-10-25 07:06:47');
/*!40000 ALTER TABLE `concern_updates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `concerns`
--

DROP TABLE IF EXISTS `concerns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `concerns` (
  `concern_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(20) DEFAULT NULL,
  `resident_id` int(10) unsigned NOT NULL,
  `category_id` smallint(5) unsigned NOT NULL,
  `purok_id` smallint(5) unsigned NOT NULL,
  `title` varchar(120) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(200) NOT NULL,
  `priority` tinyint(3) unsigned NOT NULL,
  `status` enum('Submitted','Under Review','Assigned','In Progress','Resolved','Closed','Rejected','Withdrawn') NOT NULL DEFAULT 'Submitted',
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `resolution` varchar(1000) DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`concern_id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `fk_c_resident` (`resident_id`),
  KEY `fk_c_category` (`category_id`),
  KEY `fk_c_purok` (`purok_id`),
  KEY `fk_c_assignee` (`assigned_to`),
  KEY `idx_c_status` (`status`),
  KEY `idx_c_created` (`created_at`),
  CONSTRAINT `fk_c_assignee` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_c_category` FOREIGN KEY (`category_id`) REFERENCES `concern_categories` (`category_id`),
  CONSTRAINT `fk_c_purok` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`purok_id`),
  CONSTRAINT `fk_c_resident` FOREIGN KEY (`resident_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `chk_c_priority` CHECK (`priority` between 1 and 4)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `concerns`
--

LOCK TABLES `concerns` WRITE;
/*!40000 ALTER TABLE `concerns` DISABLE KEYS */;
INSERT INTO `concerns` (`concern_id`, `reference_no`, `resident_id`, `category_id`, `purok_id`, `title`, `description`, `location`, `priority`, `status`, `assigned_to`, `resolution`, `is_archived`, `created_at`, `updated_at`) VALUES (1,'CON-2026-00001',5,1,2,'Garbage not collected for a week','Garbage bags have piled up beside the court since last Monday. Dogs are tearing them open and the smell reaches the houses nearby.','Beside the basketball court, Mabini St.',2,'Submitted',NULL,NULL,0,'2026-10-28 18:19:15','2026-10-28 21:19:15'),(2,'CON-2026-00002',12,3,1,'Streetlight not working near the chapel','The streetlight in front of the chapel has been off for two weeks. The street is very dark at night and residents walking home from the terminal feel unsafe.','In front of the barangay chapel, Rizal St.',2,'Submitted',NULL,NULL,0,'2026-11-03 17:55:54','2026-11-04 10:55:54'),(3,'CON-2026-00003',11,5,4,'Leaning electric post after the storm','An electric post is leaning toward the road after last night\'s strong winds. Wires are sagging low over the street where children pass on the way to school.','Corner of Luna St. and Del Pilar St.',4,'Submitted',NULL,NULL,0,'2026-11-04 17:34:46','2026-11-04 21:57:46'),(4,'CON-2026-00004',12,2,3,'Deep pothole on Bonifacio St.','There is a large pothole in the middle of the road that fills with water when it rains. Two tricycles have already had flat tires.','Near house no. 20, Bonifacio St.',2,'Under Review',NULL,NULL,0,'2026-10-30 18:53:59','2026-11-01 07:53:59'),(5,'CON-2026-00005',12,4,5,'Clogged canal causes flooding','The canal is full of plastic and silt. Even light rain floods the street in front of the school up to the ankles.','Canal behind Del Pilar Elementary School',3,'Under Review',NULL,NULL,0,'2026-11-02 16:34:15','2026-11-03 22:34:15'),(6,'CON-2026-00006',6,6,6,'Videoke past 10 PM every weekend','A household plays videoke at very high volume until 2 AM every Saturday, past the barangay curfew on noise.','Burgos St., near the sari-sari store',1,'Submitted',NULL,NULL,0,'2026-10-25 20:29:04','2026-10-26 03:29:04'),(7,'CON-2026-00007',9,7,2,'Aggressive stray dogs near the school','A pack of four stray dogs chases students in the morning. One child was nearly bitten yesterday.','Mabini St. entrance of the elementary school',3,'Assigned',2,NULL,0,'2026-10-31 20:10:09','2026-11-03 20:10:09'),(8,'CON-2026-00008',7,1,4,'Illegal dumping on vacant lot','People are throwing construction debris and household garbage on the vacant lot at night.','Vacant lot beside 90 Luna St.',2,'In Progress',3,NULL,0,'2026-10-26 17:24:08','2026-10-29 21:24:08'),(9,'CON-2026-00009',6,3,3,'Flickering streetlights along Bonifacio St.','Three streetlights keep flickering on and off at night.','Bonifacio St., from no. 1 to no. 30',2,'In Progress',4,NULL,0,'2026-10-29 18:44:18','2026-11-01 10:44:18'),(10,'CON-2026-00010',7,2,1,'Broken sidewalk near the health center','The sidewalk slabs are broken and lifted. Elderly patients have tripped while going to the health center.','In front of the Barangay Health Center',3,'Resolved',2,'The barangay maintenance crew repaired the problem and cleaned the area. Photos are on file at the barangay hall.',0,'2026-10-20 19:32:49','2026-10-24 04:32:49'),(11,'CON-2026-00011',7,4,6,'Drainage cover missing','The concrete cover of the drainage is missing, leaving an open hole at the corner.','Burgos St. corner Rizal St.',4,'Closed',3,'The barangay maintenance crew repaired the problem and cleaned the area. Photos are on file at the barangay hall.',0,'2026-10-10 20:51:08','2026-10-15 10:51:08'),(12,'CON-2026-00012',11,1,5,'Burning of garbage in backyard','A neighbor burns plastic garbage every morning and the smoke enters our house.','Behind 3 Del Pilar St.',2,'Closed',4,'The barangay maintenance crew repaired the problem and cleaned the area. Photos are on file at the barangay hall.',0,'2026-10-15 16:17:57','2026-10-19 21:17:57'),(13,'CON-2026-00013',10,6,2,'Loud motorcycles at night','Motorcycles with modified mufflers pass at high speed late at night.','Mabini St.',1,'Rejected',NULL,NULL,0,'2026-10-22 20:32:56','2026-10-24 22:32:56'),(14,'CON-2026-00014',11,3,4,'Streetlight on Luna St. out','The streetlight is not working.','In front of 88 Luna St.',2,'Withdrawn',NULL,NULL,0,'2026-10-24 17:06:47','2026-10-25 17:06:47');
/*!40000 ALTER TABLE `concerns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_requests`
--

DROP TABLE IF EXISTS `document_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_requests` (
  `request_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(20) DEFAULT NULL,
  `resident_id` int(10) unsigned NOT NULL,
  `doc_type_id` smallint(5) unsigned NOT NULL,
  `purpose` varchar(255) NOT NULL,
  `copies` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `fee_amount` decimal(8,2) NOT NULL,
  `status` enum('Pending','Under Review','Approved','Rejected','Processing','Ready for Pickup','Released','Cancelled') NOT NULL DEFAULT 'Pending',
  `remarks` varchar(500) DEFAULT NULL,
  `handled_by` int(10) unsigned DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`request_id`),
  UNIQUE KEY `reference_no` (`reference_no`),
  KEY `fk_dr_resident` (`resident_id`),
  KEY `fk_dr_doctype` (`doc_type_id`),
  KEY `fk_dr_handler` (`handled_by`),
  KEY `idx_dr_status` (`status`),
  KEY `idx_dr_created` (`created_at`),
  CONSTRAINT `fk_dr_doctype` FOREIGN KEY (`doc_type_id`) REFERENCES `document_types` (`doc_type_id`),
  CONSTRAINT `fk_dr_handler` FOREIGN KEY (`handled_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_dr_resident` FOREIGN KEY (`resident_id`) REFERENCES `users` (`user_id`),
  CONSTRAINT `chk_dr_copies` CHECK (`copies` between 1 and 5)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_requests`
--

LOCK TABLES `document_requests` WRITE;
/*!40000 ALTER TABLE `document_requests` DISABLE KEYS */;
INSERT INTO `document_requests` (`request_id`, `reference_no`, `resident_id`, `doc_type_id`, `purpose`, `copies`, `fee_amount`, `status`, `remarks`, `handled_by`, `is_archived`, `created_at`, `updated_at`) VALUES (1,'REQ-2026-00001',10,2,'Medical assistance at the provincial hospital',1,30.00,'Released','Signed by the Punong Barangay. Ready at the front desk.',2,1,'2026-09-25 18:03:07','2026-11-04 22:07:47'),(2,'REQ-2026-00002',10,5,'Employment requirement',2,80.00,'Released','Signed by the Punong Barangay. Ready at the front desk.',3,1,'2026-09-30 20:21:52','2026-11-04 22:07:47'),(3,'REQ-2026-00003',5,3,'Business permit renewal',1,0.00,'Released','Signed by the Punong Barangay. Ready at the front desk.',2,0,'2026-10-07 17:11:52','2026-10-10 07:11:52'),(4,'REQ-2026-00004',8,4,'Job application abroad',1,300.00,'Released','Signed by the Punong Barangay. Ready at the front desk.',2,0,'2026-10-14 18:23:31','2026-10-17 15:23:31'),(5,'REQ-2026-00005',10,1,'Employment requirement',1,50.00,'Released','Signed by the Punong Barangay. Ready at the front desk.',2,0,'2026-10-21 20:35:51','2026-10-25 02:35:51'),(6,'REQ-2026-00006',8,2,'Job application abroad',1,30.00,'Released','Signed by the Punong Barangay. Ready at the front desk.',3,0,'2026-10-26 16:39:58','2026-10-29 15:39:58'),(7,'REQ-2026-00007',5,1,'Medical assistance at the provincial hospital',1,50.00,'Released','Signed by the Punong Barangay. Ready at the front desk.',4,0,'2026-10-30 15:02:35','2026-11-02 04:02:35'),(8,'REQ-2026-00008',5,4,'Scholarship application',1,300.00,'Rejected','The Community Tax Certificate presented is from the previous year. Please secure a current cedula and submit a new request.',4,0,'2026-10-05 19:43:23','2026-10-07 10:43:23'),(9,'REQ-2026-00009',12,6,'School enrollment',1,0.00,'Rejected','The Community Tax Certificate presented is from the previous year. Please secure a current cedula and submit a new request.',2,0,'2026-10-23 15:06:57','2026-10-24 11:06:57'),(10,'REQ-2026-00010',6,5,'School enrollment',1,40.00,'Cancelled','No longer needed',NULL,0,'2026-10-17 19:05:11','2026-10-18 17:05:11'),(11,'REQ-2026-00011',8,5,'Bank loan application',1,40.00,'Cancelled','No longer needed',NULL,0,'2026-11-01 17:11:32','2026-11-03 01:11:32'),(12,'REQ-2026-00012',8,6,'Employment requirement',1,0.00,'Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',4,0,'2026-10-29 20:03:46','2026-11-01 04:03:46'),(13,'REQ-2026-00013',8,5,'Police clearance requirement',2,80.00,'Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',3,0,'2026-10-31 16:12:02','2026-11-03 03:12:02'),(14,'REQ-2026-00014',6,1,'Employment requirement',1,50.00,'Processing',NULL,2,0,'2026-11-01 15:30:14','2026-11-03 11:30:14'),(15,'REQ-2026-00015',6,4,'Business permit renewal',1,300.00,'Processing',NULL,3,0,'2026-11-02 19:49:42','2026-11-04 19:49:42'),(16,'REQ-2026-00016',5,1,'Employment requirement',1,50.00,'Approved',NULL,3,0,'2026-11-02 15:42:35','2026-11-04 04:42:35'),(17,'REQ-2026-00017',9,6,'Bank loan application',1,0.00,'Under Review',NULL,2,0,'2026-11-03 15:08:15','2026-11-04 02:08:15'),(18,'REQ-2026-00018',5,5,'Bank loan application',1,40.00,'Under Review',NULL,3,0,'2026-11-03 19:52:41','2026-11-04 13:52:41'),(19,'REQ-2026-00019',7,1,'Postal ID application',2,100.00,'Pending',NULL,NULL,0,'2026-11-03 19:21:37','2026-11-04 09:21:37'),(20,'REQ-2026-00020',5,4,'Job application abroad',2,600.00,'Pending',NULL,NULL,0,'2026-11-04 15:24:46','2026-11-04 21:57:46'),(21,'REQ-2026-00021',12,4,'Business permit renewal',1,300.00,'Pending',NULL,NULL,0,'2026-11-04 15:07:43','2026-11-04 21:57:46');
/*!40000 ALTER TABLE `document_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_requirements`
--

DROP TABLE IF EXISTS `document_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_requirements` (
  `requirement_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `doc_type_id` smallint(5) unsigned NOT NULL,
  `requirement_text` varchar(200) NOT NULL,
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`requirement_id`),
  KEY `fk_req_doctype` (`doc_type_id`),
  CONSTRAINT `fk_req_doctype` FOREIGN KEY (`doc_type_id`) REFERENCES `document_types` (`doc_type_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_requirements`
--

LOCK TABLES `document_requirements` WRITE;
/*!40000 ALTER TABLE `document_requirements` DISABLE KEYS */;
INSERT INTO `document_requirements` (`requirement_id`, `doc_type_id`, `requirement_text`, `sort_order`) VALUES (1,1,'Valid government-issued ID',1),(2,1,'Community Tax Certificate (cedula) for the current year',2),(3,1,'Proof of residency of at least 6 months',3),(4,2,'Valid government-issued ID',1),(5,2,'Proof of address (utility bill or lease contract)',2),(6,3,'Valid ID of the applicant',1),(7,3,'Letter or form from the agency requesting the certificate, if any',2),(8,3,'Interview with the barangay social worker',3),(9,4,'DTI or SEC registration',1),(10,4,'Lease contract or proof of ownership of the business location',2),(11,4,'Valid ID of the owner',3),(12,4,'Previous business clearance (for renewal)',4),(13,5,'Valid ID or school ID',1),(14,5,'Recent 1x1 photo',2),(15,6,'Valid ID showing the applicant is at least 18 years old',1),(16,6,'Signed oath of undertaking (available at the barangay hall)',2);
/*!40000 ALTER TABLE `document_requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_types`
--

DROP TABLE IF EXISTS `document_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `document_types` (
  `doc_type_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(500) NOT NULL,
  `fee` decimal(8,2) NOT NULL DEFAULT 0.00,
  `processing_days` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`doc_type_id`),
  UNIQUE KEY `name` (`name`),
  CONSTRAINT `chk_dt_fee` CHECK (`fee` >= 0),
  CONSTRAINT `chk_dt_days` CHECK (`processing_days` between 1 and 30)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_types`
--

LOCK TABLES `document_types` WRITE;
/*!40000 ALTER TABLE `document_types` DISABLE KEYS */;
INSERT INTO `document_types` (`doc_type_id`, `name`, `description`, `fee`, `processing_days`, `is_archived`, `created_at`, `updated_at`) VALUES (1,'Barangay Clearance','Certifies that the resident has no derogatory record in the barangay. Commonly required for employment, business permits and loans.',50.00,1,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(2,'Certificate of Residency','Certifies that the person is a bona fide resident of the barangay and states the length of residency.',30.00,1,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(3,'Certificate of Indigency','Certifies that the resident belongs to a low-income household. Used for medical, burial, educational and legal assistance.',0.00,2,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(4,'Barangay Business Clearance','Required before the municipality issues or renews a business permit for a business located in the barangay.',300.00,3,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(5,'Certificate of Good Moral Character','Certifies the good standing of the resident in the community. Often required for school and scholarship applications.',40.00,2,0,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(6,'First-Time Jobseeker Certificate','Certification under Republic Act No. 11261 that waives government fees for first-time jobseekers. Free of charge.',0.00,1,0,'2026-08-06 22:07:46','2026-11-04 22:07:46');
/*!40000 ALTER TABLE `document_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verifications`
--

DROP TABLE IF EXISTS `email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_verifications` (
  `verification_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`verification_id`),
  UNIQUE KEY `token_hash` (`token_hash`),
  KEY `idx_ev_user` (`user_id`),
  CONSTRAINT `fk_ev_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verifications`
--

LOCK TABLES `email_verifications` WRITE;
/*!40000 ALTER TABLE `email_verifications` DISABLE KEYS */;
INSERT INTO `email_verifications` (`verification_id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES (1,13,'f92a0697c8ee8a8166ddcf88ccc736a4e6c6f405e7162984ea8f10638a5ac845','2026-11-03 23:07:46',NULL,'2026-11-03 22:07:46');
/*!40000 ALTER TABLE `email_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `puroks`
--

DROP TABLE IF EXISTS `puroks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `puroks` (
  `purok_id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `purok_name` varchar(60) NOT NULL,
  PRIMARY KEY (`purok_id`),
  UNIQUE KEY `purok_name` (`purok_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `puroks`
--

LOCK TABLES `puroks` WRITE;
/*!40000 ALTER TABLE `puroks` DISABLE KEYS */;
INSERT INTO `puroks` (`purok_id`, `purok_name`) VALUES (1,'Purok 1 - Sampaguita'),(2,'Purok 2 - Ilang-Ilang'),(3,'Purok 3 - Rosal'),(4,'Purok 4 - Camia'),(5,'Purok 5 - Dahlia'),(6,'Purok 6 - Gumamela');
/*!40000 ALTER TABLE `puroks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `request_status_history`
--

DROP TABLE IF EXISTS `request_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `request_status_history` (
  `history_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` int(10) unsigned NOT NULL,
  `from_status` varchar(20) DEFAULT NULL,
  `to_status` varchar(20) NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `changed_by` int(10) unsigned NOT NULL,
  `changed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`history_id`),
  KEY `fk_rsh_request` (`request_id`),
  KEY `fk_rsh_user` (`changed_by`),
  CONSTRAINT `fk_rsh_request` FOREIGN KEY (`request_id`) REFERENCES `document_requests` (`request_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rsh_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `request_status_history`
--

LOCK TABLES `request_status_history` WRITE;
/*!40000 ALTER TABLE `request_status_history` DISABLE KEYS */;
INSERT INTO `request_status_history` (`history_id`, `request_id`, `from_status`, `to_status`, `remarks`, `changed_by`, `changed_at`) VALUES (1,1,NULL,'Pending','Request submitted',10,'2026-09-25 18:03:07'),(2,1,'Pending','Under Review',NULL,2,'2026-09-26 00:03:07'),(3,1,'Under Review','Approved',NULL,2,'2026-09-26 06:03:07'),(4,1,'Approved','Processing',NULL,2,'2026-09-26 10:03:07'),(5,1,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',2,'2026-09-26 18:03:07'),(6,1,'Ready for Pickup','Released',NULL,2,'2026-09-27 08:03:07'),(7,2,NULL,'Pending','Request submitted',10,'2026-09-30 20:21:52'),(8,2,'Pending','Under Review',NULL,3,'2026-10-01 03:21:52'),(9,2,'Under Review','Approved',NULL,3,'2026-10-01 07:21:52'),(10,2,'Approved','Processing',NULL,3,'2026-10-01 15:21:52'),(11,2,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',3,'2026-10-02 04:21:52'),(12,2,'Ready for Pickup','Released',NULL,3,'2026-10-02 21:21:52'),(13,3,NULL,'Pending','Request submitted',5,'2026-10-07 17:11:52'),(14,3,'Pending','Under Review',NULL,2,'2026-10-07 21:11:52'),(15,3,'Under Review','Approved',NULL,2,'2026-10-08 05:11:52'),(16,3,'Approved','Processing',NULL,2,'2026-10-08 23:11:52'),(17,3,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',2,'2026-10-09 10:11:52'),(18,3,'Ready for Pickup','Released',NULL,2,'2026-10-10 03:11:52'),(19,4,NULL,'Pending','Request submitted',8,'2026-10-14 18:23:31'),(20,4,'Pending','Under Review',NULL,2,'2026-10-14 20:23:31'),(21,4,'Under Review','Approved',NULL,2,'2026-10-15 15:23:31'),(22,4,'Approved','Processing',NULL,2,'2026-10-16 01:23:31'),(23,4,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',2,'2026-10-16 20:23:31'),(24,4,'Ready for Pickup','Released',NULL,2,'2026-10-17 12:23:31'),(25,5,NULL,'Pending','Request submitted',10,'2026-10-21 20:35:51'),(26,5,'Pending','Under Review',NULL,2,'2026-10-22 10:35:51'),(27,5,'Under Review','Approved',NULL,2,'2026-10-23 03:35:51'),(28,5,'Approved','Processing',NULL,2,'2026-10-23 20:35:51'),(29,5,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',2,'2026-10-24 10:35:51'),(30,5,'Ready for Pickup','Released',NULL,2,'2026-10-24 17:35:51'),(31,6,NULL,'Pending','Request submitted',8,'2026-10-26 16:39:58'),(32,6,'Pending','Under Review',NULL,3,'2026-10-27 10:39:58'),(33,6,'Under Review','Approved',NULL,3,'2026-10-27 18:39:58'),(34,6,'Approved','Processing',NULL,3,'2026-10-28 07:39:58'),(35,6,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',3,'2026-10-29 01:39:58'),(36,6,'Ready for Pickup','Released',NULL,3,'2026-10-29 09:39:58'),(37,7,NULL,'Pending','Request submitted',5,'2026-10-30 15:02:35'),(38,7,'Pending','Under Review',NULL,4,'2026-10-31 08:02:35'),(39,7,'Under Review','Approved',NULL,4,'2026-10-31 14:02:35'),(40,7,'Approved','Processing',NULL,4,'2026-10-31 20:02:35'),(41,7,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',4,'2026-11-01 07:02:35'),(42,7,'Ready for Pickup','Released',NULL,4,'2026-11-01 19:02:35'),(43,8,NULL,'Pending','Request submitted',5,'2026-10-05 19:43:23'),(44,8,'Pending','Under Review',NULL,4,'2026-10-06 12:43:23'),(45,8,'Under Review','Rejected','The Community Tax Certificate presented is from the previous year. Please secure a current cedula and submit a new request.',4,'2026-10-06 20:43:23'),(46,9,NULL,'Pending','Request submitted',12,'2026-10-23 15:06:57'),(47,9,'Pending','Under Review',NULL,2,'2026-10-23 23:06:57'),(48,9,'Under Review','Rejected','The Community Tax Certificate presented is from the previous year. Please secure a current cedula and submit a new request.',2,'2026-10-24 09:06:57'),(49,10,NULL,'Pending','Request submitted',6,'2026-10-17 19:05:11'),(50,10,'Pending','Cancelled','No longer needed',6,'2026-10-18 12:05:11'),(51,11,NULL,'Pending','Request submitted',8,'2026-11-01 17:11:32'),(52,11,'Pending','Cancelled','No longer needed',8,'2026-11-02 11:11:32'),(53,12,NULL,'Pending','Request submitted',8,'2026-10-29 20:03:46'),(54,12,'Pending','Under Review',NULL,4,'2026-10-30 10:03:46'),(55,12,'Under Review','Approved',NULL,4,'2026-10-30 16:03:46'),(56,12,'Approved','Processing',NULL,4,'2026-10-31 01:03:46'),(57,12,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',4,'2026-10-31 13:03:46'),(58,13,NULL,'Pending','Request submitted',8,'2026-10-31 16:12:02'),(59,13,'Pending','Under Review',NULL,3,'2026-11-01 00:12:02'),(60,13,'Under Review','Approved',NULL,3,'2026-11-01 15:12:02'),(61,13,'Approved','Processing',NULL,3,'2026-11-02 09:12:02'),(62,13,'Processing','Ready for Pickup','Signed by the Punong Barangay. Ready at the front desk.',3,'2026-11-02 14:12:02'),(63,14,NULL,'Pending','Request submitted',6,'2026-11-01 15:30:14'),(64,14,'Pending','Under Review',NULL,2,'2026-11-02 02:30:14'),(65,14,'Under Review','Approved',NULL,2,'2026-11-02 16:30:14'),(66,14,'Approved','Processing',NULL,2,'2026-11-03 07:30:14'),(67,15,NULL,'Pending','Request submitted',6,'2026-11-02 19:49:42'),(68,15,'Pending','Under Review',NULL,3,'2026-11-03 02:49:42'),(69,15,'Under Review','Approved',NULL,3,'2026-11-03 09:49:42'),(70,15,'Approved','Processing',NULL,3,'2026-11-04 04:49:42'),(71,16,NULL,'Pending','Request submitted',5,'2026-11-02 15:42:35'),(72,16,'Pending','Under Review',NULL,3,'2026-11-02 21:42:35'),(73,16,'Under Review','Approved',NULL,3,'2026-11-03 12:42:35'),(74,17,NULL,'Pending','Request submitted',9,'2026-11-03 15:08:15'),(75,17,'Pending','Under Review',NULL,2,'2026-11-04 00:08:15'),(76,18,NULL,'Pending','Request submitted',5,'2026-11-03 19:52:41'),(77,18,'Pending','Under Review',NULL,3,'2026-11-04 08:52:41'),(78,19,NULL,'Pending','Request submitted',7,'2026-11-03 19:21:37'),(79,20,NULL,'Pending','Request submitted',5,'2026-11-04 15:24:46'),(80,21,NULL,'Pending','Request submitted',12,'2026-11-04 15:07:43');
/*!40000 ALTER TABLE `request_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` tinyint(3) unsigned NOT NULL,
  `role_key` varchar(20) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_key` (`role_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`role_id`, `role_key`, `role_name`) VALUES (1,'admin','Administrator'),(2,'staff','Barangay Official / Staff'),(3,'resident','Resident');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` tinyint(3) unsigned NOT NULL,
  `purok_id` smallint(5) unsigned DEFAULT NULL,
  `first_name` varchar(60) NOT NULL,
  `last_name` varchar(60) NOT NULL,
  `email` varchar(120) NOT NULL,
  `contact_no` varchar(13) DEFAULT NULL,
  `street_address` varchar(150) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `failed_logins` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_purok` (`purok_id`),
  KEY `idx_users_role` (`role_id`),
  CONSTRAINT `fk_users_purok` FOREIGN KEY (`purok_id`) REFERENCES `puroks` (`purok_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`user_id`, `role_id`, `purok_id`, `first_name`, `last_name`, `email`, `contact_no`, `street_address`, `password_hash`, `email_verified_at`, `is_active`, `failed_logins`, `locked_until`, `created_at`, `updated_at`) VALUES (1,1,NULL,'Maricel','Dela Cruz','admin@barangayservice.com','09171110001','Barangay Hall, Rizal St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-08-06 22:07:46',1,0,NULL,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(2,2,1,'Ramon','Villanueva','staff@barangayservice.com','09171110002','Barangay Hall, Rizal St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-08-06 22:07:46',1,0,NULL,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(3,2,3,'Liza','Santos','staff2@barangayservice.com','09171110003','Barangay Hall, Rizal St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-08-06 22:07:46',1,0,NULL,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(4,2,5,'Eduardo','Ramos','staff3@barangayservice.com','09171110004','Barangay Hall, Rizal St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-08-06 22:07:46',1,0,NULL,'2026-08-06 22:07:46','2026-11-04 22:07:46'),(5,3,2,'Juan','Dela Paz','resident@barangayservice.com','09182220000','12 Mabini St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-09-05 22:07:46',1,0,NULL,'2026-09-05 22:07:46','2026-11-04 22:07:46'),(6,3,1,'Ana','Reyes','ana.reyes@barangayservice.com','09182220001','45 Rizal St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-09-08 22:07:46',1,0,NULL,'2026-09-08 22:07:46','2026-11-04 22:07:46'),(7,3,3,'Jose','Mendoza','jose.mendoza@barangayservice.com','09182220002','7 Bonifacio St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-09-11 22:07:46',1,0,NULL,'2026-09-11 22:07:46','2026-11-04 22:07:46'),(8,3,4,'Grace','Bautista','grace.bautista@barangayservice.com','09182220003','88 Luna St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-09-14 22:07:46',1,0,NULL,'2026-09-14 22:07:46','2026-11-04 22:07:46'),(9,3,5,'Mark','Aquino','mark.aquino@barangayservice.com','09182220004','3 Del Pilar St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-09-17 22:07:46',1,0,NULL,'2026-09-17 22:07:46','2026-11-04 22:07:46'),(10,3,6,'Rosa','Garcia','rosa.garcia@barangayservice.com','09182220005','21 Burgos St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-09-20 22:07:46',1,0,NULL,'2026-09-20 22:07:46','2026-11-04 22:07:46'),(11,3,2,'Paolo','Torres','paolo.torres@barangayservice.com','09182220006','64 Mabini St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-09-23 22:07:46',1,0,NULL,'2026-09-23 22:07:46','2026-11-04 22:07:46'),(12,3,4,'Carmela','Navarro','carmela.navarro@barangayservice.com','09182220007','9 Luna St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS','2026-09-26 22:07:46',1,0,NULL,'2026-09-26 22:07:46','2026-11-04 22:07:46'),(13,3,3,'Nina','Castillo','unverified@barangayservice.com','09182229999','15 Bonifacio St.','$2y$10$iy9TG7OcuiUG2y7Gupz1.u7lAi93PlGh2jmGiY5jMtN40OS1uv5iS',NULL,1,0,NULL,'2026-11-03 22:07:46','2026-11-04 22:07:46');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
