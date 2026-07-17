-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: loan_db_repaired
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

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
-- Table structure for table `borrowers`
--

DROP TABLE IF EXISTS `borrowers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `borrowers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gcash_name` varchar(150) DEFAULT NULL,
  `gcash_number` varchar(50) DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `savings_closed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `borrowers_name_unique` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `borrowers`
--

LOCK TABLES `borrowers` WRITE;
/*!40000 ALTER TABLE `borrowers` DISABLE KEYS */;
INSERT INTO `borrowers` VALUES (1,'1-Doc Alice',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:17:45'),(2,'2-Doc Alice',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:18:11'),(3,'Irene B',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:19:57'),(4,'1-Carlo T',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:20:33'),(5,'2-Carlo T',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:20:44'),(6,'3-Carlo T',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:20:55'),(7,'1-Intoy G',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:22:01'),(8,'2-Intoy G',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:22:11'),(9,'Boybi',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:22:30'),(10,'1-Pau C',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:22:51'),(11,'2-Pau C',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:23:04'),(12,'Ten',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:23:21'),(13,'Chie',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:32:22'),(14,'Joel',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:32:28'),(15,'Manong',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:32:35'),(16,'Cla',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:32:49'),(17,'Carol A',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:32:55'),(18,'Nati',NULL,NULL,NULL,NULL,'Active',0,'2026-07-15 04:33:20');
/*!40000 ALTER TABLE `borrowers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `capital_contributions`
--

DROP TABLE IF EXISTS `capital_contributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `capital_contributions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `type` enum('INITIAL','CUTOFF') NOT NULL DEFAULT 'CUTOFF',
  `contribution_date` date NOT NULL,
  `period_label` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `capital_contributions_borrower_id_index` (`borrower_id`),
  KEY `capital_contributions_date_type_index` (`contribution_date`,`type`),
  CONSTRAINT `capital_contributions_borrower_id_fk` FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `capital_contributions`
--

LOCK TABLES `capital_contributions` WRITE;
/*!40000 ALTER TABLE `capital_contributions` DISABLE KEYS */;
INSERT INTO `capital_contributions` VALUES (1,9,500.00,'CUTOFF','2026-07-15','GCash Ref: 4042902483158','2026-07-15 07:24:05'),(2,9,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-15 07:24:51'),(3,2,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-15 07:26:44'),(4,1,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 05:52:18'),(5,4,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:26:33'),(6,5,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:26:48'),(7,6,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:27:01'),(8,7,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:27:27'),(9,11,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:31:00'),(10,10,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:31:22'),(11,8,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:31:43'),(12,17,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:31:57'),(13,13,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:32:13'),(14,16,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 06:32:33'),(15,3,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 09:29:53'),(16,14,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 09:30:09'),(17,15,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 09:30:31'),(18,18,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 09:31:14'),(19,12,2771.00,'INITIAL','2026-07-03',NULL,'2026-07-16 09:31:34');
/*!40000 ALTER TABLE `capital_contributions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loan_requests`
--

DROP TABLE IF EXISTS `loan_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loan_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int(10) unsigned NOT NULL,
  `requested_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `requested_months` decimal(6,2) NOT NULL DEFAULT 0.00,
  `approved_amount` decimal(12,2) DEFAULT NULL,
  `approved_months` decimal(6,2) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `approved_loan_id` int(10) unsigned DEFAULT NULL,
  `is_guarantor` tinyint(1) NOT NULL DEFAULT 0,
  `guest_borrower_name` varchar(150) DEFAULT NULL,
  `disbursement_reference_number` varchar(100) DEFAULT NULL,
  `disbursement_proof_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `loan_requests_borrower_id_index` (`borrower_id`),
  KEY `loan_requests_status_created_at_index` (`status`,`created_at`),
  KEY `loan_requests_approved_loan_id_index` (`approved_loan_id`),
  CONSTRAINT `loan_requests_approved_loan_id_fk` FOREIGN KEY (`approved_loan_id`) REFERENCES `loans` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `loan_requests_borrower_id_fk` FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_requests`
--

LOCK TABLES `loan_requests` WRITE;
/*!40000 ALTER TABLE `loan_requests` DISABLE KEYS */;
INSERT INTO `loan_requests` VALUES (2,9,10000.00,6.00,10000.00,6.00,'Approved',NULL,0,NULL,NULL,NULL,'2026-07-15 10:17:36','2026-07-16 00:02:19');
/*!40000 ALTER TABLE `loan_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `loans`
--

DROP TABLE IF EXISTS `loans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `loans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `interest` decimal(12,2) NOT NULL DEFAULT 0.00,
  `months` decimal(6,2) NOT NULL DEFAULT 0.00,
  `total_payable` decimal(12,2) NOT NULL DEFAULT 0.00,
  `start_date` date NOT NULL,
  `status` enum('Active','Completed') NOT NULL DEFAULT 'Active',
  `is_guarantor` tinyint(1) NOT NULL DEFAULT 0,
  `guest_borrower_name` varchar(150) DEFAULT NULL,
  `disbursement_reference_number` varchar(100) DEFAULT NULL,
  `disbursement_proof_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `loans_borrower_id_index` (`borrower_id`),
  CONSTRAINT `loans_borrower_id_fk` FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans`
--

LOCK TABLES `loans` WRITE;
/*!40000 ALTER TABLE `loans` DISABLE KEYS */;
INSERT INTO `loans` VALUES (4,12,36028.00,4324.00,6.00,40352.00,'2026-07-01','Active',0,NULL,NULL,NULL,'2026-07-15 07:35:19');
/*!40000 ALTER TABLE `loans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_submissions`
--

DROP TABLE IF EXISTS `payment_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int(10) unsigned NOT NULL,
  `payment_date` date NOT NULL,
  `cutoff_date` date NOT NULL,
  `capital_contribution` decimal(12,2) NOT NULL DEFAULT 0.00,
  `loan_payment` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) NOT NULL,
  `proof_image` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `payment_submissions_borrower_id_index` (`borrower_id`),
  KEY `payment_submissions_cutoff_date_index` (`cutoff_date`),
  CONSTRAINT `payment_submissions_borrower_id_fk` FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_submissions`
--

LOCK TABLES `payment_submissions` WRITE;
/*!40000 ALTER TABLE `payment_submissions` DISABLE KEYS */;
INSERT INTO `payment_submissions` VALUES (1,9,'2026-07-15','2026-07-15',500.00,0.00,'4042902483158','uploads/payment_proofs/payment_9_1784100175_6b2fe890.jpg','Approved','2026-07-15 07:22:55');
/*!40000 ALTER TABLE `payment_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `loan_id` int(10) unsigned NOT NULL,
  `payment_no` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_date` date NOT NULL,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `payments_loan_id_index` (`loan_id`),
  KEY `payments_due_date_paid_index` (`due_date`,`paid`),
  CONSTRAINT `payments_loan_id_fk` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (37,4,1,3362.00,'2026-07-15',0,NULL,'2026-07-15 07:35:19'),(38,4,2,3362.00,'2026-07-31',0,NULL,'2026-07-15 07:35:19'),(39,4,3,3362.00,'2026-08-15',0,NULL,'2026-07-15 07:35:19'),(40,4,4,3362.00,'2026-08-31',0,NULL,'2026-07-15 07:35:19'),(41,4,5,3362.00,'2026-09-15',0,NULL,'2026-07-15 07:35:19'),(42,4,6,3362.00,'2026-09-30',0,NULL,'2026-07-15 07:35:19'),(43,4,7,3362.00,'2026-10-15',0,NULL,'2026-07-15 07:35:19'),(44,4,8,3362.00,'2026-10-31',0,NULL,'2026-07-15 07:35:19'),(45,4,9,3362.00,'2026-11-15',0,NULL,'2026-07-15 07:35:19'),(46,4,10,3362.00,'2026-11-30',0,NULL,'2026-07-15 07:35:19'),(47,4,11,3362.00,'2026-12-15',0,NULL,'2026-07-15 07:35:19'),(48,4,12,3370.00,'2026-12-31',0,NULL,'2026-07-15 07:35:19');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_submissions`
--

DROP TABLE IF EXISTS `savings_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `savings_submissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_number` varchar(100) NOT NULL,
  `proof_image` varchar(255) NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_submissions_borrower_id_index` (`borrower_id`),
  KEY `savings_submissions_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `savings_submissions_borrower_id_fk` FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_submissions`
--

LOCK TABLES `savings_submissions` WRITE;
/*!40000 ALTER TABLE `savings_submissions` DISABLE KEYS */;
INSERT INTO `savings_submissions` VALUES (1,9,5000.00,'4040436','uploads/savings_proofs/savings_9_1784164367_38e0e5bb.jpg','Approved','2026-07-16 01:12:47','2026-07-16 01:13:47');
/*!40000 ALTER TABLE `savings_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_transactions`
--

DROP TABLE IF EXISTS `savings_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `savings_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `type` enum('DEPOSIT','WITHDRAWAL') NOT NULL DEFAULT 'DEPOSIT',
  `transaction_date` date NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `savings_transactions_borrower_id_index` (`borrower_id`),
  KEY `savings_transactions_date_type_index` (`transaction_date`,`type`),
  CONSTRAINT `savings_transactions_borrower_id_fk` FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_transactions`
--

LOCK TABLES `savings_transactions` WRITE;
/*!40000 ALTER TABLE `savings_transactions` DISABLE KEYS */;
/*!40000 ALTER TABLE `savings_transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `savings_withdrawal_requests`
--

DROP TABLE IF EXISTS `savings_withdrawal_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `savings_withdrawal_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `borrower_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gcash_name` varchar(150) NOT NULL,
  `gcash_number` varchar(50) NOT NULL,
  `admin_reference_number` varchar(100) DEFAULT NULL,
  `admin_proof_image` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_withdrawal_requests_borrower_id_index` (`borrower_id`),
  KEY `savings_withdrawal_requests_status_created_at_index` (`status`,`created_at`),
  CONSTRAINT `savings_withdrawal_requests_borrower_id_fk` FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `savings_withdrawal_requests`
--

LOCK TABLES `savings_withdrawal_requests` WRITE;
/*!40000 ALTER TABLE `savings_withdrawal_requests` DISABLE KEYS */;
INSERT INTO `savings_withdrawal_requests` VALUES (2,9,2000.00,'Joseph Michael Aramil','09951660335','4040','uploads/withdrawal_proofs/withdrawal_9_1784164677_780d17a1.jpg','Approved','2026-07-16 01:16:35','2026-07-16 01:17:57');
/*!40000 ALTER TABLE `savings_withdrawal_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_account_links`
--

DROP TABLE IF EXISTS `user_account_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_account_links` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `linked_user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_account_links_unique` (`user_id`,`linked_user_id`),
  KEY `user_account_links_linked_user_id_index` (`linked_user_id`),
  CONSTRAINT `user_account_links_linked_user_id_fk` FOREIGN KEY (`linked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `user_account_links_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_account_links`
--

LOCK TABLES `user_account_links` WRITE;
/*!40000 ALTER TABLE `user_account_links` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_account_links` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('Admin','Member') NOT NULL DEFAULT 'Member',
  `borrower_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_borrower_id_index` (`borrower_id`),
  CONSTRAINT `users_borrower_id_fk` FOREIGN KEY (`borrower_id`) REFERENCES `borrowers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$tC8XBjQ1fpcOxn9I3ItQyum7FGeds5ZWti1wnLbw8USA0zKRDR2Fq','Admin',NULL,'2026-07-15 03:54:51'),(2,'1-Intoy G','','Member',7,'2026-07-15 05:08:58'),(3,'1-Carlo T','','Member',4,'2026-07-15 05:08:58'),(4,'1-Doc Alice','','Member',1,'2026-07-15 05:08:58'),(5,'1-Pau C','','Member',10,'2026-07-15 05:08:58'),(6,'2-Intoy G','','Member',8,'2026-07-15 05:08:58'),(7,'2-Carlo T','','Member',5,'2026-07-15 05:08:58'),(8,'2-Doc Alice','','Member',2,'2026-07-15 05:08:58'),(9,'2-Pau C','','Member',11,'2026-07-15 05:08:58'),(10,'3-Carlo T','','Member',6,'2026-07-15 05:08:58'),(11,'Boybi','$2y$10$N2sLpLaEIdzC5nYzxQrXqOvNvIqMVWVTt/x6Do2WNCpySdnqyZiwe','Member',9,'2026-07-15 05:08:58'),(12,'Carol A','','Member',17,'2026-07-15 05:08:58'),(13,'Chie','','Member',13,'2026-07-15 05:08:58'),(14,'Cla','','Member',16,'2026-07-15 05:08:58'),(15,'Irene B','','Member',3,'2026-07-15 05:08:58'),(16,'Joel','','Member',14,'2026-07-15 05:08:58'),(17,'Manong','','Member',15,'2026-07-15 05:08:58'),(18,'Nati','','Member',18,'2026-07-15 05:08:58'),(19,'Ten','','Member',12,'2026-07-15 05:08:58');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'loan_db_repaired'
--

--
-- Dumping routines for database 'loan_db_repaired'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-17 12:11:39
