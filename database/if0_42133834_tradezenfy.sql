-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql113.infinityfree.com
-- Generation Time: Jun 25, 2026 at 12:26 AM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_42133834_tradezenfy`
--

-- --------------------------------------------------------

--
-- Table structure for table `account_registrations`
--

CREATE TABLE `account_registrations` (
  `id` int(11) NOT NULL,
  `title` varchar(10) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `dob` date DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `street` text DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `state_citizenship` varchar(100) DEFAULT NULL,
  `state_tax_residency` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `employment_status` varchar(50) DEFAULT NULL,
  `trading_currency` varchar(50) DEFAULT 'INR',
  `annual_income` decimal(15,2) DEFAULT NULL,
  `net_worth` decimal(15,2) DEFAULT NULL,
  `source_of_wealth` text DEFAULT NULL,
  `initial_deposit` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `current_balance` decimal(15,2) DEFAULT 0.00,
  `portfolio_value` decimal(15,2) DEFAULT 0.00,
  `total_invested` decimal(15,2) DEFAULT 0.00,
  `total_pnl` decimal(15,2) DEFAULT 0.00,
  `is_blocked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `account_registrations`
--

INSERT INTO `account_registrations` (`id`, `title`, `first_name`, `last_name`, `email`, `username`, `password`, `dob`, `phone`, `street`, `postal_code`, `city`, `country`, `state_citizenship`, `state_tax_residency`, `tax_id`, `employment_status`, `trading_currency`, `annual_income`, `net_worth`, `source_of_wealth`, `initial_deposit`, `status`, `current_balance`, `portfolio_value`, `total_invested`, `total_pnl`, `is_blocked`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Test', 'User', 'test@alphanumeric.com', 'aaaa', '$2y$12$19rSh9RCdM0jdFbrUgAweuQyP6yAHC2v9rtUePQsvEWOqpRPk1GpO', NULL, '9999999999', NULL, NULL, NULL, 'India', NULL, NULL, NULL, NULL, 'INR', NULL, NULL, NULL, NULL, 'approved', '30034.85', '79834.15', '82086.15', '0.00', 0, '2026-05-23 05:44:40', '2026-06-09 07:55:23');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@alphanumerical.com', 'System Admin', 'super_admin', 1, '2026-06-25 04:24:22', '2026-05-23 05:44:39', '2026-06-25 04:24:22');

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_log`
--

CREATE TABLE `admin_activity_log` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_activity_log`
--

INSERT INTO `admin_activity_log` (`id`, `admin_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '::1', '2026-05-23 05:45:27'),
(2, 1, 'LOGOUT', 'admin', 1, 'Admin logged out', '::1', '2026-05-23 05:45:55'),
(3, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '::1', '2026-05-23 06:05:02'),
(4, 1, 'LOGOUT', 'admin', 1, 'Admin logged out', '::1', '2026-05-23 06:12:00'),
(5, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '::1', '2026-06-07 18:02:11'),
(6, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '::1', '2026-06-08 09:49:53'),
(7, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '::1', '2026-06-08 12:34:18'),
(8, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '::1', '2026-06-08 13:36:43'),
(9, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '::1', '2026-06-08 16:23:08'),
(10, 1, 'LOGOUT', 'admin', 1, 'Admin logged out', '::1', '2026-06-08 16:23:18'),
(11, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '152.59.10.186', '2026-06-09 07:54:53'),
(12, 1, 'DEPOSIT_APPROVED', 'payments', 1, 'Test User | ₹12121.00 | approved', '152.59.10.186', '2026-06-09 07:55:23'),
(13, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '106.219.86.172', '2026-06-17 10:00:26'),
(14, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '104.28.37.202', '2026-06-17 10:00:30'),
(15, 1, 'LOGOUT', 'admin', 1, 'Admin logged out', '106.219.86.172', '2026-06-17 10:01:01'),
(16, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '103.87.64.181', '2026-06-19 06:15:30'),
(17, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '103.87.64.181', '2026-06-19 06:23:08'),
(18, 1, 'LOGOUT', 'admin', 1, 'Admin logged out', '103.87.64.181', '2026-06-19 06:23:17'),
(19, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '103.87.64.181', '2026-06-19 06:23:22'),
(20, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 06:26:33'),
(21, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 06:28:09'),
(22, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 07:12:08'),
(23, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 07:19:59'),
(24, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 07:20:35'),
(25, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '103.87.64.181', '2026-06-19 07:32:43'),
(26, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 07:33:09'),
(27, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 07:33:30'),
(28, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 07:37:17'),
(29, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 07:37:48'),
(30, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 07:37:52'),
(31, 1, 'LOGOUT', 'admin', 1, 'Admin logged out', '103.87.64.181', '2026-06-19 07:38:06'),
(32, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '103.87.64.181', '2026-06-19 09:36:11'),
(33, 1, 'LOGOUT', 'admin', 1, 'Admin logged out', '103.87.64.181', '2026-06-19 09:36:41'),
(34, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '103.87.64.181', '2026-06-19 12:38:31'),
(35, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 12:38:46'),
(36, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 12:38:47'),
(37, 1, 'API_SETTINGS_UPDATED', 'api_settings', NULL, 'API settings updated', '103.87.64.181', '2026-06-19 12:38:47'),
(38, 1, 'LOGOUT', 'admin', 1, 'Admin logged out', '103.87.64.181', '2026-06-19 12:38:52'),
(39, 1, 'LOGIN', 'admin', 1, 'Admin logged in', '103.216.234.23', '2026-06-25 04:24:22');

-- --------------------------------------------------------

--
-- Table structure for table `admin_bank_accounts`
--

CREATE TABLE `admin_bank_accounts` (
  `id` int(11) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(20) NOT NULL,
  `upi_id` varchar(255) DEFAULT NULL,
  `qr_code_image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `display_order` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_bank_accounts`
--

INSERT INTO `admin_bank_accounts` (`id`, `account_name`, `bank_name`, `account_number`, `ifsc_code`, `upi_id`, `qr_code_image`, `is_active`, `is_default`, `display_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'hululu Info', 'IDFC', '909090909090', 'IDFC000123', 'Company@ybl', 'uploads/qr-codes/qr_6a11452b05ee4_1779516715.jfif', 1, 0, 0, 1, '2026-05-23 06:11:56', '2026-05-23 06:11:56');

