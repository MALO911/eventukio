-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2026 at 02:49 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eventukio`
--

-- --------------------------------------------------------

--
-- Table structure for table `user_event_asset`
--

CREATE TABLE `user_event_asset` (
  `asset_id` varchar(20) NOT NULL,
  `owner_id` varchar(25) NOT NULL,
  `asset_category` text NOT NULL,
  `asset_name` text NOT NULL,
  `asset_quality` varchar(20) NOT NULL,
  `asset_quantity` int(11) NOT NULL,
  `asset_type` enum('Rental','Consumption') DEFAULT 'Rental',
  `asset_price` decimal(10,2) NOT NULL,
  `asset_earned_amount` decimal(10,2) DEFAULT 0.00,
  `asset_location_specifics` text DEFAULT NULL,
  `asset_street` text DEFAULT NULL,
  `asset_district` text DEFAULT NULL,
  `asset_region` text DEFAULT NULL,
  `asset_status` enum('Available','Booked','Unavailable') DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_event_asset`
--

INSERT INTO `user_event_asset` (`asset_id`, `owner_id`, `asset_category`, `asset_name`, `asset_quality`, `asset_quantity`, `asset_type`, `asset_price`, `asset_earned_amount`, `asset_location_specifics`, `asset_street`, `asset_district`, `asset_region`, `asset_status`) VALUES
('', 'EEMS-USER-TEST001', 'Furniture', 'Chair', 'Metal chair', 200, 'Rental', 2500.00, 0.00, 'Jirani na NBC Bank', 'Bukombe', 'Kibondo', 'Kigoma', 'Available');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `user_event_asset`
--
ALTER TABLE `user_event_asset`
  ADD PRIMARY KEY (`asset_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
