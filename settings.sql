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
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` json DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string',
  `group` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`key`),
  KEY `settings_group_index` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('allow_negative_stock','\"true\"','boolean','core','允許負庫存出貨','2026-02-27 06:13:27','2026-04-17 11:30:58'),('base_currency','\"TWD\"','string','finance','本幣','2026-04-10 13:21:44','2026-04-10 13:21:44'),('currency_rates','{\"CNY\": \"4.5230\", \"HKD\": \"4.1250\", \"USD\": \"32.1500\"}','json','finance','匯率連動設置','2026-04-15 13:26:18','2026-04-15 13:26:18'),('enable_audit_log','\"true\"','boolean','security','記錄操作日誌','2026-02-27 06:13:27','2026-04-11 09:08:36'),('force_vendor_on_po','\"true\"','boolean','core','採購必須綁定供應商','2026-02-27 06:13:27','2026-04-11 09:08:35'),('ic_prefix','\"IC-\"','string','numbering','拆裝組合單前綴','2026-04-09 14:20:06','2026-04-10 13:14:40'),('number_digits','\"4\"','number','numbering','流水號位數','2026-02-27 06:13:27','2026-04-11 09:08:36'),('per_page','\"25\"','number','display','每頁顯示筆數','2026-02-27 06:13:28','2026-04-11 09:08:36'),('po_auto_stock_in','\"false\"','boolean','core','預設採購即入庫','2026-04-09 14:20:06','2026-04-21 12:25:20'),('po_prefix','\"PO-\"','string','numbering','採購單前綴','2026-02-27 06:13:27','2026-02-27 06:13:27'),('pr_prefix','\"PR-\"','string','numbering','採購退回單前綴','2026-04-09 14:20:06','2026-04-10 13:14:40'),('session_timeout','\"30\"','number','security','閒置登出時間(分)','2026-02-27 06:13:27','2026-04-11 09:08:36'),('show_cost_fields','\"false\"','boolean','display','顯示庫存成本','2026-02-27 06:13:28','2026-04-11 09:08:36'),('so_auto_stock_out','\"false\"','boolean','core','預設銷貨即出庫','2026-04-09 14:20:06','2026-04-21 07:25:28'),('so_prefix','\"SO-\"','string','numbering','銷貨單前綴','2026-04-09 14:20:06','2026-04-10 13:14:40'),('sr_prefix','\"SR-\"','string','numbering','銷貨退回單前綴','2026-04-09 14:20:06','2026-04-10 13:14:40'),('stock_alert_enabled','\"true\"','boolean','integration','啟用庫存低於安全量警報','2026-02-27 06:13:28','2026-04-11 09:08:36'),('tax_rate','\"5\"','float','finance','營業稅率','2026-04-10 13:05:47','2026-04-11 09:08:36');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-28 13:49:22
