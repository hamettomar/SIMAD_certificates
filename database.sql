-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: certificate_hub
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
-- Current Database: `certificate_hub`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `u264887221_shahado_hub` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `u264887221_shahado_hub`;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Lead Organizer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'Ahmed Omar','ahmed@gmail.com','$2y$10$G26BknUlxjXAJNMrnv4uE.LrK0j1XWK193FaqK8hlUMuH5wXvHglS','Lead Organizer','2026-09-08 09:02:23','2026-09-08 09:20:22');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificate_templates`
--

DROP TABLE IF EXISTS `certificate_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificate_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cohort_id` int(10) unsigned NOT NULL,
  `template_name` varchar(150) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `name_pos_x` decimal(5,2) NOT NULL DEFAULT 50.00,
  `name_pos_y` decimal(5,2) NOT NULL DEFAULT 48.00,
  `name_font_size` int(10) unsigned NOT NULL DEFAULT 48,
  `name_font_family` varchar(100) NOT NULL DEFAULT 'Inter',
  `name_font_color` varchar(20) NOT NULL DEFAULT '#00174B',
  `name_text_align` enum('center','left','right') DEFAULT 'center',
  `show_cert_id` tinyint(1) NOT NULL DEFAULT 1,
  `cert_id_pos_x` decimal(5,2) DEFAULT 50.00,
  `cert_id_pos_y` decimal(5,2) DEFAULT 88.00,
  `cert_id_color` varchar(20) DEFAULT '#64748B',
  `cert_id_font_size` int(10) unsigned DEFAULT 14,
  `show_qr` tinyint(1) NOT NULL DEFAULT 0,
  `qr_pos_x` decimal(5,2) DEFAULT 88.00,
  `qr_pos_y` decimal(5,2) DEFAULT 84.00,
  `qr_size` int(10) unsigned DEFAULT 80,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `show_issue_date` tinyint(1) DEFAULT 1,
  `issue_date_text` varchar(255) DEFAULT '12–13 September 2026',
  `issue_date_pos_x` decimal(5,2) DEFAULT 28.00,
  `issue_date_pos_y` decimal(5,2) DEFAULT 88.00,
  `issue_date_font_size` int(11) DEFAULT 10,
  `issue_date_color` varchar(20) DEFAULT '#1E293B',
  `issue_date_font_family` varchar(100) DEFAULT 'Inter',
  `show_cert_id_prefix` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `cohort_id` (`cohort_id`),
  CONSTRAINT `certificate_templates_ibfk_1` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_templates`
--

