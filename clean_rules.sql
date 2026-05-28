-- MySQL dump 10.13  Distrib 8.4.3, for Win64 (x86_64)
--
-- Host: localhost    Database: taotique
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounting_rules`
--

DROP TABLE IF EXISTS `accounting_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `shop_id` bigint unsigned NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounting_rules_event_type_unique` (`event_type`),
  KEY `accounting_rules_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounting_rules`
--

LOCK TABLES `accounting_rules` WRITE;
/*!40000 ALTER TABLE `accounting_rules` DISABLE KEYS */;
INSERT INTO `accounting_rules` VALUES (20,'sale_revenue_retail',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(21,'sale_revenue_shopee',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(22,'sale_revenue_facebook',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(23,'sale_revenue_live',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(24,'sale_fee_retail',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(25,'sale_fee_shopee',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(26,'sale_fee_facebook',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(27,'sale_fee_live',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(28,'sale_cost',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(29,'purchase_inbound_pendant',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(30,'purchase_inbound_bracelet',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(31,'purchase_inbound_general',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(32,'purchase_inbound_earring',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(33,'purchase_inbound_ring',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(34,'purchase_inbound_part',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(35,'purchase_inbound_package',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(36,'sale_return',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(37,'private_borrow',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(38,'private_withdraw',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(39,'owner_contract_income_cash',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(40,'owner_contract_income_cathay-cude',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(41,'owner_contract_income_tcb-bank',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(42,'owner_contract_income_post',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(43,'owner_contract_income_megabank',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(44,'owner_contract_income_taiwanpay',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(45,'owner_contract_income_shopee',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(46,'owner_contract_income_linepay',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(47,'owner_development_income_cash',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(48,'owner_development_income_cathay-cude',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(49,'owner_development_income_tcb-bank',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(50,'owner_development_income_post',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(51,'owner_development_income_megabank',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(52,'owner_development_income_taiwanpay',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(53,'owner_development_income_shopee',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(54,'owner_development_income_linepay',1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17');
/*!40000 ALTER TABLE `accounting_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accounting_rule_lines`
--

DROP TABLE IF EXISTS `accounting_rule_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `accounting_rule_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `accounting_rule_id` bigint unsigned NOT NULL,
  `account_id` bigint unsigned DEFAULT NULL,
  `account_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entry_type` enum('debit','credit') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ratio` decimal(8,4) NOT NULL DEFAULT '1.0000',
  `sort_order` int unsigned NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `accounting_rule_lines_accounting_rule_id_foreign` (`accounting_rule_id`),
  KEY `accounting_rule_lines_account_id_foreign` (`account_id`),
  KEY `accounting_rule_lines_is_active_index` (`is_active`),
  CONSTRAINT `accounting_rule_lines_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `accounting_rule_lines_accounting_rule_id_foreign` FOREIGN KEY (`accounting_rule_id`) REFERENCES `accounting_rules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=181 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounting_rule_lines`
--

LOCK TABLES `accounting_rule_lines` WRITE;
/*!40000 ALTER TABLE `accounting_rule_lines` DISABLE KEYS */;
INSERT INTO `accounting_rule_lines` VALUES (74,20,NULL,'DYNAMIC','debit','customer_total',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(75,20,76,'500101','credit','subtotal_after_discount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(76,20,103,'222101','credit','tax_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(77,21,50,NULL,'debit','customer_total',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(78,21,77,NULL,'credit','subtotal_after_discount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(79,21,103,NULL,'credit','tax_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(80,21,69,NULL,'credit','shipping_fee_customer',1.0000,4,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(81,22,50,NULL,'debit','customer_total',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(82,22,78,NULL,'credit','subtotal_after_discount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(83,22,103,NULL,'credit','tax_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(84,22,69,NULL,'credit','shipping_fee_customer',1.0000,4,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(85,23,32,NULL,'debit','customer_total',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(86,23,79,NULL,'credit','subtotal_after_discount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(87,23,103,NULL,'credit','tax_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(88,23,69,NULL,'credit','shipping_fee_customer',1.0000,4,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(94,25,88,NULL,'debit','platform_fee',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(95,25,108,NULL,'debit','commission',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(96,25,81,NULL,'debit','seller_discount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(97,25,87,NULL,'debit','shipping_fee_platform',1.0000,4,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(98,25,91,NULL,'debit','order_adjustment',1.0000,5,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(99,25,50,NULL,'credit','total_fees',1.0000,6,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(100,26,88,NULL,'debit','platform_fee',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(101,26,108,NULL,'debit','commission',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(102,26,81,NULL,'debit','seller_discount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(103,26,87,NULL,'debit','shipping_fee_platform',1.0000,4,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(104,26,50,NULL,'credit','total_fees',1.0000,5,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(105,27,88,NULL,'debit','platform_fee',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(106,27,108,NULL,'debit','commission',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(107,27,81,NULL,'debit','seller_discount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(108,27,87,NULL,'debit','shipping_fee_platform',1.0000,4,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(109,27,32,NULL,'credit','total_fees',1.0000,5,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(112,29,54,NULL,'debit','items_amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(113,29,67,NULL,'debit','purchase_tax_amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(114,29,65,NULL,'credit','total_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(115,30,55,NULL,'debit','items_amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(116,30,67,NULL,'debit','purchase_tax_amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(117,30,65,NULL,'credit','total_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(118,31,56,NULL,'debit','items_amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(119,31,67,NULL,'debit','purchase_tax_amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(120,31,65,NULL,'credit','total_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(121,32,57,NULL,'debit','items_amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(122,32,67,NULL,'debit','purchase_tax_amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(123,32,65,NULL,'credit','total_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(124,33,58,NULL,'debit','items_amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(125,33,67,NULL,'debit','purchase_tax_amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(126,33,65,NULL,'credit','total_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(127,34,59,NULL,'debit','items_amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(128,34,67,NULL,'debit','purchase_tax_amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(129,34,65,NULL,'credit','total_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(130,35,60,NULL,'debit','items_amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(131,35,67,NULL,'debit','purchase_tax_amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(132,35,65,NULL,'credit','total_amount',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(133,36,76,NULL,'debit','subtotal_after_discount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(134,36,103,NULL,'debit','tax_amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(135,36,30,NULL,'credit','customer_total',1.0000,3,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(136,36,54,NULL,'debit','items.sum:cost*quantity',1.0000,4,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(137,36,107,NULL,'credit','items.sum:cost*quantity',1.0000,5,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(138,37,53,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(139,37,30,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(140,38,72,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(141,38,30,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(142,39,30,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(143,39,82,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(144,40,32,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(145,40,82,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(146,41,33,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(147,41,82,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(148,42,34,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(149,42,82,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(150,43,35,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(151,43,82,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(152,44,38,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(153,44,82,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(154,45,39,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(155,45,82,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(156,46,40,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(157,46,82,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(158,47,30,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(159,47,83,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(160,48,32,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(161,48,83,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(162,49,33,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(163,49,83,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(164,50,34,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(165,50,83,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(166,51,35,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(167,51,83,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(168,52,38,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(169,52,83,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(170,53,39,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(171,53,83,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(172,54,40,NULL,'debit','amount',1.0000,1,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(173,54,83,NULL,'credit','amount',1.0000,2,1,'2026-05-27 00:14:17','2026-05-27 00:14:17'),(174,28,107,NULL,'debit','items.sum:product.cost*quantity',1.0000,1,1,'2026-05-27 14:34:57','2026-05-27 14:34:57'),(175,28,54,NULL,'credit','items.sum:product.cost*quantity',1.0000,1,1,'2026-05-27 14:34:57','2026-05-27 14:34:57'),(176,24,88,NULL,'debit','platform_fee',1.0000,1,1,'2026-05-27 23:34:00','2026-05-27 23:34:00'),(177,24,108,NULL,'debit','commission',1.0000,1,1,'2026-05-27 23:34:00','2026-05-27 23:34:00'),(178,24,81,NULL,'debit','seller_discount',1.0000,1,1,'2026-05-27 23:34:01','2026-05-27 23:34:01'),(179,24,87,NULL,'debit','shipping_fee_platform',1.0000,1,1,'2026-05-27 23:34:01','2026-05-27 23:34:01'),(180,24,30,NULL,'credit','total_fees',1.0000,1,1,'2026-05-27 23:34:01','2026-05-27 23:34:01');
/*!40000 ALTER TABLE `accounting_rule_lines` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-05-28 15:14:36