-- --------------------------------------------------------

--
-- Table structure for table `api_settings`
--

CREATE TABLE `api_settings` (
  `id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL COMMENT 'yahoo_finance, angel_one',
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `client_id` varchar(100) DEFAULT NULL,
  `client_secret` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `totp_secret` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0 COMMENT '0=inactive, 1=active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `api_settings`
--

INSERT INTO `api_settings` (`id`, `provider`, `api_key`, `api_secret`, `client_id`, `client_secret`, `password`, `totp_secret`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'yahoo_finance', NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-06-19 06:25:07', '2026-06-19 06:25:07'),
(2, 'angel_one', 'BFlnbdJR', NULL, 'HSHH1260', NULL, '4267', 'KE6XVJ7AFXZJMERJ6UR4IRQJ7M', 1, '2026-06-19 06:25:07', '2026-06-19 07:37:17');

-- --------------------------------------------------------

--
-- Table structure for table `compliance_blocked`
--

CREATE TABLE `compliance_blocked` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `data_provider_preferences`
--

CREATE TABLE `data_provider_preferences` (
  `id` int(11) NOT NULL,
  `asset_type` varchar(50) NOT NULL COMMENT 'stocks, commodities, indices, crypto, forex',
  `provider` varchar(50) NOT NULL DEFAULT 'yahoo_finance' COMMENT 'yahoo_finance or angel_one',
  `is_enabled` tinyint(1) DEFAULT 1 COMMENT '0=disabled, 1=enabled',
  `priority` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `data_provider_preferences`
--

INSERT INTO `data_provider_preferences` (`id`, `asset_type`, `provider`, `is_enabled`, `priority`, `created_at`, `updated_at`) VALUES
(1, 'stocks', 'angel_one', 1, 1, '2026-06-19 06:25:07', '2026-06-19 12:41:39'),
(2, 'commodities', 'angel_one', 1, 1, '2026-06-19 06:25:07', '2026-06-19 12:41:39'),
(3, 'indices', 'angel_one', 1, 1, '2026-06-19 06:25:07', '2026-06-19 12:41:39'),
(4, 'crypto', 'yahoo_finance', 1, 1, '2026-06-19 06:25:07', '2026-06-19 12:26:03'),
(5, 'forex', 'yahoo_finance', 1, 1, '2026-06-19 06:25:07', '2026-06-19 12:26:03');

-- --------------------------------------------------------

--
-- Table structure for table `fno_contracts`
--

CREATE TABLE `fno_contracts` (
  `id` int(11) NOT NULL,
  `symbol` varchar(50) NOT NULL,
  `stock_name` varchar(100) DEFAULT NULL,
  `contract_type` enum('FUTURES','CALL','PUT') NOT NULL,
  `strike_price` decimal(12,2) DEFAULT 0.00,
  `expiry_date` date NOT NULL,
  `lot_size` int(11) NOT NULL,
  `current_price` decimal(12,2) DEFAULT NULL,
  `previous_close` decimal(12,2) DEFAULT NULL,
  `change_percent` decimal(10,4) DEFAULT NULL,
  `high_price` decimal(12,2) DEFAULT NULL,
  `low_price` decimal(12,2) DEFAULT NULL,
  `volume` bigint(20) DEFAULT NULL,
  `open_interest` bigint(20) DEFAULT NULL,
  `premium` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fno_contracts`
--

INSERT INTO `fno_contracts` (`id`, `symbol`, `stock_name`, `contract_type`, `strike_price`, `expiry_date`, `lot_size`, `current_price`, `previous_close`, `change_percent`, `high_price`, `low_price`, `volume`, `open_interest`, `premium`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'RELIANCE', 'Reliance Industries Ltd', 'FUTURES', '0.00', '2025-04-28', 250, '2456.75', '2445.30', '0.4700', '2468.90', '2438.50', 15678234, 45678901, '0.00', 1, '2026-05-23 05:44:41', '2026-05-23 05:44:41'),
(2, 'RELIANCE', 'Reliance Industries Ltd', 'CALL', '2400.00', '2025-04-28', 250, '68.50', '65.20', '5.0600', '72.30', '63.40', 8934567, 12345678, '68.50', 1, '2026-05-23 05:44:41', '2026-05-23 05:44:41'),
(3, 'TCS', 'Tata Consultancy Services', 'FUTURES', '0.00', '2025-04-28', 150, '3678.90', '3665.40', '0.3700', '3692.50', '3658.20', 9876543, 23456789, '0.00', 1, '2026-05-23 05:44:41', '2026-05-23 05:44:41');

-- --------------------------------------------------------

--
-- Table structure for table `fno_orders`
--

CREATE TABLE `fno_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `order_type` enum('BUY','SELL') NOT NULL,
  `contract_type` enum('FUTURES','CALL','PUT') NOT NULL,
  `quantity` int(11) NOT NULL,
  `entry_price` decimal(12,2) NOT NULL,
  `exit_price` decimal(12,2) DEFAULT 0.00,
  `lot_size` int(11) NOT NULL,
  `premium_paid` decimal(12,2) DEFAULT 0.00,
  `margin_used` decimal(12,2) DEFAULT 0.00,
  `pnl` decimal(12,2) DEFAULT 0.00,
  `status` enum('PENDING','EXECUTED','CLOSED','CANCELLED','EXPIRED') DEFAULT 'PENDING',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `executed_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `stop_loss` decimal(12,2) DEFAULT NULL,
  `target` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fno_positions`
--

CREATE TABLE `fno_positions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `contract_type` enum('FUTURES','CALL','PUT') NOT NULL,
  `position_type` enum('BUY','SELL') NOT NULL,
  `quantity` int(11) NOT NULL,
  `entry_price` decimal(12,2) NOT NULL,
  `current_price` decimal(12,2) NOT NULL,
  `margin_used` decimal(12,2) DEFAULT 0.00,
  `pnl` decimal(12,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `stop_loss` decimal(12,2) DEFAULT NULL,
  `target` decimal(12,2) DEFAULT NULL,
  `opened_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fno_transactions`
--

CREATE TABLE `fno_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `transaction_type` enum('MARGIN_DEBIT','MARGIN_CREDIT','PREMIUM_PAID','PREMIUM_RECEIVED','Pnl_SETTLEMENT','EXPIRY_SETTLEMENT') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fno_watchlist`
--

CREATE TABLE `fno_watchlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `proof_image` varchar(255) DEFAULT NULL,
  `utr_number` varchar(50) DEFAULT NULL,
  `payment_proof` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `admin_remark` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `user_id`, `amount`, `payment_method`, `transaction_id`, `proof_image`, `utr_number`, `payment_proof`, `status`, `admin_notes`, `admin_remark`, `approved_by`, `approved_at`, `created_at`) VALUES
(1, 1, '12121.00', 'upi', '123432123', NULL, NULL, NULL, 'approved', NULL, 'approved', 1, '2026-06-09 07:55:23', '2026-06-09 07:50:39');

-- --------------------------------------------------------

--
-- Table structure for table `stocks`
--

CREATE TABLE `stocks` (
  `id` int(11) NOT NULL,
  `symbol` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `exchange` varchar(20) DEFAULT 'NSE',
  `industry` varchar(100) DEFAULT NULL,
  `sector` varchar(100) DEFAULT NULL,
  `market_cap` bigint(20) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `ltp` decimal(10,2) DEFAULT 0.00,
  `change_percent` decimal(10,4) DEFAULT 0.0000,
  `previous_close` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `stocks`
--

INSERT INTO `stocks` (`id`, `symbol`, `name`, `exchange`, `industry`, `sector`, `market_cap`, `logo_url`, `website`, `ltp`, `change_percent`, `previous_close`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'RELIANCE', 'Reliance Industries Ltd', 'NSE', NULL, 'Energy', NULL, NULL, 'ril.com', '2456.75', '0.4700', '2445.30', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(2, 'TCS', 'Tata Consultancy Services', 'NSE', NULL, 'IT Services', NULL, NULL, 'tcs.com', '3678.90', '0.3700', '3665.40', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(3, 'INFY', 'Infosys Limited', 'NSE', NULL, 'IT Services', NULL, NULL, 'infosys.com', '1567.80', '0.5700', '1558.90', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(4, 'HDFCBANK', 'HDFC Bank Limited', 'NSE', NULL, 'Banking', NULL, NULL, 'hdfcbank.com', '1678.50', '0.5500', '1669.30', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(5, 'ICICIBANK', 'ICICI Bank Limited', 'NSE', NULL, 'Banking', NULL, NULL, 'icicibank.com', '1089.40', '0.6200', '1082.70', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(6, 'SBIN', 'State Bank of India', 'NSE', NULL, 'Banking', NULL, NULL, 'sbi.co.in', '756.30', '0.8500', '749.90', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(7, 'BHARTIARTL', 'Bharti Airtel Limited', 'NSE', NULL, 'Telecom', NULL, NULL, 'airtel.in', '1234.50', '1.1500', '1220.45', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(8, 'ITC', 'ITC Limited', 'NSE', NULL, 'FMCG', NULL, NULL, 'itcportal.com', '445.20', '-0.3200', '446.65', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(9, 'LT', 'Larsen & Toubro', 'NSE', NULL, 'Construction', NULL, NULL, 'larsentoubro.com', '3456.80', '0.9500', '3424.30', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(10, 'HINDUNILVR', 'Hindustan Unilever Ltd', 'NSE', NULL, 'FMCG', NULL, NULL, 'hul.co.in', '2567.90', '0.4200', '2557.10', 1, '2026-05-23 05:44:40', '2026-05-23 05:44:40'),
(11, '^NSEI', 'Nifty 50', 'NSE', NULL, 'Index', NULL, NULL, NULL, '24850.00', '0.2016', '24800.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(12, '^NSEBANK', 'Nifty Bank', 'NSE', NULL, 'Index', NULL, NULL, NULL, '53500.00', '0.1873', '53400.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(13, '^BSESN', 'BSE Sensex', 'BSE', NULL, 'Index', NULL, NULL, NULL, '81500.00', '0.1229', '81400.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(14, '^CNXIT', 'Nifty IT', 'NSE', NULL, 'Index', NULL, NULL, NULL, '38000.00', '0.2639', '37900.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(15, '^CNXFIN', 'Nifty Financial Services', 'NSE', NULL, 'Index', NULL, NULL, NULL, '23500.00', '0.4274', '23400.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(16, '^NSEMDCP100', 'Nifty Midcap 100', 'NSE', NULL, 'Index', NULL, NULL, NULL, '56000.00', '0.1789', '55900.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(17, '^NSESMLCP100', 'Nifty Smallcap 100', 'NSE', NULL, 'Index', NULL, NULL, NULL, '18500.00', '0.5435', '18400.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(18, '^CNXAUTO', 'Nifty Auto', 'NSE', NULL, 'Index', NULL, NULL, NULL, '25000.00', '0.4016', '24900.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(19, '^CNXPHARMA', 'Nifty Pharma', 'NSE', NULL, 'Index', NULL, NULL, NULL, '21000.00', '0.4785', '20900.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(20, '^CNXMETAL', 'Nifty Metal', 'NSE', NULL, 'Index', NULL, NULL, NULL, '8500.00', '0.5917', '8450.00', 1, '2026-06-08 17:28:17', '2026-06-08 17:28:17'),
(21, 'CL=F', 'Crude Oil (WTI)', 'NYMEX', 'Energy', 'Commodity', NULL, NULL, NULL, '71.50', '0.4213', '71.20', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(22, 'BZ=F', 'Crude Oil (Brent)', 'ICE', 'Energy', 'Commodity', NULL, NULL, NULL, '75.80', '0.3974', '75.50', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(23, 'NG=F', 'Natural Gas', 'NYMEX', 'Energy', 'Commodity', NULL, NULL, NULL, '3.25', '2.2013', '3.18', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(24, 'GC=F', 'Gold', 'COMEX', 'Precious Metals', 'Commodity', NULL, NULL, NULL, '2385.40', '0.3112', '2378.00', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(25, 'SI=F', 'Silver', 'COMEX', 'Precious Metals', 'Commodity', NULL, NULL, NULL, '28.45', '0.8865', '28.20', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(26, 'PL=F', 'Platinum', 'NYMEX', 'Precious Metals', 'Commodity', NULL, NULL, NULL, '985.00', '0.5102', '980.00', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(27, 'HG=F', 'Copper', 'COMEX', 'Base Metals', 'Commodity', NULL, NULL, NULL, '4.35', '1.1628', '4.30', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(28, 'ZC=F', 'Corn', 'CBOT', 'Agriculture', 'Commodity', NULL, NULL, NULL, '445.50', '0.7919', '442.00', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(29, 'ZW=F', 'Wheat', 'CBOT', 'Agriculture', 'Commodity', NULL, NULL, NULL, '568.25', '0.5752', '565.00', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(30, 'ZS=F', 'Soybeans', 'CBOT', 'Agriculture', 'Commodity', NULL, NULL, NULL, '1185.00', '0.5942', '1178.00', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(31, 'KC=F', 'Coffee', 'ICE', 'Agriculture', 'Commodity', NULL, NULL, NULL, '185.30', '0.9809', '183.50', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(32, 'CT=F', 'Cotton', 'ICE', 'Agriculture', 'Commodity', NULL, NULL, NULL, '68.45', '0.9587', '67.80', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(33, 'SB=F', 'Sugar', 'ICE', 'Agriculture', 'Commodity', NULL, NULL, NULL, '19.25', '0.7853', '19.10', 1, '2026-06-08 17:56:26', '2026-06-08 17:56:26'),
(34, 'BTC-USD', 'Bitcoin', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '43250.00', '0.0000', '42800.00', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(35, 'ETH-USD', 'Ethereum', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '2285.00', '0.0000', '2250.00', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(36, 'BNB-USD', 'Binance Coin', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '312.50', '0.0000', '308.00', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(37, 'XRP-USD', 'Ripple', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '0.52', '0.0000', '0.51', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(38, 'ADA-USD', 'Cardano', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '0.45', '0.0000', '0.44', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(39, 'SOL-USD', 'Solana', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '98.50', '0.0000', '96.00', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(40, 'DOT-USD', 'Polkadot', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '7.25', '0.0000', '7.10', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(41, 'DOGE-USD', 'Dogecoin', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '0.08', '0.0000', '0.08', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(42, 'AVAX-USD', 'Avalanche', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '35.80', '0.0000', '35.00', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(43, 'MATIC-USD', 'Polygon', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '0.85', '0.0000', '0.83', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(44, 'LINK-USD', 'Chainlink', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '14.50', '0.0000', '14.20', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(45, 'UNI-USD', 'Uniswap', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '6.20', '0.0000', '6.05', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(46, 'LTC-USD', 'Litecoin', 'CRYPTO', 'Crypto', 'Cryptocurrency', NULL, NULL, NULL, '72.50', '0.0000', '71.00', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14'),
(47, 'ETH-BTC', 'Ethereum/Bitcoin', 'CRYPTO', 'Crypto Pair', 'Cryptocurrency', NULL, NULL, NULL, '0.05', '0.0000', '0.05', 1, '2026-06-08 20:49:14', '2026-06-08 20:49:14');

-- --------------------------------------------------------

--
-- Table structure for table `stock_price_cache`
--

CREATE TABLE `stock_price_cache` (
  `stock_id` int(11) NOT NULL,
  `ltp` decimal(10,2) DEFAULT 0.00,
  `open` decimal(10,2) DEFAULT NULL,
  `high` decimal(10,2) DEFAULT NULL,
  `low` decimal(10,2) DEFAULT NULL,
  `close` decimal(10,2) DEFAULT NULL,
  `volume` bigint(20) DEFAULT NULL,
  `change_percent` decimal(5,2) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `trade_history`
--

CREATE TABLE `trade_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `symbol` varchar(20) DEFAULT NULL,
  `order_type` enum('BUY','SELL') NOT NULL,
  `trade_type` enum('BUY','SELL') NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `realized_pnl` decimal(15,2) DEFAULT 0.00,
  `trade_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trade_history`
--

INSERT INTO `trade_history` (`id`, `user_id`, `stock_id`, `order_id`, `symbol`, `order_type`, `trade_type`, `quantity`, `price`, `total_amount`, `realized_pnl`, `trade_date`, `executed_at`) VALUES
(1, 1, 3, 1, 'INFY', 'BUY', 'BUY', 5, '1174.50', '5872.50', '0.00', '2026-05-23 11:36:10', '2026-05-23 11:36:10'),
(2, 1, 7, 2, 'BHARTIARTL', 'BUY', 'BUY', 1, '1798.20', '1798.20', '0.00', '2026-06-06 08:35:54', '2026-06-06 08:35:54'),
(3, 1, 8, 3, 'ITC', 'BUY', 'BUY', 4, '280.70', '1122.80', '0.00', '2026-06-06 13:24:01', '2026-06-06 13:24:01'),
(4, 1, 7, 4, 'BHARTIARTL', 'BUY', 'BUY', 1, '1798.20', '1798.20', '0.00', '2026-06-06 15:41:21', '2026-06-06 15:41:21'),
(5, 1, 7, 5, 'BHARTIARTL', 'BUY', 'BUY', 1, '1798.20', '1798.20', '0.00', '2026-06-06 15:43:31', '2026-06-06 15:43:31'),
(6, 1, 9, 6, 'LT', 'BUY', 'BUY', 10, '3953.20', '39532.00', '0.00', '2026-06-06 15:59:45', '2026-06-06 15:59:45'),
(7, 1, 7, 7, 'BHARTIARTL', 'BUY', 'BUY', 1, '1811.10', '1811.10', '0.00', '2026-06-08 09:35:01', '2026-06-08 09:35:01'),
(8, 1, 7, 8, 'BHARTIARTL', 'BUY', 'BUY', 1, '1823.30', '1823.30', '0.00', '2026-06-08 17:22:53', '2026-06-08 17:22:53'),
(9, 1, 11, 9, '^NSEI', 'BUY', 'BUY', 1, '24850.00', '24850.00', '0.00', '2026-06-08 17:34:15', '2026-06-08 17:34:15'),
(10, 1, 22, 10, 'BZ=F', 'BUY', 'BUY', 1, '75.80', '75.80', '0.00', '2026-06-08 21:00:20', '2026-06-08 21:00:20'),
(11, 1, 38, 11, 'ADA-USD', 'BUY', 'BUY', 10, '0.45', '4.50', '0.00', '2026-06-08 21:03:16', '2026-06-08 21:03:16'),
(12, 1, 38, 12, 'ADA-USD', 'SELL', 'SELL', 1, '0.45', '0.45', '0.00', '2026-06-08 21:46:21', '2026-06-08 21:46:21'),
(13, 1, 41, 13, 'DOGE-USD', 'BUY', 'BUY', 20000, '0.08', '1600.00', '0.00', '2026-06-08 22:10:42', '2026-06-08 22:10:42');

-- --------------------------------------------------------

--
-- Table structure for table `user_holdings`
--

CREATE TABLE `user_holdings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `average_price` decimal(10,2) NOT NULL,
  `current_price` decimal(10,2) DEFAULT 0.00,
  `invested_amount` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) DEFAULT 0.00,
  `pnl` decimal(15,2) DEFAULT 0.00,
  `pnl_percent` decimal(5,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_holdings`
--

INSERT INTO `user_holdings` (`id`, `user_id`, `stock_id`, `quantity`, `average_price`, `current_price`, `invested_amount`, `current_value`, `pnl`, `pnl_percent`, `updated_at`) VALUES
(1, 1, 3, 5, '1174.50', '1567.80', '5872.50', '7839.00', '1966.50', '33.49', '2026-06-08 17:13:50'),
(2, 1, 7, 5, '1805.80', '1823.30', '9029.00', '9116.50', '87.50', '350.00', '2026-06-08 17:22:53'),
(3, 1, 8, 4, '280.70', '445.20', '1122.80', '1780.80', '658.00', '58.60', '2026-06-08 17:13:50'),
(4, 1, 9, 10, '3953.20', '3456.80', '39532.00', '34568.00', '-4964.00', '-12.56', '2026-06-08 17:13:50'),
(5, 1, 11, 1, '24850.00', '24850.00', '24850.00', '24850.00', '0.00', '0.00', '2026-06-08 17:34:15'),
(6, 1, 22, 1, '75.80', '75.80', '75.80', '75.80', '0.00', '0.00', '2026-06-08 21:00:20'),
(7, 1, 38, 9, '0.45', '0.45', '4.05', '4.05', '0.00', '0.00', '2026-06-08 21:46:21'),
(8, 1, 41, 20000, '0.08', '0.08', '1600.00', '1600.00', '0.00', '0.00', '2026-06-08 22:10:42');

-- --------------------------------------------------------

--
-- Table structure for table `user_orders`
--

CREATE TABLE `user_orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `order_type` enum('BUY','SELL') NOT NULL,
  `order_mode` enum('MARKET','LIMIT') DEFAULT 'MARKET',
  `limit_price` decimal(10,2) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `traded_price` decimal(10,2) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `status` enum('PENDING','EXECUTED','CANCELLED','REJECTED') DEFAULT 'EXECUTED',
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `admin_remark` text DEFAULT NULL,
  `executed_by` int(11) DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  `modified_by_admin` int(11) DEFAULT NULL,
  `admin_modified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_orders`
--

INSERT INTO `user_orders` (`id`, `user_id`, `stock_id`, `order_type`, `order_mode`, `limit_price`, `quantity`, `traded_price`, `price`, `status`, `total_amount`, `admin_remark`, `executed_by`, `executed_at`, `modified_by_admin`, `admin_modified_at`, `created_at`) VALUES
(1, 1, 3, 'BUY', 'MARKET', NULL, 5, NULL, '1174.50', 'EXECUTED', '5872.50', NULL, NULL, '2026-05-23 11:36:10', NULL, NULL, '2026-05-23 11:36:10'),
(2, 1, 7, 'BUY', 'MARKET', NULL, 1, NULL, '1798.20', 'EXECUTED', '1798.20', NULL, NULL, '2026-06-06 08:35:54', NULL, NULL, '2026-06-06 08:35:54'),
(3, 1, 8, 'BUY', 'MARKET', NULL, 4, NULL, '280.70', 'EXECUTED', '1122.80', NULL, NULL, '2026-06-06 13:24:01', NULL, NULL, '2026-06-06 13:24:01'),
(4, 1, 7, 'BUY', 'MARKET', NULL, 1, NULL, '1798.20', 'EXECUTED', '1798.20', NULL, NULL, '2026-06-06 15:41:21', NULL, NULL, '2026-06-06 15:41:21'),
(5, 1, 7, 'BUY', 'MARKET', NULL, 1, NULL, '1798.20', 'EXECUTED', '1798.20', NULL, NULL, '2026-06-06 15:43:31', NULL, NULL, '2026-06-06 15:43:31'),
(6, 1, 9, 'BUY', 'MARKET', NULL, 10, NULL, '3953.20', 'EXECUTED', '39532.00', NULL, NULL, '2026-06-06 15:59:45', NULL, NULL, '2026-06-06 15:59:45'),
(7, 1, 7, 'BUY', 'MARKET', NULL, 1, NULL, '1811.10', 'EXECUTED', '1811.10', NULL, NULL, '2026-06-08 09:35:01', NULL, NULL, '2026-06-08 09:35:01'),
(8, 1, 7, 'BUY', 'LIMIT', NULL, 1, NULL, '1823.30', 'EXECUTED', '1823.30', NULL, NULL, '2026-06-08 17:22:53', NULL, NULL, '2026-06-08 17:22:53'),
(9, 1, 11, 'BUY', 'MARKET', NULL, 1, NULL, '24850.00', 'EXECUTED', '24850.00', NULL, NULL, '2026-06-08 17:34:15', NULL, NULL, '2026-06-08 17:34:15'),
(10, 1, 22, 'BUY', 'MARKET', NULL, 1, NULL, '75.80', 'EXECUTED', '75.80', NULL, NULL, '2026-06-08 21:00:20', NULL, NULL, '2026-06-08 21:00:20'),
(11, 1, 38, 'BUY', 'MARKET', NULL, 10, NULL, '0.45', 'EXECUTED', '4.50', NULL, NULL, '2026-06-08 21:03:16', NULL, NULL, '2026-06-08 21:03:16'),
(12, 1, 38, 'SELL', 'MARKET', NULL, 1, NULL, '0.45', 'EXECUTED', '0.45', NULL, NULL, '2026-06-08 21:46:21', NULL, NULL, '2026-06-08 21:46:21'),
(13, 1, 41, 'BUY', 'MARKET', NULL, 20000, NULL, '0.08', 'EXECUTED', '1600.00', NULL, NULL, '2026-06-09 10:40:42', NULL, NULL, '2026-06-08 22:10:42');

-- --------------------------------------------------------

--
-- Table structure for table `user_payment_details`
--

CREATE TABLE `user_payment_details` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(20) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `account_type` enum('savings','current') DEFAULT 'savings',
  `upi_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_payment_details`
--

INSERT INTO `user_payment_details` (`id`, `user_id`, `account_holder_name`, `account_number`, `ifsc_code`, `bank_name`, `branch_name`, `account_type`, `upi_id`, `created_at`, `updated_at`) VALUES
(1, 1, '', '8585858585', 'HDFC1221G', 'hdfc', NULL, NULL, '343432@ybl', '2026-06-06 10:26:37', '2026-06-06 10:26:45');

-- --------------------------------------------------------

--
-- Table structure for table `user_watchlist`
--

CREATE TABLE `user_watchlist` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_watchlist`
--

INSERT INTO `user_watchlist` (`id`, `user_id`, `stock_id`, `added_at`, `created_at`) VALUES
(3, 1, 3, '2026-06-08 17:06:27', '2026-06-08 17:06:27');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawals`
--

CREATE TABLE `withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `bank_details` text DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `upi_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `admin_remark` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `account_registrations`
--
ALTER TABLE `account_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_id` (`admin_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `admin_bank_accounts`
--
ALTER TABLE `admin_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_default` (`is_default`);

--
-- Indexes for table `api_settings`
--
ALTER TABLE `api_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `provider` (`provider`);

--
-- Indexes for table `compliance_blocked`
--
ALTER TABLE `compliance_blocked`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip` (`ip_address`);

--
-- Indexes for table `data_provider_preferences`
--
ALTER TABLE `data_provider_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_type` (`asset_type`);

--
-- Indexes for table `fno_contracts`
--
ALTER TABLE `fno_contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_contract` (`symbol`,`contract_type`,`strike_price`,`expiry_date`),
  ADD KEY `idx_symbol` (`symbol`),
  ADD KEY `idx_type` (`contract_type`),
  ADD KEY `idx_expiry` (`expiry_date`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `fno_orders`
--
ALTER TABLE `fno_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_contract` (`contract_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_order_date` (`order_date`);

--
-- Indexes for table `fno_positions`
--
ALTER TABLE `fno_positions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_contract` (`contract_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `fno_transactions`
--
ALTER TABLE `fno_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_type` (`transaction_type`);

--
-- Indexes for table `fno_watchlist`
--
ALTER TABLE `fno_watchlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_fno_watchlist` (`user_id`,`contract_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_contract` (`contract_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `stocks`
--
ALTER TABLE `stocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_symbol` (`symbol`),
  ADD KEY `idx_symbol` (`symbol`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `stock_price_cache`
--
ALTER TABLE `stock_price_cache`
  ADD PRIMARY KEY (`stock_id`),
  ADD KEY `idx_updated` (`last_updated`);

--
-- Indexes for table `trade_history`
--
ALTER TABLE `trade_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_stock` (`stock_id`),
  ADD KEY `idx_date` (`trade_date`);

--
-- Indexes for table `user_holdings`
--
ALTER TABLE `user_holdings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_holding` (`user_id`,`stock_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `user_orders`
--
ALTER TABLE `user_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_stock` (`stock_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `user_payment_details`
--
ALTER TABLE `user_payment_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `user_watchlist`
--
ALTER TABLE `user_watchlist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_watchlist` (`user_id`,`stock_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `withdrawals`
--
ALTER TABLE `withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `account_registrations`
--
ALTER TABLE `account_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admin_activity_log`
--
ALTER TABLE `admin_activity_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `admin_bank_accounts`
--
ALTER TABLE `admin_bank_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `api_settings`
--
ALTER TABLE `api_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `compliance_blocked`
--
ALTER TABLE `compliance_blocked`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_provider_preferences`
--
ALTER TABLE `data_provider_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `fno_contracts`
--
ALTER TABLE `fno_contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `fno_orders`
--
ALTER TABLE `fno_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fno_positions`
--
ALTER TABLE `fno_positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fno_transactions`
--
ALTER TABLE `fno_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fno_watchlist`
--
ALTER TABLE `fno_watchlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stocks`
--
ALTER TABLE `stocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `trade_history`
--
ALTER TABLE `trade_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_holdings`
--
ALTER TABLE `user_holdings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_orders`
--
ALTER TABLE `user_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `user_payment_details`
--
ALTER TABLE `user_payment_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_watchlist`
--
ALTER TABLE `user_watchlist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `withdrawals`
--
ALTER TABLE `withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
