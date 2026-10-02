-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 24, 2026 at 06:36 PM
-- Server version: 11.8.9-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u356050643_license_mngr`
--

-- --------------------------------------------------------

--
-- Table structure for table `bundles`
--

CREATE TABLE `bundles` (
  `id` int(11) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `included_modules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`included_modules`)),
  `custom_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_features`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `bundles`
--

INSERT INTO `bundles` (`id`, `slug`, `name`, `description`, `price`, `currency`, `included_modules`, `custom_features`, `is_active`, `created_at`) VALUES
(6, 'core-lead', 'Business Platform Bundle', 'Core SaaS Platform (Retail, Restaurant & Café) + Lead Management CRM System', 69.00, 'USD', '[\"core\", \"leadmanagement\"]', '[\"Everything in Core (Retail + Restaurant + Café)\", \"Lead Management CRM Module Included\", \"Visual Kanban Sales Pipeline & Follow-ups\", \"Quotation Generator & Auto Customer Sync\", \"Two Separate License Keys Emailed Instantly\", \"Priority Updates & Comprehensive Setup Guide\", \"Zero Monthly or Annual Platform Fees\"]', 1, '2026-09-23 18:56:43'),
(7, 'all-in-one', 'Enterprise Suite Bundle Premium', 'Complete All-in-One POS & Business Platform + All 4 Specialized Vertical Modules (Lead CRM, Pharmacy, Salon, Repair)', 299.00, 'USD', '[\"core\", \"leadmanagement\", \"pharmacy\", \"repairtechnician\", \"salon\"]', '[\"Full Platform + All 4 Vertical Modules Included\", \"Lead Management & CRM Module\", \"Pharmacy POS (Batches & Expiry Control)\", \"Salon & Spa (Stylists & Appointment Booking)\", \"Repair Workbench (Tickets & Diagnosis)\", \"Multi-Tenant SaaS Billing & Domain Mapping\", \"Priority VIP Business Deployment Support\"]', 1, '2026-09-23 18:56:43');

-- --------------------------------------------------------

--
-- Table structure for table `licenses`
--

