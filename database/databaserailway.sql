-- TradeZenfy Database Setup for Railway
-- Run each section separately in Railway MySQL console

-- ===== TABLE 1: account_registrations =====
CREATE TABLE IF NOT EXISTS `account_registrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 2: admins =====
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `role` enum('super_admin','admin') DEFAULT 'admin',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 3: admin_activity_log =====
CREATE TABLE IF NOT EXISTS `admin_activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 4: admin_bank_accounts =====
CREATE TABLE IF NOT EXISTS `admin_bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 5: api_settings =====
CREATE TABLE IF NOT EXISTS `api_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) NOT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `api_secret` varchar(255) DEFAULT NULL,
  `client_id` varchar(100) DEFAULT NULL,
  `client_secret` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `totp_secret` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 6: compliance_blocked =====
CREATE TABLE IF NOT EXISTS `compliance_blocked` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 7: data_provider_preferences =====
CREATE TABLE IF NOT EXISTS `data_provider_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_type` varchar(50) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'yahoo_finance',
  `is_enabled` tinyint(1) DEFAULT 1,
  `priority` int(11) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_type` (`asset_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 8: fno_contracts =====
CREATE TABLE IF NOT EXISTS `fno_contracts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 9: fno_orders =====
CREATE TABLE IF NOT EXISTS `fno_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 10: fno_positions =====
CREATE TABLE IF NOT EXISTS `fno_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 11: fno_transactions =====
CREATE TABLE IF NOT EXISTS `fno_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `transaction_type` enum('MARGIN_DEBIT','MARGIN_CREDIT','PREMIUM_PAID','PREMIUM_RECEIVED','Pnl_SETTLEMENT','EXPIRY_SETTLEMENT') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 12: fno_watchlist =====
CREATE TABLE IF NOT EXISTS `fno_watchlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_fno_watchlist` (`user_id`,`contract_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 13: payments =====
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 14: stocks =====
CREATE TABLE IF NOT EXISTS `stocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_symbol` (`symbol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 15: stock_price_cache =====
CREATE TABLE IF NOT EXISTS `stock_price_cache` (
  `stock_id` int(11) NOT NULL,
  `ltp` decimal(10,2) DEFAULT 0.00,
  `open` decimal(10,2) DEFAULT NULL,
  `high` decimal(10,2) DEFAULT NULL,
  `low` decimal(10,2) DEFAULT NULL,
  `close` decimal(10,2) DEFAULT NULL,
  `volume` bigint(20) DEFAULT NULL,
  `change_percent` decimal(5,2) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 16: trade_history =====
CREATE TABLE IF NOT EXISTS `trade_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 17: user_holdings =====
CREATE TABLE IF NOT EXISTS `user_holdings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `average_price` decimal(10,2) NOT NULL,
  `current_price` decimal(10,2) DEFAULT 0.00,
  `invested_amount` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) DEFAULT 0.00,
  `pnl` decimal(15,2) DEFAULT 0.00,
  `pnl_percent` decimal(5,2) DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_holding` (`user_id`,`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 18: user_orders =====
CREATE TABLE IF NOT EXISTS `user_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 19: user_payment_details =====
CREATE TABLE IF NOT EXISTS `user_payment_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `ifsc_code` varchar(20) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `account_type` enum('savings','current') DEFAULT 'savings',
  `upi_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 20: user_watchlist =====
CREATE TABLE IF NOT EXISTS `user_watchlist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `stock_id` int(11) NOT NULL,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_watchlist` (`user_id`,`stock_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== TABLE 21: withdrawals =====
CREATE TABLE IF NOT EXISTS `withdrawals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== DEFAULT DATA (single-line for Railway console) =====

-- Admin (password: password)
INSERT INTO `admins` (`username`, `password`, `email`, `name`, `role`, `is_active`) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@alphanumerical.com', 'System Admin', 'super_admin', 1);

-- Test user (password: password)
INSERT INTO `account_registrations` (`first_name`, `last_name`, `email`, `username`, `password`, `phone`, `country`, `status`, `current_balance`) VALUES ('Test', 'User', 'test@alphanumeric.com', 'aaaa', '$2y$12$19rSh9RCdM0jdFbrUgAweuQyP6yAHC2v9rtUePQsvEWOqpRPk1GpO', '9999999999', 'India', 'approved', 30034.85);

-- Bank account
INSERT INTO `admin_bank_accounts` (`account_name`, `bank_name`, `account_number`, `ifsc_code`, `upi_id`, `is_active`) VALUES ('hululu Info', 'IDFC', '909090909090', 'IDFC000123', 'Company@ybl', 1);

-- API settings
INSERT INTO `api_settings` (`provider`, `is_active`) VALUES ('yahoo_finance', 1);
INSERT INTO `api_settings` (`provider`, `api_key`, `client_id`, `password`, `totp_secret`, `is_active`) VALUES ('angel_one', 'BFlnbdJR', 'HSHH1260', '4267', 'KE6XVJ7AFXZJMERJ6UR4IRQJ7M', 1);

-- Data provider preferences (one per line)
INSERT INTO `data_provider_preferences` (`asset_type`, `provider`) VALUES ('stocks', 'angel_one');
INSERT INTO `data_provider_preferences` (`asset_type`, `provider`) VALUES ('commodities', 'angel_one');
INSERT INTO `data_provider_preferences` (`asset_type`, `provider`) VALUES ('indices', 'angel_one');
INSERT INTO `data_provider_preferences` (`asset_type`, `provider`) VALUES ('crypto', 'yahoo_finance');
INSERT INTO `data_provider_preferences` (`asset_type`, `provider`) VALUES ('forex', 'yahoo_finance');

-- Stocks (one per line for Railway console)
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('RELIANCE', 'Reliance Industries Ltd', 'NSE', 'Energy', 'ril.com', 2456.75, 0.47, 2445.30);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('TCS', 'Tata Consultancy Services', 'NSE', 'IT Services', 'tcs.com', 3678.90, 0.37, 3665.40);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('INFY', 'Infosys Limited', 'NSE', 'IT Services', 'infosys.com', 1567.80, 0.57, 1558.90);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('HDFCBANK', 'HDFC Bank Limited', 'NSE', 'Banking', 'hdfcbank.com', 1678.50, 0.55, 1669.30);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('ICICIBANK', 'ICICI Bank Limited', 'NSE', 'Banking', 'icicibank.com', 1089.40, 0.62, 1082.70);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('SBIN', 'State Bank of India', 'NSE', 'Banking', 'sbi.co.in', 756.30, 0.85, 749.90);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('BHARTIARTL', 'Bharti Airtel Limited', 'NSE', 'Telecom', 'airtel.in', 1234.50, 1.15, 1220.45);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('ITC', 'ITC Limited', 'NSE', 'FMCG', 'itcportal.com', 445.20, -0.32, 446.65);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('LT', 'Larsen & Toubro', 'NSE', 'Construction', 'larsentoubro.com', 3456.80, 0.95, 3424.30);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('HINDUNILVR', 'Hindustan Unilever Ltd', 'NSE', 'FMCG', 'hul.co.in', 2567.90, 0.42, 2557.10);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('^NSEI', 'Nifty 50', 'NSE', 'Index', NULL, 24850.00, 0.20, 24800.00);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('^NSEBANK', 'Nifty Bank', 'NSE', 'Index', NULL, 53500.00, 0.19, 53400.00);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('^BSESN', 'BSE Sensex', 'BSE', 'Index', NULL, 81500.00, 0.12, 81400.00);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('^CNXIT', 'Nifty IT', 'NSE', 'Index', NULL, 38000.00, 0.26, 37900.00);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('^CNXFIN', 'Nifty Financial Services', 'NSE', 'Index', NULL, 23500.00, 0.43, 23400.00);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('^NSEMDCP100', 'Nifty Midcap 100', 'NSE', 'Index', NULL, 56000.00, 0.18, 55900.00);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('CL=F', 'Crude Oil (WTI)', 'NYMEX', 'Commodity', NULL, 71.50, 0.42, 71.20);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('GC=F', 'Gold', 'COMEX', 'Commodity', NULL, 2385.40, 0.31, 2378.00);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('BTC-USD', 'Bitcoin', 'CRYPTO', 'Cryptocurrency', NULL, 43250.00, 0.00, 42800.00);
INSERT INTO `stocks` (`symbol`, `name`, `exchange`, `sector`, `website`, `ltp`, `change_percent`, `previous_close`) VALUES ('ETH-USD', 'Ethereum', 'CRYPTO', 'Cryptocurrency', NULL, 2285.00, 0.00, 2250.00);

-- ===== VERIFY =====
SELECT 'All tables created successfully!' AS status;
SHOW TABLES;
