-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2026 at 02:09 PM
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
-- Table structure for table `user_gateway_info`
--

CREATE TABLE `user_gateway_info` (
  `gateway_id` varchar(25) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `account_id` varchar(25) NOT NULL,
  `creation_date` date NOT NULL DEFAULT curdate(),
  `gateway_method` text NOT NULL,
  `gateway_account_number` varchar(40) NOT NULL,
  `gateway_brand` varchar(25) NOT NULL,
  `account_status` enum('Active','Terminated') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `user_gateway_info`
--
ALTER TABLE `user_gateway_info`
  ADD PRIMARY KEY (`gateway_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