CREATE TABLE `licenses` (
  `id` int(11) NOT NULL,
  `license_key` varchar(64) NOT NULL,
  `product_slug` varchar(64) NOT NULL,
  `client_email` varchar(191) NOT NULL DEFAULT '',
  `bound_domain` varchar(191) DEFAULT NULL,
  `bound_ip` varchar(64) DEFAULT NULL,
  `payment_reference` varchar(191) DEFAULT NULL,
  `plan` varchar(64) DEFAULT NULL,
  `status` enum('active','suspended','revoked','expired') NOT NULL DEFAULT 'active',
  `valid_until` date DEFAULT NULL,
  `last_verified_at` timestamp NULL DEFAULT NULL,
  `last_verified_ip` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `licenses`
--

INSERT INTO `licenses` (`id`, `license_key`, `product_slug`, `client_email`, `bound_domain`, `bound_ip`, `payment_reference`, `plan`, `status`, `valid_until`, `last_verified_at`, `last_verified_ip`, `created_at`) VALUES
(5, 'B0FD2-115FA-F80BA-9ADFF', 'pharmacy', 'admin@zoommarket.test', 'saas.zoomnearby.com', '223.227.71.205', NULL, NULL, 'active', '2029-10-24', '2026-09-24 05:44:51', '74.208.248.88', '2026-09-08 10:20:32'),
(6, '1E654-DCCB9-6CA95-4B20D', 'repairtechnician', 'repair@gmail.com', 'saas.zoomnearby.com', '223.227.71.205', NULL, NULL, 'active', '2030-11-02', '2026-09-24 05:44:51', '74.208.248.88', '2026-09-08 17:28:58'),
(7, '64E39-CED8C-287EE-24DEB', 'salon', 'salon@gmail.com', 'saas.zoomnearby.com', '223.227.71.205', NULL, NULL, 'active', '2030-12-28', '2026-09-24 05:44:51', '74.208.248.88', '2026-09-08 17:31:23'),
(9, 'A39E1-BFC93-F94A3-70644', 'core', 'superadmin@gmail.com', 'crm.zoomnearby.com', '74.208.248.88', NULL, NULL, 'active', '2026-09-15', '2026-09-11 18:28:51', '74.208.248.88', '2026-09-11 18:09:25'),
(11, '6C81A-2FAFF-B51F4-63447', 'pharmacy', 'noemail@gmail.com', 'crm.zoomnearby.com', '106.211.23.210', NULL, NULL, 'active', NULL, '2026-09-11 18:52:50', '74.208.248.88', '2026-09-11 18:33:17'),
(16, '3E1FA-F4091-91A6A-63722', 'leadmanagement', '', 'crm.zoomnearby.com', '74.208.248.88', NULL, NULL, 'active', NULL, '2026-09-12 19:13:08', '74.208.248.88', '2026-09-12 19:12:12'),
(17, '8BA3A-663B2-399B4-27478', 'repairtechnician', '', 'crm.zoomnearby.com', '74.208.248.88', NULL, NULL, 'active', NULL, '2026-09-12 19:33:18', '74.208.248.88', '2026-09-12 19:33:07'),
(18, '851D5-D0283-7D86B-285E9', 'salon', '', 'crm.zoomnearby.com', '74.208.248.88', NULL, NULL, 'active', NULL, '2026-09-12 19:33:35', '74.208.248.88', '2026-09-12 19:33:28'),
(19, '45FA9-FE0EF-02F68-127B7', 'leadmanagement', '', 'saas.zoomnearby.com', '74.208.248.88', NULL, NULL, 'active', NULL, '2026-09-24 05:44:51', '74.208.248.88', '2026-09-13 19:11:12'),
(20, '6E295-65EA5-0F968-BA390', 'core', '', 'saas.zoomnearby.com', '74.208.248.88', NULL, 'extended', 'active', NULL, '2026-09-24 11:20:01', '74.208.248.88', '2026-09-23 11:38:30'),
(23, '8B076-69564-E62D0-40195', 'core', 'sales@zoomnearby.com', 'sales.zoomnearby.com', '106.223.207.170', 'ord_06110a3a3b1669bcf6f934b95984023b4976390c50e97160', NULL, 'active', NULL, NULL, NULL, '2026-09-24 16:54:13'),
(24, '39559-540C7-2714C-A2009', 'core', 'new@zoomnearby.com', 'new.zoomnearby.com', '106.223.207.170', 'ord_1ca3f46eb4d4be82fc4989b6936cd9224a172ce35956d322', NULL, 'active', NULL, NULL, NULL, '2026-09-24 16:55:41'),
(25, '4BC81-65C76-92848-8F981', 'leadmanagement', 'new@zoomnearby.com', 'new.zoomnearby.com', '106.223.207.170', 'ord_1ca3f46eb4d4be82fc4989b6936cd9224a172ce35956d322', NULL, 'active', NULL, NULL, NULL, '2026-09-24 16:55:41'),
(26, '126EC-2EF61-D1FB8-E011B', 'pharmacy', 'new@zoomnearby.com', 'new.zoomnearby.com', '106.223.207.170', 'ord_1ca3f46eb4d4be82fc4989b6936cd9224a172ce35956d322', NULL, 'active', NULL, NULL, NULL, '2026-09-24 16:55:41'),
(27, '9CE02-66A5E-E50B9-DE7E2', 'repairtechnician', 'new@zoomnearby.com', 'new.zoomnearby.com', '106.223.207.170', 'ord_1ca3f46eb4d4be82fc4989b6936cd9224a172ce35956d322', NULL, 'active', NULL, NULL, NULL, '2026-09-24 16:55:41'),
(28, '37A36-65E92-AF667-C2103', 'salon', 'new@zoomnearby.com', 'new.zoomnearby.com', '106.223.207.170', 'ord_1ca3f46eb4d4be82fc4989b6936cd9224a172ce35956d322', NULL, 'active', NULL, NULL, NULL, '2026-09-24 16:55:41'),
(29, 'ZN-POS-7E8A9-4B3C2-EXTENDED', 'core', 'admin@zoomnearby.com', 'pos.zoomnearby.com', '93.127.208.194', NULL, 'extended', 'active', NULL, NULL, NULL, '2026-09-26 02:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `reference` varchar(191) NOT NULL,
  `gateway` varchar(40) NOT NULL DEFAULT '',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'USD',
  `product_slug` varchar(64) NOT NULL,
  `bundle_slug` varchar(64) DEFAULT NULL,
  `license_id` int(11) DEFAULT NULL,
  `items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`items_json`)),
  `email` varchar(191) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'paid',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `order_status` varchar(20) DEFAULT NULL,
  `order_token` varchar(48) DEFAULT NULL,
  `target_domain` varchar(191) DEFAULT NULL,
  `checkout_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`checkout_json`)),
  `gateway_reference` varchar(191) DEFAULT NULL,
  `gateway_url` text DEFAULT NULL,
  `order_error` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `reference`, `gateway`, `amount`, `currency`, `product_slug`, `bundle_slug`, `license_id`, `items_json`, `email`, `status`, `created_at`, `order_status`, `order_token`, `target_domain`, `checkout_json`, `gateway_reference`, `gateway_url`, `order_error`) VALUES
