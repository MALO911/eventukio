-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2026 at 02:48 PM
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
-- Table structure for table `user_event_jobs`
--

CREATE TABLE `user_event_jobs` (
  `profile_id` varchar(20) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `task_count` int(11) NOT NULL DEFAULT 0,
  `profession_category` text NOT NULL,
  `profession_title` text NOT NULL,
  `job_earning` decimal(10,2) DEFAULT NULL,
  `job_average_rating` decimal(3,2) DEFAULT 0.00,
  `job_status` enum('Invalid','Valid') DEFAULT 'Valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_event_jobs`
--

INSERT INTO `user_event_jobs` (`profile_id`, `user_id`, `task_count`, `profession_category`, `profession_title`, `job_earning`, `job_average_rating`, `job_status`) VALUES
('', 'EEMS-USER-TEST001', 0, 'Food', 'Cook', NULL, 0.00, 'Valid');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `user_event_jobs`
--
ALTER TABLE `user_event_jobs`
  ADD PRIMARY KEY (`profile_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