LOCK TABLES `certificate_templates` WRITE;
/*!40000 ALTER TABLE `certificate_templates` DISABLE KEYS */;
INSERT INTO `certificate_templates` VALUES (2,2,'Capacity-Building Workshop on Project and Research Grant Proposal Development Certificate Template','uploads/templates/template_cohort_2_1789382910.png',50.00,58.40,48,'Poppins','#000000','center',1,37.19,85.19,'#000000',10,1,51.00,82.10,60,1,'2026-09-14 10:44:06','2026-09-14 10:54:46',1,'14 September 2026',31.97,87.22,10,'0000000','Inter',0);
/*!40000 ALTER TABLE `certificate_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `certificates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` int(10) unsigned NOT NULL,
  `template_id` int(10) unsigned NOT NULL,
  `certificate_token` varchar(60) NOT NULL,
  `document_hash` varchar(64) NOT NULL,
  `download_count` int(10) unsigned NOT NULL DEFAULT 0,
  `last_downloaded_at` datetime DEFAULT NULL,
  `status` enum('valid','revoked') DEFAULT 'valid',
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `participant_id` (`participant_id`),
  UNIQUE KEY `certificate_token` (`certificate_token`),
  KEY `idx_certificate_token` (`certificate_token`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `certificate_templates` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
INSERT INTO `certificates` VALUES (1,1,2,'SIMAD-PU-2026-001','1001c790f50f1f75702cd2cbbe7c2aa5c3c31da4e52efa9564b8eb93d847ceba',0,NULL,'valid','2026-09-14 10:53:38'),(2,2,2,'SIMAD-PU-2026-002','00e4f06f4fcd3c52969fa4db71b0787e6b739d37023a557908e85269e8d2311a',0,NULL,'valid','2026-09-14 10:53:38'),(3,3,2,'SIMAD-PU-2026-003','3a9b82d578a506a39b2bfe10f553828f8dead4c5265492a8fd9a5886d1912fb6',0,NULL,'valid','2026-09-14 10:53:38'),(4,4,2,'SIMAD-PU-2026-004','32f1f2cc92015e0ecbf37db9b0c76750aa4c8e516fe1db4db80c09e5c2a6f2b5',0,NULL,'valid','2026-09-14 10:53:38'),(5,5,2,'SIMAD-PU-2026-005','0ae2a1e8374346475a864014db822948b05de071f49544dd8f5966e7d3e0f037',0,NULL,'valid','2026-09-14 10:53:38'),(6,6,2,'SIMAD-PU-2026-006','9cd477400c320371228b4644e1a10473c77de82ca4df6bbed6e30d96cc470095',0,NULL,'valid','2026-09-14 10:53:38'),(7,7,2,'SIMAD-PU-2026-007','f55a1233480dc829ecf338fdb59b2eb717f2d215f105a41aeca39b39bf231086',0,NULL,'valid','2026-09-14 10:53:38'),(8,8,2,'SIMAD-PU-2026-008','cd68883bc7f1581cc6741a9c91200c6da8fc4c083cdb68b6afca75b947ff6d7c',0,NULL,'valid','2026-09-14 10:53:38'),(9,9,2,'SIMAD-PU-2026-009','ef07ef5bbdeda897b9aecf67e82c6e2084a7b0a3e4a742760a0a5e39b8df8194',0,NULL,'valid','2026-09-14 10:53:38'),(10,10,2,'SIMAD-PU-2026-010','9924fbbdca6cd93c2e39063546f6f8bfe5d364ca0f0972937e3bd5f0967afc4d',0,NULL,'valid','2026-09-14 10:53:38'),(11,11,2,'SIMAD-PU-2026-011','c755ebdfb87a98e49f54d4fb41f1d9f0593b7b599ed40cd3037c9740a2be387f',0,NULL,'valid','2026-09-14 10:53:38'),(12,12,2,'SIMAD-PU-2026-012','f3dc50adcce0cd54345792ef5c3902a7de570d43d7fdc3822a97be58246338f0',0,NULL,'valid','2026-09-14 10:53:38'),(13,13,2,'SIMAD-PU-2026-013','5d0ef30b71fdac4efc5c500c6597532edfc230796c1b009afefee0dd3a925bcd',0,NULL,'valid','2026-09-14 10:53:38'),(14,14,2,'SIMAD-PU-2026-014','2b4671cc8f82f2be2e86992e85fd0e32ba466deb971b5de4b65534b4c10fae17',0,NULL,'valid','2026-09-14 10:53:38'),(15,15,2,'SIMAD-PU-2026-015','ed55515384b630b6bc8ad09e0e8abae73e3c133cb4eaf8566a6866f469db9406',0,NULL,'valid','2026-09-14 10:53:38'),(16,16,2,'SIMAD-PU-2026-016','dcb70470f3f898e349559bc136b20f63b95a68fbb84a31b59a35ee41059de2dd',0,NULL,'valid','2026-09-14 10:53:38'),(17,17,2,'SIMAD-PU-2026-017','3ab21c96dc5ae3e847d2c939b02da745a717070b8c1728a380002ecbcdba69f0',0,NULL,'valid','2026-09-14 10:53:38'),(18,18,2,'SIMAD-PU-2026-018','2e67c5856d18bae3e864956e0bfa2db4a50d9a6d1bc5859bbfdaef5964be9297',0,NULL,'valid','2026-09-14 10:53:38'),(19,19,2,'SIMAD-PU-2026-019','2cb0e360fdadad8341b8e5b29d6969cfce4de0eb3a0fd508683252b119e3c393',0,NULL,'valid','2026-09-14 10:53:38'),(20,20,2,'SIMAD-PU-2026-020','b19a8062f79a0acaf5ea0f218ca6e8b2d49f20e49cd6308663744099e9ed45a6',0,NULL,'valid','2026-09-14 10:53:38'),(21,21,2,'SIMAD-PU-2026-021','32cf995309c05fd28c6835035b298262b4d9be481cd13cf2e3a6a5f93221fe89',0,NULL,'valid','2026-09-14 10:53:38'),(22,22,2,'SIMAD-PU-2026-022','e98afc6c4250a04874dabc8482dd30be45a4616a55fdeed6822ce9221de27658',0,NULL,'valid','2026-09-14 10:53:38'),(23,23,2,'SIMAD-PU-2026-023','5f7518b98aeaae46cfcbe3454aa0db7b6b55bd8061f143a1f04085241d448d2c',0,NULL,'valid','2026-09-14 10:53:38'),(24,24,2,'SIMAD-PU-2026-024','3d4ab62bc131f25e717fafe186b0152ee2e07b2a3f0aae807c50e8f4e8496e50',0,NULL,'valid','2026-09-14 10:53:38'),(25,25,2,'SIMAD-PU-2026-025','bfe15e0add1a9f61a0fc7566205eb49ab16585747e9dfc1e81a0380408c8799f',0,NULL,'valid','2026-09-14 10:53:38'),(26,26,2,'SIMAD-PU-2026-026','de2d6b5489590f43c2d7dcdb21fab9023486e333a501569ae8a86dcfead31abd',0,NULL,'valid','2026-09-14 10:53:38'),(27,27,2,'SIMAD-PU-2026-027','9de8155c902498d5168fc416c905c1caf00caa4b51d6eda1bd5c1617a45bb554',0,NULL,'valid','2026-09-14 10:53:38'),(28,28,2,'SIMAD-PU-2026-028','8de71066e3134bc0fcff279b39b193822c2c2ab01f92332c0e6467d91752885d',0,NULL,'valid','2026-09-14 10:53:38'),(29,29,2,'SIMAD-PU-2026-029','19b06c268ee2041b3a76e6366eb30349731fa2db4f5eb226680fcbc535c9d24d',0,NULL,'valid','2026-09-14 10:53:38'),(30,30,2,'SIMAD-PU-2026-030','d4cc29c7ca633d367652a3b33905509ee2bf3f33e12ff588f02ae1e528e6ccfe',0,NULL,'valid','2026-09-14 10:53:38'),(31,31,2,'SIMAD-PU-2026-031','15f69095d6e8503354f632be5e29eba8fe01cc3978a4c3fa259f4b432d07aa0b',0,NULL,'valid','2026-09-14 10:53:38'),(32,32,2,'SIMAD-PU-2026-032','f971df4db4a4296e4d7e2c7667c191c082bc022c82b2d10e52444012853e476d',0,NULL,'valid','2026-09-14 10:53:38'),(33,33,2,'SIMAD-PU-2026-033','b1be2e05b78fca614737d74796e7e3f68d5ff5cea8848c74dd8f9b25de58d9c2',0,NULL,'valid','2026-09-14 10:53:38'),(34,34,2,'SIMAD-PU-2026-034','a8dde9d22c9c6fa6fa97797343d44bf5a59dfbb07db7f815a28eb67610a28630',0,NULL,'valid','2026-09-14 10:53:38'),(35,35,2,'SIMAD-PU-2026-035','ccb1db3848fe81adedf3568a65524241f104c3c4282618eec7542429c2aeabdb',0,NULL,'valid','2026-09-14 10:53:38'),(36,36,2,'SIMAD-PU-2026-036','ac48183cdff28f1ec905f3a519e5c626966a76c7bc0f6f603a6bd713bb666d19',0,NULL,'valid','2026-09-14 10:53:38'),(37,37,2,'SIMAD-PU-2026-037','93ec7dd10c9ea2574183141ad5e3b38d10ae37de756349527e05435c24c47549',0,NULL,'valid','2026-09-14 10:53:38'),(38,38,2,'SIMAD-PU-2026-038','eecc75f216996ed1c9e1b87d2ab5ee965c840e7dd9b640a862b0e7fc3091ed4d',0,NULL,'valid','2026-09-14 10:53:38'),(39,39,2,'SIMAD-PU-2026-039','bcc4807a354bb023688ed6eb259c2b70ce70b31fa909221e05d0767683cbbe6f',0,NULL,'valid','2026-09-14 10:53:38'),(40,40,2,'SIMAD-PU-2026-040','f37c5668f1e065521ca15fdfcd587eae6abb51e7d2f557f84e1112e98221b036',0,NULL,'valid','2026-09-14 10:53:38'),(41,41,2,'SIMAD-PU-2026-041','e12ce49ec041973d7a996277e0696d35379bb24764bcc53da7fc1778ea88bd75',0,NULL,'valid','2026-09-14 10:53:38'),(42,42,2,'SIMAD-PU-2026-042','a5b3b91dd3df5477fea042523529aa5512ca01e06c15b4012eaeb78f2f0ff0a0',0,NULL,'valid','2026-09-14 10:53:38'),(43,43,2,'SIMAD-PU-2026-043','5eae8ead93185f3e4c8950c183d51311f8e0604a868ff87ac6587667c0b0fe54',0,NULL,'valid','2026-09-14 10:53:38'),(44,44,2,'SIMAD-PU-2026-044','dd6a75b0f25900d590174cb8c57b2778633e72494d2c28b7b0cfde6c26ddf7ce',0,NULL,'valid','2026-09-14 10:53:38'),(45,45,2,'SIMAD-PU-2026-045','8c56f020ac0239ddb383e1b4c3436180badbf3ae0e473038a261008595f7a05b',0,NULL,'valid','2026-09-14 10:53:38'),(46,46,2,'SIMAD-PU-2026-046','22526addcb6b7adc061353dcdfcb44cf3ad62e7a54bf8c0a3b0dfbb773390446',0,NULL,'valid','2026-09-14 10:53:38'),(47,47,2,'SIMAD-PU-2026-047','3cb6f1973e64a72c610f60ddcf02c1201788ca305c0de1eac370fcb404be5409',0,NULL,'valid','2026-09-14 10:53:38'),(48,48,2,'SIMAD-PU-2026-048','e1eee2ccad1b850231ea7ec75b988fb286a49e5bd0ed05ecba1f9a8d3e10d48e',0,NULL,'valid','2026-09-14 10:53:38'),(49,49,2,'SIMAD-PU-2026-049','c5972e51b7e409d5fa4594f3e99a70d4fdbcc6a7646398ae7aeabacb6e3c9b2c',0,NULL,'valid','2026-09-14 10:53:38'),(50,50,2,'SIMAD-PU-2026-050','81d5ed3da799ad30d44c22ef60616af4f0c37c96c3740aac9b68a44750b9584d',0,NULL,'valid','2026-09-14 10:53:38'),(51,51,2,'SIMAD-PU-2026-051','ab45faee1922ff26b8f0d311298e26cc164f5a8309d5d9d0f543a4bd46cc3035',0,NULL,'valid','2026-09-14 10:53:38'),(52,52,2,'SIMAD-PU-2026-052','3894b2407c1fea19c2ec889c5c36eb0712d80034f727c9b9a9475831cd0d84f6',0,NULL,'valid','2026-09-14 10:53:38'),(53,53,2,'SIMAD-PU-2026-053','c8fcfde409b9d8655bf618d3ce55b28516457c96d345682967495b7235fc65e8',0,NULL,'valid','2026-09-14 10:53:38'),(54,54,2,'SIMAD-PU-2026-054','398206af3d98b5d06dc6967ec201e5e959d9139d663dc3c122279f268feab086',0,NULL,'valid','2026-09-14 10:53:38'),(55,55,2,'SIMAD-PU-2026-055','abfd0a8aa2252b07518e02fc6f760fa0a47d8725b41eea731bba96c1ceb1291d',0,NULL,'valid','2026-09-14 10:53:38'),(56,56,2,'SIMAD-PU-2026-056','cf5d742c9e0620df94988661e86d3153f35c6473c53d3e75f1322644d5b485c8',0,NULL,'valid','2026-09-14 10:53:38'),(57,57,2,'SIMAD-PU-2026-057','240bb2b47a969e5c4d3488824cbe2e5bd27294bfe0c4093e94f5813a9b9253ec',0,NULL,'valid','2026-09-14 10:53:38'),(58,58,2,'SIMAD-PU-2026-058','4b047280f24e22fce6f0bd8b60a0c7edbdda44ae3cc40233979fd7e661b2605d',0,NULL,'valid','2026-09-14 10:53:38'),(59,59,2,'SIMAD-PU-2026-059','4f7f8147c311680d4b3dea21f0e5f2bd45fc49bcf3b583d45fe3031f405325ec',0,NULL,'valid','2026-09-14 10:53:38'),(60,60,2,'SIMAD-PU-2026-060','cb10c6ef67635dcc7dc4283f2e4f98ef67bc323b02d3364d2534c13470dfeb02',0,NULL,'valid','2026-09-14 10:53:38'),(61,61,2,'SIMAD-PU-2026-061','2780522cdae4d7b6c210bddba9339c9490b30de660546849bcc21fa81aac5f74',0,NULL,'valid','2026-09-14 10:53:38'),(62,62,2,'SIMAD-PU-2026-062','f5e5bc2d47e2f3f351f15a798e12cda9ce1fbfe1f8c56d6d4fb28000709d2053',0,NULL,'valid','2026-09-14 10:53:38'),(63,63,2,'SIMAD-PU-2026-063','4854a9d7ba3f163a687617bb53dbebb9f6e210238f486409723a0ff8b2cbbf98',0,NULL,'valid','2026-09-14 10:53:38'),(64,64,2,'SIMAD-PU-2026-064','726cccc1100d2fb7e8edf82a43f14872f8be6783d50eb50bc27eb5dcb25f98a1',0,NULL,'valid','2026-09-14 10:53:38'),(65,65,2,'SIMAD-PU-2026-065','cdfc2bc43e50d5c4162b258819c7e8ac80dd2b26f761c072cabc12e36d11233e',0,NULL,'valid','2026-09-14 10:53:38'),(66,66,2,'SIMAD-PU-2026-066','8ee3f0170d46a5d3864eb86ce5a29e0e50ee631b41a7b76ee5aad4fe1b49a889',0,NULL,'valid','2026-09-14 10:53:38'),(67,67,2,'SIMAD-PU-2026-067','54a94bfc8185d2ef6218e955bb15d7b3d9a6b09f24cefdf8bc71f6d85f5ab753',0,NULL,'valid','2026-09-14 10:53:38'),(68,68,2,'SIMAD-PU-2026-068','16ab79caaa29797384a04cd415062dbd163b885952bc94ecb71cc1e53c432db0',0,NULL,'valid','2026-09-14 10:53:38'),(69,69,2,'SIMAD-PU-2026-069','9f56889550d584373ebf5d6794cb1432047176b45bfa5da09a75270d62bf5258',0,NULL,'valid','2026-09-14 10:53:38'),(70,70,2,'SIMAD-PU-2026-070','f1fc70622c42f16578e28457e27d87612792d539f9269d6b90f1f5dc444b6721',0,NULL,'valid','2026-09-14 10:53:38'),(71,71,2,'SIMAD-PU-2026-071','27f5c1489d8a968df8599daa1c70538687cb8e6ac4a46fcd9b26ee74ade76772',0,NULL,'valid','2026-09-14 10:53:38'),(72,72,2,'SIMAD-PU-2026-072','a4ad66b053a0e1a2167b80dac0e226fece055450e68b41bca23e12bbf8f0f97b',0,NULL,'valid','2026-09-14 10:53:38'),(73,73,2,'SIMAD-PU-2026-073','adc65c9f459c23d4c74a98ef9bffab8ae0552142f0deb1991ce8ca54b4520edf',0,NULL,'valid','2026-09-14 10:53:38'),(74,74,2,'SIMAD-PU-2026-074','a25f20435d819b9cb1b5fa7babc83c4a5dd66c162a9512e47451df1ab974c380',0,NULL,'valid','2026-09-14 10:53:38'),(75,75,2,'SIMAD-PU-2026-075','7859691605602635e4201e8a13bbf20d87f2c37d93d07fd615c498722cdd0d15',0,NULL,'valid','2026-09-14 10:53:38'),(76,76,2,'SIMAD-PU-2026-076','c1232d9fe7072b286aedbfc0e7a6912434b4e2028848f013aaeec4c508d6c230',0,NULL,'valid','2026-09-14 10:53:38'),(77,77,2,'SIMAD-PU-2026-077','bd92ed80ecc7b753b328907d3e28008458a1b73e243a87153806d89833f31d1f',0,NULL,'valid','2026-09-14 10:53:38'),(78,78,2,'SIMAD-PU-2026-078','7d018e3d0dc0271b3085127d223648a32a2862c8a917856cdb9510fc86f525a7',0,NULL,'valid','2026-09-14 10:53:38'),(79,79,2,'SIMAD-PU-2026-079','78c5fcb716c113c326ce1ee520db20f15d60e610d398a5dbb67e7553ca3aedc4',0,NULL,'valid','2026-09-14 10:53:38'),(80,80,2,'SIMAD-PU-2026-080','d18ea2c76cf039afb47c6460421e624e1c30fa22004dc0ecba0645adeeefea84',0,NULL,'valid','2026-09-14 10:53:38'),(81,81,2,'SIMAD-PU-2026-081','ab19ebfaccd073e92f0efa9b8afe67dc76086deb57a37b5479594eb721002e66',0,NULL,'valid','2026-09-14 10:53:38'),(82,82,2,'SIMAD-PU-2026-082','e516c3ba01311a3c742a72a8b58415f959065dd24d8b0de46a463d2b2f159a88',0,NULL,'valid','2026-09-14 10:53:38'),(83,83,2,'SIMAD-PU-2026-083','c3f8bfbbcf80d404148d90db103dbeb3201168e3078e82e43cea349eaa166115',0,NULL,'valid','2026-09-14 10:53:38'),(84,84,2,'SIMAD-PU-2026-084','3323f9c7b8fd287121d2a4c45996a2459f01005424a4a137625899ec6b3a25ef',3,'2026-09-14 13:56:29','valid','2026-09-14 10:53:38');
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cohorts`
--

DROP TABLE IF EXISTS `cohorts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cohorts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `batch_code` varchar(50) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `instructor_name` varchar(120) NOT NULL,
  `instructor_title` varchar(150) DEFAULT NULL,
  `issue_date` date NOT NULL,
  `location` varchar(120) DEFAULT 'Online',
  `status` enum('upcoming','active','completed','archived') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cohorts`
--

LOCK TABLES `cohorts` WRITE;
/*!40000 ALTER TABLE `cohorts` DISABLE KEYS */;
INSERT INTO `cohorts` VALUES (2,'Capacity-Building Workshop on Project and Research Grant Proposal Development','1','2026-09-12','2026-09-13','International','','2026-09-14','SIMAD University, Town Campus','completed','2026-09-14 10:44:06','2026-09-14 10:44:06');
/*!40000 ALTER TABLE `cohorts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `participants`
--

DROP TABLE IF EXISTS `participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `participants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `cohort_id` int(10) unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(191) NOT NULL,
  `status` enum('issued','pending','revoked') DEFAULT 'issued',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cohort_email` (`cohort_id`,`email`),
  KEY `idx_email` (`email`),
  CONSTRAINT `participants_ibfk_1` FOREIGN KEY (`cohort_id`) REFERENCES `cohorts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `participants`
--

LOCK TABLES `participants` WRITE;
/*!40000 ALTER TABLE `participants` DISABLE KEYS */;
INSERT INTO `participants` VALUES (1,2,'Abdiasis Sheikh Abuukar','abdulazizsh221@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(2,2,'Abdikani Salah Abdulle','abdikani@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(3,2,'ABDIKAREM ABDULAHI','darwiish83@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(4,2,'Abdikarim Salad Elmi','Abdulkarim.salad@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(5,2,'Abdirahman Abdinur Awale','Awale10@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(6,2,'Abdirahman Ahmed Abdule','Svea@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(7,2,'Abdirahman Ahmed Abdullahi','craxman22@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(8,2,'Abdirahman Mohammed Elmi','cabdiraxmaan825@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(9,2,'Abdirahman Mohamud Salah','amsalah@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(10,2,'Abdirasak Sharif Ali','Arshamyare@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(11,2,'Abdirizak Osman Abdullahi','Saaqeey@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(12,2,'Abdisalam Yusuf Abdi','jarane@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(13,2,'Abdisalan Aden Mohamed','abdisalan@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(14,2,'Abdishakur Mohamud Hassan','abdishakur.hidigow@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(15,2,'Abdiwali Mohamed Hussein','abdiwelimohamedh@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(16,2,'Abdulkadir Abdullahi Sharif','Abdulkadir@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(17,2,'Abdulkadir Said Ahmed','Elmi@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(18,2,'Abdullahi Hassan Elm','aarrkaa@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(19,2,'Abdullahi Ibrahim hussein','Abdullahiguled1643@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(20,2,'Abdullahi sharif nor','Asharif@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(21,2,'Abdulrazak Nur Mohamed','jurile10@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(22,2,'Abubakar Maxamad Axmad','taakuloow@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(23,2,'Abubakar Maxamad Axmad Cilmi','abucabdiraxmaan114@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(24,2,'Abukar Ali Ahmed','Abukar.ali@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(25,2,'Adnan Abdukadir Ahmed','maazin284@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(26,2,'Ahmed Mohamed Hassan','Ahmed-fidow@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(27,2,'Ahmed Mohamed Hussein Enow','ahmedmxeenow@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(28,2,'Ahmed Omar Siyad','ahmedomars@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(29,2,'Ahmed-Nor Mohamed Abdi','ahmednor@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(30,2,'Ali ismail Gurhan','enggurhan@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(31,2,'Ali Yusuf Hassan','ali.yusuf@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(32,2,'Ayan Muse Osman','ayanmuse74@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(33,2,'Bashiir mukhtar Osman','bashiirleader88@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(34,2,'Basma Mohamed Ahmed','basma@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(35,2,'Dr. Suldaan Cabdullahi Sh Ibrahim','Sultansheikh@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(36,2,'Dr.Abdulkadir Noor jibril','Jibriil@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(37,2,'Dr.Mukhtar Sheikh Mohamud Touryare','Tuuryare@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(38,2,'Faduma Ibrahim Gutale','faadumoibraahim23@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(39,2,'Faduma Jama Hussein','fadumajama@simad.edo.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(40,2,'Fadumo Abdullahi Ahmed','ftmasahra@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(41,2,'Fadumo Ahmed Abdullahi','fatimaahmed@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(42,2,'Fahmo Hussein Ibrahim','fahmohusseini@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(43,2,'Farhia Hassan Mohamud','Farhia@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(44,2,'Fartun Abdullahi Hassan','Dr.Fartunorey@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(45,2,'Fowzia Abdullahi nor','Fowziaabdullahi250@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(46,2,'Ikran Abubakar Mohamed','ikabubakar02@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(47,2,'Jamal Ali Abdulle','jamal.ali@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(48,2,'Jaweriya Bashir Ahmed','jaweriya@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(49,2,'Layla Abdullahi Osman','Layla@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(50,2,'Leila Mohamed Warsame','Leilamwarsame@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(51,2,'Lul Farah Abdullahi','Luulf21@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(52,2,'Mohamed Abdi Dhaqane','dhaqane220@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(53,2,'Mohamed Abdulkadir Mohamud','touryare@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(54,2,'Mohamed Adan Sheik Abdi','Wardiyow114@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(55,2,'Mohamed Ahmed Mahamud','m.yare@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(56,2,'Mohamed Ali Omar','mohaali@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(57,2,'Mohamed Hussein Adam','ganowyare@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(58,2,'Mohamed Ibrahim Ahmed','mohamed.ibrahim@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(59,2,'Mohamed Jama Mohamed','mjaamac201@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(60,2,'Mohamed mahad yousuf','Mahamedmahad9@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(61,2,'Mohamed Mohamud Ali','mohamedmohamud@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(62,2,'Mohamed Warsame','nimcale@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(63,2,'Mohamud Ahmed Mohamed','emaara10@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(64,2,'Mukhtar Adan Hassan','mukhtaarxaaji12@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(65,2,'Mulki Dahir Moalim Adam','Mulki@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(66,2,'Mulki Yakub Abdow','mulkiyakub@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(67,2,'Muna Ga\'al','munagaal@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(68,2,'Nasima Abdimajid Hassan','Nasiimoabdimajiid@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(69,2,'Nasteho Mohamud Mudei','dr.nastehomudei@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(70,2,'Omar Abdi Mohamud','gsinfo@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(71,2,'Omar Arabow Addan','arabow@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(72,2,'Omar Osman Haji Abdi','shamsudiin@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(73,2,'Osman Mohamed Ishaq','Muallif05@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(74,2,'Rahmo mohamed ali','Rahmomohameda@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(75,2,'Sabirin Abdikadir Hersi','sabirahersi@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(76,2,'Sabirin Mohamed Omar','Halagadhyare@gmail.com','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(77,2,'Sadio Yusuf Hassan','Nasiixah@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(78,2,'Shafie Mohamud Shafie','absame.student@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(79,2,'Shuaib Mursal Ibrahim','shuaib.mursal@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(80,2,'Sidomar Osman Farah','Cumarcagacdae@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(81,2,'Umama Dahir Dirshe','oumaimatahir23@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(82,2,'Yahye Ahmed Nageye','yaya86ah@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(83,2,'YONIS ALI MUKHTAR','yonis@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38'),(84,2,'Zeinab Abdirahman Mohamud','Zeinab9393@simad.edu.so','issued','2026-09-14 10:53:38','2026-09-14 10:53:38');
/*!40000 ALTER TABLE `participants` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-14 14:03:11