(2, 'ord_06110a3a3b1669bcf6f934b95984023b4976390c50e97160', 'stripe', 49.00, 'USD', 'core', NULL, 23, '[{\"id\":23,\"slug\":\"core\",\"name\":\"Core SaaS Platform (Retail, Restaurant & Caf\\u00e9)\",\"license_key\":\"8B076-69564-E62D0-40195\",\"valid_until\":null,\"download_token\":\"f1d779a6253dd3ad4784d30cfc3415eaf0096b19c500d8fed753e1f36b192d7c\"}]', 'sales@zoomnearby.com', 'unpaid', '2026-09-24 15:29:35', 'completed', '06110a3a3b1669bcf6f934b95984023b4976390c50e97160', 'sales.zoomnearby.com', '{\"title\":\"Core SaaS Platform (Retail, Restaurant & Café)\",\"description\":\"Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café \\/ Quick-Service modules.\",\"products\":[{\"id\":19,\"slug\":\"core\",\"name\":\"Core SaaS Platform (Retail, Restaurant & Café)\",\"description\":\"Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café \\/ Quick-Service modules.\",\"custom_features\":\"[\\\"Retail POS Module (Barcodes, variants & stock)\\\",\\\"Restaurant POS Module (Tables, KOT & KDS)\\\",\\\"Caf\\\\u00e9 & Quick-Service Module (Fast checkout)\\\",\\\"Multi-Store Warehousing & Stock Transfers\\\",\\\"Thermal Receipts (80\\\\\\/58mm) & Barcode Labels\\\",\\\"Unlimited Stores, Registers & Cashiers\\\",\\\"100% Unencrypted Full PHP Source Code\\\"]\",\"price\":\"49.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 14:08:14\",\"created_at\":\"2026-09-11 18:13:17\"}],\"amount\":49,\"currency\":\"USD\",\"bundle\":\"\",\"domain\":\"sales.zoomnearby.com\",\"email\":\"sales@zoomnearby.com\",\"gateway\":\"stripe\",\"return\":\"https:\\/\\/buy-crm.zoomnearby.com\\/\\/index.php?order=complete\"}', 'cs_live_a10sNeDSz55qTAdl3nDA88v4GQKuAQbv0g70JwTVisBCyPFXRr3oN0EGqL', 'https://checkout.stripe.com/g/pay/cs_live_a10sNeDSz55qTAdl3nDA88v4GQKuAQbv0g70JwTVisBCyPFXRr3oN0EGqL#fidnandhYHdWcXxpYCc%2FJ2FgY2RwaXEnKSdicyc%2FNiknYnUnPzcpJ2JpJz81KSdkdWxOYHwnPyd1blppbHNgWkd9cFNLUD1VYGtgYHd1YmJRVD1DbXxWXScpJ2N3amhWYHdzYHcnP3F3cGApJ2dkZm5id2pwa2FGamlqdyc%2FJyZjY2NjY2MnKSdpZHxqcHFRfHVgJz8ndmxrYmlgWmxxYGgnKSdga2RnaWBVaWRmYG1qaWFgd3YnP3F3cGB4JSUl', NULL),
(3, 'ord_1ca3f46eb4d4be82fc4989b6936cd9224a172ce35956d322', 'stripe', 299.00, 'USD', 'bundle:all-in-one', 'all-in-one', 24, '[{\"id\":24,\"slug\":\"core\",\"name\":\"Core SaaS Platform (Retail, Restaurant & Caf\\u00e9)\",\"license_key\":\"39559-540C7-2714C-A2009\",\"valid_until\":null,\"download_token\":\"e1a14ad580ce4f3059d70e176941b2e3f1109eacc6484ed9f28c5df99e586d77\"},{\"id\":25,\"slug\":\"leadmanagement\",\"name\":\"Lead Management System\",\"license_key\":\"4BC81-65C76-92848-8F981\",\"valid_until\":null,\"download_token\":\"e854e477804918ed88ecc41f91d3d6802247c7f5abe1974c300657630e7b7c68\"},{\"id\":26,\"slug\":\"pharmacy\",\"name\":\"Pharmacy POS for SaaS\",\"license_key\":\"126EC-2EF61-D1FB8-E011B\",\"valid_until\":null,\"download_token\":\"c7eca6568b896f253cb217fa29c91eea40ca0169b9058f90fbb183dc1a7e3ded\"},{\"id\":27,\"slug\":\"repairtechnician\",\"name\":\"Repair Service Provider\",\"license_key\":\"9CE02-66A5E-E50B9-DE7E2\",\"valid_until\":null,\"download_token\":\"2776824154296535cedb4394da2e18454798ab04a46349c20fe7e667773098f1\"},{\"id\":28,\"slug\":\"salon\",\"name\":\"Salon Management System\",\"license_key\":\"37A36-65E92-AF667-C2103\",\"valid_until\":null,\"download_token\":\"cc954562be7e38c2efa19430143c6bbe16ccf4321509db1d2029b6adf7621f1c\"}]', 'new@zoomnearby.com', 'unpaid', '2026-09-24 15:46:00', 'completed', '1ca3f46eb4d4be82fc4989b6936cd9224a172ce35956d322', 'new.zoomnearby.com', '{\"title\":\"Enterprise Suite Bundle Premium\",\"description\":\"Complete All-in-One POS & Business Platform + All 4 Specialized Vertical Modules (Lead CRM, Pharmacy, Salon, Repair)\",\"products\":[{\"id\":19,\"slug\":\"core\",\"name\":\"Core SaaS Platform (Retail, Restaurant & Café)\",\"description\":\"Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café \\/ Quick-Service modules.\",\"custom_features\":\"[\\\"Retail POS Module (Barcodes, variants & stock)\\\",\\\"Restaurant POS Module (Tables, KOT & KDS)\\\",\\\"Caf\\\\u00e9 & Quick-Service Module (Fast checkout)\\\",\\\"Multi-Store Warehousing & Stock Transfers\\\",\\\"Thermal Receipts (80\\\\\\/58mm) & Barcode Labels\\\",\\\"Unlimited Stores, Registers & Cashiers\\\",\\\"100% Unencrypted Full PHP Source Code\\\"]\",\"price\":\"49.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 14:08:14\",\"created_at\":\"2026-09-11 18:13:17\"},{\"id\":21,\"slug\":\"leadmanagement\",\"name\":\"Lead Management System\",\"description\":\"Lead tracking, CRM pipeline, follow-ups, and sales conversion module.\",\"custom_features\":null,\"price\":\"29.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 13:58:33\",\"created_at\":\"2026-09-13 19:10:23\"},{\"id\":14,\"slug\":\"pharmacy\",\"name\":\"Pharmacy POS for SaaS\",\"description\":\"Standalone pharmacy vertical: drug batch & expiry tracking, prescription intake and dispensing.\",\"custom_features\":null,\"price\":\"25.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 13:59:02\",\"created_at\":\"2026-09-08 09:19:19\"},{\"id\":16,\"slug\":\"repairtechnician\",\"name\":\"Repair Service Provider\",\"description\":\"Standalone repair vertical: device intake tickets, diagnostic checklist, parts & labor, lifecycle status and pickup.\",\"custom_features\":null,\"price\":\"25.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 13:59:24\",\"created_at\":\"2026-09-08 10:29:44\"},{\"id\":15,\"slug\":\"salon\",\"name\":\"Salon Management System\",\"description\":\"Standalone salon vertical: service catalogue, stylists \\/ specialists, appointment booking and lifecycle.\",\"custom_features\":null,\"price\":\"25.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 13:59:31\",\"created_at\":\"2026-09-08 10:29:01\"}],\"amount\":299,\"currency\":\"USD\",\"bundle\":\"all-in-one\",\"domain\":\"new.zoomnearby.com\",\"email\":\"new@zoomnearby.com\",\"gateway\":\"stripe\",\"return\":\"https:\\/\\/buy-crm.zoomnearby.com\\/\\/index.php?order=complete\"}', NULL, NULL, NULL),
(4, 'ord_bdd530d276df8457676ed7c0c7e949c39877bf551df2bf8b', 'stripe', 299.00, 'USD', 'bundle:all-in-one', 'all-in-one', NULL, NULL, 'new@zoomnearby.com', 'unpaid', '2026-09-24 18:26:58', 'pending', 'bdd530d276df8457676ed7c0c7e949c39877bf551df2bf8b', 'new.zoomnearby.com', '{\"title\":\"Enterprise Suite Bundle Premium\",\"description\":\"Complete All-in-One POS & Business Platform + All 4 Specialized Vertical Modules (Lead CRM, Pharmacy, Salon, Repair)\",\"products\":[{\"id\":19,\"slug\":\"core\",\"name\":\"Core SaaS Platform (Retail, Restaurant & Café)\",\"description\":\"Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café \\/ Quick-Service modules.\",\"custom_features\":\"[\\\"Retail POS Module (Barcodes, variants & stock)\\\",\\\"Restaurant POS Module (Tables, KOT & KDS)\\\",\\\"Caf\\\\u00e9 & Quick-Service Module (Fast checkout)\\\",\\\"Multi-Store Warehousing & Stock Transfers\\\",\\\"Thermal Receipts (80\\\\\\/58mm) & Barcode Labels\\\",\\\"Unlimited Stores, Registers & Cashiers\\\",\\\"100% Unencrypted Full PHP Source Code\\\"]\",\"price\":\"49.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 14:08:14\",\"created_at\":\"2026-09-11 18:13:17\"},{\"id\":21,\"slug\":\"leadmanagement\",\"name\":\"Lead Management System\",\"description\":\"Lead tracking, CRM pipeline, follow-ups, and sales conversion module.\",\"custom_features\":null,\"price\":\"29.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 13:58:33\",\"created_at\":\"2026-09-13 19:10:23\"},{\"id\":14,\"slug\":\"pharmacy\",\"name\":\"Pharmacy POS for SaaS\",\"description\":\"Standalone pharmacy vertical: drug batch & expiry tracking, prescription intake and dispensing.\",\"custom_features\":null,\"price\":\"25.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 13:59:02\",\"created_at\":\"2026-09-08 09:19:19\"},{\"id\":16,\"slug\":\"repairtechnician\",\"name\":\"Repair Service Provider\",\"description\":\"Standalone repair vertical: device intake tickets, diagnostic checklist, parts & labor, lifecycle status and pickup.\",\"custom_features\":null,\"price\":\"25.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 13:59:24\",\"created_at\":\"2026-09-08 10:29:44\"},{\"id\":15,\"slug\":\"salon\",\"name\":\"Salon Management System\",\"description\":\"Standalone salon vertical: service catalogue, stylists \\/ specialists, appointment booking and lifecycle.\",\"custom_features\":null,\"price\":\"25.00\",\"currency\":\"USD\",\"is_active\":1,\"package_uploaded_at\":\"2026-09-24 13:59:31\",\"created_at\":\"2026-09-08 10:29:01\"}],\"amount\":299,\"currency\":\"USD\",\"bundle\":\"all-in-one\",\"domain\":\"new.zoomnearby.com\",\"email\":\"new@zoomnearby.com\",\"gateway\":\"stripe\",\"return\":\"https:\\/\\/buy-crm.zoomnearby.com\\/\\/index.php?order=complete\"}', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `slug` varchar(64) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `custom_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`custom_features`)),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `package_uploaded_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `slug`, `name`, `description`, `custom_features`, `price`, `currency`, `is_active`, `package_uploaded_at`, `created_at`) VALUES
(14, 'pharmacy', 'Pharmacy POS for SaaS', 'Standalone pharmacy vertical: drug batch & expiry tracking, prescription intake and dispensing.', NULL, 25.00, 'USD', 1, '2026-09-24 13:59:02', '2026-09-08 09:19:19'),
(15, 'salon', 'Salon Management System', 'Standalone salon vertical: service catalogue, stylists / specialists, appointment booking and lifecycle.', NULL, 25.00, 'USD', 1, '2026-09-24 13:59:31', '2026-09-08 10:29:01'),
(16, 'repairtechnician', 'Repair Service Provider', 'Standalone repair vertical: device intake tickets, diagnostic checklist, parts & labor, lifecycle status and pickup.', NULL, 25.00, 'USD', 1, '2026-09-24 13:59:24', '2026-09-08 10:29:44'),
(19, 'core', 'Core SaaS Platform (Retail, Restaurant & Café)', 'Multi-tenant POS & Business SaaS platform core platform engine with built-in Retail POS, Restaurant (Dine-in, Tables & KOT), and Café / Quick-Service modules.', '[\"Retail POS Module (Barcodes, variants & stock)\",\"Restaurant POS Module (Tables, KOT & KDS)\",\"Caf\\u00e9 & Quick-Service Module (Fast checkout)\",\"Multi-Store Warehousing & Stock Transfers\",\"Thermal Receipts (80\\/58mm) & Barcode Labels\",\"Unlimited Stores, Registers & Cashiers\",\"100% Unencrypted Full PHP Source Code\"]', 49.00, 'USD', 1, '2026-09-24 14:08:14', '2026-09-11 18:13:17'),
(21, 'leadmanagement', 'Lead Management System', 'Lead tracking, CRM pipeline, follow-ups, and sales conversion module.', NULL, 29.00, 'USD', 1, '2026-09-24 13:58:33', '2026-09-13 19:10:23');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `k` varchar(64) NOT NULL,
  `v` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`k`, `v`) VALUES
('currency', 'USD'),
('envato_item_id', ''),
('gateway', 'stripe'),
('landing_page_config', '{\n    \"brand_name\": \"Zoom POS & Market\",\n    \"brand_tagline\": \"Smarter Business. Greater Control.\",\n    \"support_email\": \"support@zoomnearby.com\",\n    \"currency_code\": \"USD\",\n    \"currency_symbol\": \"$\",\n    \"hero_badge\": \"Complete Business Management Platform\",\n    \"hero_title\": \"Run Your Entire Business<br>From <span>One Powerful POS<\\/span>\",\n    \"hero_subtitle\": \"Retail, Restaurant & Service — sales, inventory, customers, reports and more in one self-hosted platform.\",\n    \"cta_primary\": \"Buy Now\",\n    \"cta_secondary\": \"Live Demo\",\n    \"cta_verify\": \"Verify License\",\n    \"demo_admin_url\": \"https:\\/\\/saas.zoomnearby.com\\/login\",\n    \"demo_admin_title\": \"SuperAdmin SaaS Portal\",\n    \"demo_admin_desc\": \"Manage SaaS subscription packages, tenant stores, payment gateways, and system settings.\",\n    \"demo_store_url\": \"https:\\/\\/saas.zoomnearby.com\\/store\\/login\",\n    \"demo_store_title\": \"Store & Cashier Backoffice\",\n    \"demo_store_desc\": \"Staff and cashier portal for catalog, orders, table floorplans, and billing settlement.\",\n    \"demo_flutter_web_url\": \"https:\\/\\/saas.zoomnearby.com\\/pos-web\\/\",\n    \"demo_flutter_web_title\": \"Flutter Web POS\",\n    \"demo_flutter_web_desc\": \"Instant browser-based POS terminal with touch UI, barcode scanning & receipt printing.\",\n    \"demo_flutter_windows_url\": \"https:\\/\\/saas.zoomnearby.com\\/Zoom-Sales-CRM-Setup1.0.4.exe\",\n    \"demo_flutter_windows_title\": \"Flutter Windows Desktop App\",\n    \"demo_flutter_windows_desc\": \"Native 64-bit Windows desktop installer with ESC\\/POS thermal receipt printer integration.\",\n    \"demo_flutter_android_url\": \"https:\\/\\/saas.zoomnearby.com\\/zoom-pos-v1.0.2.apk\",\n    \"demo_flutter_android_title\": \"Flutter Android POS App\",\n    \"demo_flutter_android_desc\": \"Native Android APK build optimized for handheld wireless terminals, smartphones, and tablets.\",\n    \"documentation_url\": \"https:\\/\\/saas.zoomnearby.com\\/documentation\",\n    \"documentation_title\": \"Documentation & Setup Guide\",\n    \"documentation_desc\": \"Comprehensive developer and administrator installation guide.\",\n    \"demo_other_links\": [\n        {\n            \"title\": \"Customer Ordering Web Storefront\",\n            \"desc\": \"Interactive customer self-service catalog, table ordering & online checkout.\",\n            \"url\": \"https:\\/\\/saas.zoomnearby.com\\/store\",\n            \"icon\": \"🛒\",\n            \"badge\": \"Online Storefront\",\n            \"btn_text\": \"Open Storefront ↗\"\n        }\n    ],\n    \"metric_1_val\": \"$49.00\",\n    \"metric_1_label\": \"One-Time Core Script Price\",\n    \"metric_2_val\": \"100%\",\n    \"metric_2_label\": \"Self-Hosted Source Code\",\n    \"metric_3_val\": \"Instant\",\n    \"metric_3_label\": \"License Key Email Delivery\",\n    \"metric_4_val\": \"Unlimited\",\n    \"metric_4_label\": \"Stores, Cashiers & Registers\",\n    \"discount_tier_1\": 10,\n    \"discount_tier_2\": 15,\n    \"discount_tier_3\": 20,\n    \"features\": [\n        {\n            \"icon\": \"🛒\",\n            \"title\": \"Retail POS Engine\",\n            \"desc\": \"High-speed barcode scanner checkout, variant inventory, customer credit accounts, return handling, and price label printing.\"\n        },\n        {\n            \"icon\": \"🍽️\",\n            \"title\": \"Restaurant & Dine-In (Tables + KOT)\",\n            \"desc\": \"Visual floor & table layout management, Kitchen Order Tickets (KOT) printing\\/display, waiter ordering, and split bill checkout.\"\n        },\n        {\n            \"icon\": \"☕\",\n            \"title\": \"Café & Quick-Service Counter\",\n            \"desc\": \"Rapid touch-optimized ordering, modifiers\\/addons, kitchen queue tokens, and swift card\\/cash cashier settlement.\"\n        },\n        {\n            \"icon\": \"🏬\",\n            \"title\": \"Multi-Store & Warehousing\",\n            \"desc\": \"Manage multiple stores and stock warehouses from one screen. Inter-branch stock transfers and low inventory warnings.\"\n        },\n        {\n            \"icon\": \"🖨️\",\n            \"title\": \"Thermal Receipt & Barcode Printing\",\n            \"desc\": \"Direct ESC\\/POS 80mm & 58mm thermal printer support, PDF invoices, customized receipts, and automatic barcode sticker generator.\"\n        },\n        {\n            \"icon\": \"🌐\",\n            \"title\": \"Multi-Tenant SaaS Architecture\",\n            \"desc\": \"Create pricing subscription packages, allow business tenants to register, manage their billing, and connect custom domains.\"\n        }\n    ],\n    \"faqs\": [\n        {\n            \"q\": \"What is included in the Core main script?\",\n            \"a\": \"The Core main script includes full Retail POS, Restaurant POS (with Table Management, Kitchen Order Tickets \\/ KOT, and Waiter workflow), and Café \\/ Quick-Service modes built-in out of the box. It also includes multi-store warehousing, thermal receipt printing (80mm\\/58mm), barcode generation, customer ledgers, and the complete multi-tenant SaaS billing engine.\"\n        },\n        {\n            \"q\": \"What do I receive after completing payment?\",\n            \"a\": \"Immediately upon purchase, your license details are rendered on screen and sent to your registered email address. This includes your official license key for the Core SaaS platform, plus individual license keys for any add-on modules purchased in your bundle, with simple setup steps.\"\n        },\n        {\n            \"q\": \"Can I host this on any domain, cPanel, or VPS?\",\n            \"a\": \"Yes! The system is designed to run on any standard hosting environment with PHP 8.2+ and MySQL. It runs perfectly on cPanel, CloudPanel, DirectAdmin, Ubuntu VPS, AWS, or DigitalOcean with standard Apache or Nginx.\"\n        },\n        {\n            \"q\": \"How does bundle pricing work?\",\n            \"a\": \"You can purchase the Core SaaS script for $49.00. If you wish to bundle other modules (such as Lead Manager, Pharmacy POS, or Salon), you can either select our discounted ready-made bundles or use our interactive bundle builder to select exactly the modules you need with automatic bundle discounts applied.\"\n        },\n        {\n            \"q\": \"How do I activate vertical modules like Lead Manager?\",\n            \"a\": \"In your self-hosted SaaS SuperAdmin panel, navigate to Modules. Find the purchased module, click Activate \\/ Download, and enter the module\'s license key sent to your email. The system securely downloads the module package from the central license server and installs it automatically.\"\n        },\n        {\n            \"q\": \"Are there any recurring monthly subscription fees?\",\n            \"a\": \"No! You pay once for a lifetime perpetual license. You own the code and can use it forever on your registered domain without recurring platform fees.\"\n        }\n    ]\n}'),
('license_public_url', 'https://license.zoomnearby.com'),
('mail_driver', 'smtp'),
('mail_from_address', 'sales@zoomnearby.com'),
('mail_from_name', 'Zoom Sales CRM & Inventory'),
('pkg_file_core', 'core-32f1a83f4518a80d4480b653.zip'),
('pkg_file_leadmanagement', 'leadmanagement-a3d1cf7457319fa09c4a2c56.zip'),
('pkg_file_pharmacy', 'pharmacy-f49c64f34d124af9d61d9a23.zip'),
('pkg_file_repairtechnician', 'repairtechnician-1836724f1863ff725c0e61ee.zip'),
('pkg_file_salon', 'salon-614a7bdf7f4baf8d9601ab42.zip'),
('razorpay_key_id', 'rzp_test_placeholder'),
('razorpay_key_secret', 'razorpay_secret_placeholder'),
('site_name', 'Zoom Sales CRM & Inventory'),
('smtp_encryption', 'tls'),
('smtp_host', 'smtp.gmail.com'),
('smtp_pass', 'smtp_password_placeholder'),
('smtp_port', '587'),
('smtp_user', 'admin@example.com'),
('stripe_publishable_key', 'pk_live_placeholder'),
('stripe_secret_key', 'sk_live_placeholder'),
('stripe_webhook_secret', 'whsec_placeholder'),
('validation_mode', 'native');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bundles`
--
ALTER TABLE `bundles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `licenses`
--
ALTER TABLE `licenses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `license_key` (`license_key`),
  ADD KEY `idx_lookup` (`license_key`,`product_slug`),
  ADD KEY `idx_product` (`product_slug`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD UNIQUE KEY `order_token` (`order_token`),
  ADD UNIQUE KEY `gateway_reference` (`gateway_reference`),
  ADD KEY `idx_license` (`license_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`k`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bundles`
--
ALTER TABLE `bundles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `licenses`
--
ALTER TABLE `licenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
