-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 22, 2026 at 03:49 PM
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
-- Table structure for table `event_ad_images`
--

CREATE TABLE `event_ad_images` (
  `images_ad_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `images_upload_date` date NOT NULL DEFAULT curdate(),
  `images_upload_time` time NOT NULL DEFAULT curtime(),
  `image_a` varchar(200) NOT NULL,
  `image_b` varchar(200) NOT NULL,
  `image_c` varchar(200) NOT NULL,
  `image_d` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_ad_video`
--

CREATE TABLE `event_ad_video` (
  `video_ad_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `video_upload_date` date NOT NULL DEFAULT curdate(),
  `video_upload_time` time NOT NULL DEFAULT curtime(),
  `video_uploaded` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_announcements`
--

CREATE TABLE `event_announcements` (
  `announcement_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `invitee_id` varchar(25) DEFAULT NULL,
  `announcement_content` text NOT NULL,
  `announcing_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `announcement_priority` enum('High','Normal','Low') DEFAULT 'Normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_asset_rentals`
--

CREATE TABLE `event_asset_rentals` (
  `rental_id` varchar(20) NOT NULL,
  `event_id` int(11) NOT NULL,
  `asset_id` varchar(20) NOT NULL,
  `renting_price` decimal(10,2) NOT NULL,
  `total_renting_price` decimal(10,2) NOT NULL,
  `rented_quantity` int(11) NOT NULL,
  `renting_date` date NOT NULL,
  `renting_time` time NOT NULL,
  `renting_status` enum('Requested','Pleaded','Booked','Received','Returned','Postponed') DEFAULT 'Postponed',
  `lending_status` enum('Approved','Pending','Denied') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_asset_rentals`
--

INSERT INTO `event_asset_rentals` (`rental_id`, `event_id`, `asset_id`, `renting_price`, `total_renting_price`, `rented_quantity`, `renting_date`, `renting_time`, `renting_status`, `lending_status`) VALUES
('', 6, 'ASSET-64149CCB29CB', 250000.00, 250000.00, 1, '0000-00-00', '00:00:00', 'Requested', 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `event_asset_returns`
--

CREATE TABLE `event_asset_returns` (
  `return_id` varchar(20) NOT NULL,
  `rental_id` varchar(20) NOT NULL,
  `event_id` int(11) NOT NULL,
  `asset_id` varchar(20) NOT NULL,
  `returned_quantity` int(11) NOT NULL,
  `returned_date` date NOT NULL,
  `returned_time` time NOT NULL,
  `return_status` enum('Complete','None','Incomplete') DEFAULT 'Complete',
  `payment_status` enum('Paid','Unpaid') DEFAULT 'Unpaid',
  `reception_status` enum('Waiting','Received') DEFAULT 'Waiting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_attendees`
--

CREATE TABLE `event_attendees` (
  `attendee_id` varchar(25) NOT NULL,
  `invitee_id` varchar(25) NOT NULL,
  `event_id` int(11) NOT NULL,
  `participant_id` varchar(25) NOT NULL,
  `participation_badge` text DEFAULT NULL,
  `participation_status` enum('Active','Banned') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_basic_info`
--

CREATE TABLE `event_basic_info` (
  `event_id` int(11) NOT NULL,
  `host_id` varchar(25) NOT NULL,
  `creation_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `event_title` varchar(100) NOT NULL,
  `event_type` enum('Private','Public') DEFAULT 'Public',
  `event_category` text NOT NULL,
  `event_extra_detail` text DEFAULT NULL,
  `event_tickets` int(11) NOT NULL DEFAULT 0,
  `event_tickets_sold` int(11) DEFAULT 0,
  `groupchat_permission` enum('Unlocked','Locked') DEFAULT 'Unlocked',
  `privatechat_permission` enum('Unlocked','Locked') DEFAULT 'Unlocked',
  `groupchat_id` int(11) DEFAULT NULL,
  `venue_id` varchar(20) DEFAULT NULL,
  `venue_details` text DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time NOT NULL,
  `termination_date` date NOT NULL,
  `termination_time` time NOT NULL,
  `pocket_id` varchar(30) DEFAULT NULL,
  `event_ad_media` enum('None','Image','Video') DEFAULT 'Image',
  `participation_fee` enum('Present','Absent') DEFAULT 'Present',
  `booking_fundraise_id` varchar(30) DEFAULT NULL,
  `event_activeness` enum('Created','Announced','In Session','Closed','Terminated') DEFAULT 'Created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_basic_info`
--

INSERT INTO `event_basic_info` (`event_id`, `host_id`, `creation_datetime`, `event_title`, `event_type`, `event_category`, `event_extra_detail`, `event_tickets`, `event_tickets_sold`, `groupchat_permission`, `privatechat_permission`, `groupchat_id`, `venue_id`, `venue_details`, `event_date`, `event_time`, `termination_date`, `termination_time`, `pocket_id`, `event_ad_media`, `participation_fee`, `booking_fundraise_id`, `event_activeness`) VALUES
(1, 'EEMS-USER-TEST001', '2026-06-17 19:03:52', 'Mahafali ya Vijana wangu', 'Public', 'Graduation ceremony', 'Mahafali haya yatafanyikia BestWestern Hotels &amp; Resorts, Jangwani, Sea Breeze Resort', 100, 0, 'Unlocked', 'Unlocked', NULL, NULL, NULL, '2026-06-22', '07:01:00', '2026-06-24', '19:00:00', NULL, 'Image', 'Present', NULL, 'In Session'),
(2, 'EEMS-USER-TEST001', '2026-06-17 19:23:08', 'My brother&#039;s Birthday', 'Public', 'Birthday Party', 'Everyone is eventited. You&#039;re all welcome', 5000, 0, 'Unlocked', 'Unlocked', NULL, NULL, NULL, '2026-06-26', '21:25:00', '2026-06-27', '01:25:00', NULL, 'Image', 'Present', NULL, 'Created'),
(6, 'EEMS-USER-TEST001', '2026-06-22 16:32:12', 'My brothers Birthday', 'Public', 'Birthday', 'Mkuje wananguuu', 100, 0, 'Unlocked', 'Unlocked', NULL, NULL, NULL, '2026-07-12', '22:35:00', '2026-07-11', '22:35:00', NULL, 'Image', 'Absent', NULL, 'Created');

-- --------------------------------------------------------

--
-- Table structure for table `event_chatroom`
--

CREATE TABLE `event_chatroom` (
  `chat_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `chat_type` enum('Group','Private') DEFAULT 'Private'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_funding_records`
--

CREATE TABLE `event_funding_records` (
  `funding_id` int(11) NOT NULL,
  `fundraise_id` varchar(30) NOT NULL,
  `event_id` int(11) NOT NULL,
  `fundraise_tag_id` int(11) DEFAULT NULL,
  `payer_id` varchar(25) NOT NULL,
  `funded_amount` decimal(10,2) NOT NULL,
  `funding_date` date NOT NULL DEFAULT curdate(),
  `funding_time` time NOT NULL DEFAULT curtime(),
  `fund_validity` enum('Valid','Invalid') DEFAULT 'Valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_fundraise_info`
--

CREATE TABLE `event_fundraise_info` (
  `fundraise_id` varchar(30) NOT NULL,
  `event_id` int(11) NOT NULL,
  `creation_date` date NOT NULL DEFAULT curdate(),
  `creation_time` time NOT NULL DEFAULT curtime(),
  `fundraise_title` text NOT NULL,
  `collected_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `required_amount` decimal(10,2) DEFAULT NULL,
  `spent_amount` decimal(10,2) DEFAULT NULL,
  `fundraise_type` enum('Contribution','Donation') DEFAULT 'Donation',
  `fundraise_category` enum('Limited','Unlimited') DEFAULT 'Unlimited',
  `fundraise_duration` enum('Pre-event','Mid-event','Post-event') DEFAULT 'Mid-event',
  `fundraise_status` enum('Active','Complete','Complied','Terminated') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_fundraise_tags`
--

CREATE TABLE `event_fundraise_tags` (
  `fundraise_tag_id` varchar(20) NOT NULL,
  `event_id` int(11) NOT NULL,
  `fundraise_id` varchar(30) NOT NULL,
  `required_amount` int(11) DEFAULT NULL,
  `tag_name` varchar(20) NOT NULL,
  `tag_details` varchar(20) DEFAULT NULL,
  `participant_count` int(11) NOT NULL DEFAULT 0,
  `tag_validity` enum('Valid','Invalid') DEFAULT 'Valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_groupchat_messages`
--

CREATE TABLE `event_groupchat_messages` (
  `groupchat_message_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `sender_id` varchar(25) NOT NULL,
  `participation_position` enum('Normal Attendee','Event Host','Service Provider') DEFAULT 'Normal Attendee',
  `message_date` date NOT NULL DEFAULT curdate(),
  `message_time` time NOT NULL DEFAULT curtime(),
  `message_content` text NOT NULL,
  `sender_permission` enum('Allowed','Blocked') DEFAULT 'Allowed',
  `message_visibility` enum('Visible','Hidden') DEFAULT 'Visible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_groupchat_participants`
--

CREATE TABLE `event_groupchat_participants` (
  `groupchart_participant_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `reader_id` varchar(25) NOT NULL,
  `last_read_message_id` int(11) DEFAULT NULL,
  `reading_timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_invitees`
--

CREATE TABLE `event_invitees` (
  `invitee_id` varchar(25) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `attendance_status` enum('Confirmed','Pending','Denied') DEFAULT 'Pending',
  `invitation_badge` enum('Server','Normal','Host','Co-host') DEFAULT 'Normal',
  `invitation_position` text NOT NULL,
  `invitation_category` enum('Non-paying','Paying') DEFAULT 'Non-paying',
  `attendance_date` date DEFAULT NULL,
  `attendance_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_privatechat_messages`
--

CREATE TABLE `event_privatechat_messages` (
  `privatechat_message_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `chat_id` int(11) NOT NULL,
  `sender_id` varchar(25) NOT NULL,
  `sender_participation_position` enum('Normal Attendee','Event Host','Service Provider') DEFAULT 'Normal Attendee',
  `receiver_id` varchar(25) NOT NULL,
  `message_content` text NOT NULL,
  `message_date` date NOT NULL DEFAULT curdate(),
  `message_time` time NOT NULL DEFAULT curtime(),
  `sender_permission` enum('Allowed','Suspended','Blocked') DEFAULT 'Allowed',
  `message_visibility` enum('Visible','Hidden for sender','Hidden for both') DEFAULT 'Visible',
  `message_status` enum('Read','Unread') DEFAULT 'Unread'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_schedule_participants`
--

CREATE TABLE `event_schedule_participants` (
  `schedule_participant_id` varchar(100) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `participant_presence` enum('Active','Absent') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_schedule_timetable`
--

CREATE TABLE `event_schedule_timetable` (
  `schedule_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `schedule_title` text NOT NULL,
  `schedule_start_time` time NOT NULL,
  `schedule_end_time` time NOT NULL,
  `schedule_status` enum('On schedule','Off schedule') DEFAULT 'On schedule'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_service_hiring`
--

CREATE TABLE `event_service_hiring` (
  `hire_id` int(11) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `profile_id` varchar(20) NOT NULL,
  `event_id` int(11) NOT NULL,
  `invitee_id` varchar(25) DEFAULT NULL,
  `hire_amount` decimal(10,2) NOT NULL,
  `hire_date` date NOT NULL,
  `hire_time` time NOT NULL,
  `total_rating` int(11) DEFAULT NULL,
  `hire_status` enum('Requested','Hired','Rejected') DEFAULT 'Requested',
  `service_status` enum('Accepted','Pending','Rejected') DEFAULT 'Pending',
  `payment_status` enum('Paid','Unpaid') DEFAULT 'Unpaid',
  `presence_status` enum('Active','Banned') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_service_ratings`
--

CREATE TABLE `event_service_ratings` (
  `review_id` int(11) NOT NULL,
  `reviewer_id` varchar(25) NOT NULL,
  `profile_id` varchar(20) NOT NULL,
  `user_rating` int(11) NOT NULL,
  `user_review` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_shared_media`
--

CREATE TABLE `event_shared_media` (
  `media_id` varchar(20) NOT NULL,
  `event_id` int(11) NOT NULL,
  `media_type` enum('Photo','Video') DEFAULT 'Photo',
  `uploader_id` varchar(25) NOT NULL,
  `upload_datetime` datetime NOT NULL DEFAULT current_timestamp(),
  `media_file` varchar(200) NOT NULL,
  `media_validity` enum('Valid','Invalid') DEFAULT 'Valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fundraise_user_transactions`
--

CREATE TABLE `fundraise_user_transactions` (
  `fundraiseuser_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `fundraise_id` varchar(30) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `account_id` varchar(25) NOT NULL,
  `transaction_amount` decimal(10,2) NOT NULL,
  `transaction_details` text NOT NULL,
  `transaction_permission` enum('Allowed','Waiting','Denied') DEFAULT 'Waiting',
  `acceptance_status` enum('Accepted','Waiting') DEFAULT 'Waiting'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_basic_info`
--

CREATE TABLE `user_basic_info` (
  `user_id` varchar(25) NOT NULL,
  `user_full_name` varchar(120) NOT NULL,
  `user_profile_picture` varchar(70) DEFAULT NULL,
  `user_type` enum('Personal','Business') NOT NULL,
  `user_bio` varchar(200) DEFAULT NULL,
  `user_email` varchar(70) NOT NULL,
  `user_phone_number` varchar(20) NOT NULL,
  `user_gender` enum('None','Male','Female','Profit','Non-Profit') DEFAULT 'None',
  `birth_date` date NOT NULL,
  `national_id` varchar(50) NOT NULL,
  `home_street` varchar(20) DEFAULT NULL,
  `home_district` varchar(30) DEFAULT NULL,
  `home_region` varchar(30) DEFAULT NULL,
  `recovery_phone_number` varchar(20) DEFAULT NULL,
  `user_password` varchar(255) NOT NULL,
  `user_job_count` int(11) NOT NULL DEFAULT 0,
  `user_language` enum('en','sw','suk','chag') DEFAULT 'en',
  `user_theme` enum('Oceanic Blue','Luxe Jewel','Warm Glow','Soft Pastel') DEFAULT 'Oceanic Blue',
  `registration_date_time` datetime NOT NULL DEFAULT current_timestamp(),
  `user_validity` enum('Registered','Verified','Banned','Inactive') DEFAULT 'Inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_basic_info`
--

INSERT INTO `user_basic_info` (`user_id`, `user_full_name`, `user_profile_picture`, `user_type`, `user_bio`, `user_email`, `user_phone_number`, `user_gender`, `birth_date`, `national_id`, `home_street`, `home_district`, `home_region`, `recovery_phone_number`, `user_password`, `user_job_count`, `user_language`, `user_theme`, `registration_date_time`, `user_validity`) VALUES
('EEMS-USER-TEST001', 'Malongo Lujegi', 'uploads/profiles/6a37f70dd4f53_Saul Profile.png', 'Personal', '', 'lujegimalongo@gmail.com', '255756982868', 'Male', '2001-11-09', '20011109373010000226', 'Darajani', '', 'Dar es Salaam', '255627989963', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, 'en', 'Oceanic Blue', '2026-06-17 01:08:23', 'Verified');

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
('', 'EEMS-USER-TEST001', 'Furniture', 'Chair', 'Metal chair', 200, 'Rental', 2500.00, 0.00, 'Jirani na NBC Bank', 'Bukombe', 'Kibondo', 'Kigoma', 'Available'),
('ASSET-64149CCB29CB', 'EEMS-USER-TEST001', 'Venue', 'Hall', '2500 square metres t', 1, 'Rental', 250000.00, 0.00, 'Triple H hotel', 'Kinyerezi', 'Kinondoni', 'Dar es Salaam', 'Available');

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
('', 'EEMS-USER-TEST001', 0, 'Food', 'Cook', NULL, 0.00, 'Valid'),
('JOB-07CBCF2A160C', 'EEMS-USER-TEST001', 0, 'Entertainment', 'Musician', NULL, 0.00, 'Valid');

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
-- Dumping data for table `user_gateway_info`
--

INSERT INTO `user_gateway_info` (`gateway_id`, `user_id`, `account_id`, `creation_date`, `gateway_method`, `gateway_account_number`, `gateway_brand`, `account_status`) VALUES
('', 'EEMS-USER-TEST001', '', '2026-06-22', 'Bank', '0152745641900', 'CRDB', 'Active'),
('GW-9705CA1218B6', 'EEMS-USER-TEST001', 'WALLET-TEST001', '2026-06-22', 'Mobile Network Operator', '0756982868', 'M-Pesa', 'Active'),
('GW-AD25A8EFE442', 'EEMS-USER-TEST001', 'WALLET-TEST001', '2026-06-22', 'Mobile Network Operator', '0627989963', 'Halo Pesa', 'Active'),
('GW-C2C75FE8B957', 'EEMS-USER-TEST001', 'WALLET-TEST001', '2026-06-22', 'Bank', '01J52745641900', 'NMB', 'Active'),
('GW-EA6172636F29', 'EEMS-USER-TEST001', 'WALLET-TEST001', '2026-06-22', 'Mobile Network Operator', '0673124958', 'Mixx by Yas', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `user_wallet_info`
--

CREATE TABLE `user_wallet_info` (
  `account_id` varchar(25) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `account_balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gateways_number` int(11) NOT NULL DEFAULT 0,
  `account_activity` enum('Active','Shut down') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_wallet_info`
--

INSERT INTO `user_wallet_info` (`account_id`, `user_id`, `account_balance`, `gateways_number`, `account_activity`) VALUES
('WALLET-TEST001', 'EEMS-USER-TEST001', 19999999.99, 0, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `user_wallet_transactions`
--

CREATE TABLE `user_wallet_transactions` (
  `transaction_id` int(11) NOT NULL,
  `user_id` varchar(25) NOT NULL,
  `account_id` varchar(25) NOT NULL,
  `transaction_details` text NOT NULL,
  `transaction_amount` decimal(10,2) NOT NULL,
  `transaction_date` date NOT NULL DEFAULT curdate(),
  `transaction_time` time NOT NULL DEFAULT curtime(),
  `transaction_type` enum('Deposit','Incoming','Outgoing','Withdrawal') DEFAULT 'Deposit',
  `transaction_validity` enum('Valid','Invalid') DEFAULT 'Valid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_wallet_transactions`
--

INSERT INTO `user_wallet_transactions` (`transaction_id`, `user_id`, `account_id`, `transaction_details`, `transaction_amount`, `transaction_date`, `transaction_time`, `transaction_type`, `transaction_validity`) VALUES
(1, 'EEMS-USER-TEST001', '', 'Deposit from CRDB through 0152745641900', 99999999.99, '2026-06-22', '15:12:05', 'Deposit', 'Valid'),
(2, 'EEMS-USER-TEST001', '', 'Deposit from CRDB through 0152745641900', 99999999.99, '2026-06-22', '15:12:56', 'Deposit', 'Valid'),
(3, 'EEMS-USER-TEST001', '', 'Withdrawal into CRDB through 0152745641900', 80000000.00, '2026-06-22', '15:14:03', 'Withdrawal', 'Valid');

-- --------------------------------------------------------

--
-- Table structure for table `zip_validation`
--

CREATE TABLE `zip_validation` (
  `zip_id` int(11) NOT NULL,
  `zip_code` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `zip_validation`
--

INSERT INTO `zip_validation` (`zip_id`, `zip_code`) VALUES
(1, 23101),
(2, 23102),
(3, 23103),
(4, 23104),
(5, 23105),
(6, 23106),
(7, 23107),
(8, 11101),
(9, 11102),
(10, 11103),
(11, 11104),
(12, 11105),
(13, 11106),
(14, 11107),
(15, 11108),
(16, 11109),
(17, 12101),
(18, 12102),
(19, 41101),
(20, 41102),
(21, 41103),
(22, 41104),
(23, 41105),
(24, 41106),
(25, 41107),
(26, 41108),
(27, 30101),
(28, 30102),
(29, 30103),
(30, 51101),
(31, 51102),
(32, 51103),
(33, 51104),
(34, 35101),
(35, 35102),
(36, 35103),
(37, 35104),
(38, 35105),
(39, 35106),
(40, 35107),
(41, 35108),
(42, 35109),
(43, 50101),
(44, 50102),
(45, 50103),
(46, 50104),
(47, 50105),
(48, 50106),
(49, 50107),
(50, 47101),
(51, 47102),
(52, 47103),
(53, 47104),
(54, 25101),
(55, 25102),
(56, 25103),
(57, 25104),
(58, 25105),
(59, 25106),
(60, 25107),
(61, 25108),
(62, 25109),
(63, 25110),
(64, 25111),
(65, 25112),
(66, 25113),
(67, 65101),
(68, 65102),
(69, 65103),
(70, 65104),
(71, 65105),
(72, 65106),
(73, 65107),
(74, 65108),
(75, 65109),
(76, 27101),
(77, 27102),
(78, 27103),
(79, 27104),
(80, 31101),
(81, 31102),
(82, 31103),
(83, 31104),
(84, 31105),
(85, 31106),
(86, 31107),
(87, 31108),
(88, 53101),
(89, 53102),
(90, 53103),
(91, 53104),
(92, 53105),
(93, 53106),
(94, 53107),
(95, 67101),
(96, 67102),
(97, 67103),
(98, 67104),
(99, 63101),
(100, 63102),
(101, 63103),
(102, 63104),
(103, 63105),
(104, 63106),
(105, 59101),
(106, 59102),
(107, 59103),
(108, 61101),
(109, 61102),
(110, 61103),
(111, 61104),
(112, 61105),
(113, 61106),
(114, 61107),
(115, 55101),
(116, 55102),
(117, 57101),
(118, 57102),
(119, 57103),
(120, 57104),
(121, 57105),
(122, 57106),
(123, 57107),
(124, 57108),
(125, 57109),
(126, 37101),
(127, 37102),
(128, 37103),
(129, 37104),
(130, 37105),
(131, 37106),
(132, 37107),
(133, 37108),
(134, 39101),
(135, 39102),
(136, 39103),
(137, 39104),
(138, 39105),
(139, 43101),
(140, 43102),
(141, 43103),
(142, 43104),
(143, 43105),
(144, 43106),
(145, 54101),
(146, 54102),
(147, 54103),
(148, 54104),
(149, 45101),
(150, 45102),
(151, 45103),
(152, 45104),
(153, 21101),
(154, 21102),
(155, 21103),
(156, 21104),
(157, 21105),
(158, 23000),
(159, 11000),
(160, 41000),
(161, 30000),
(162, 51000),
(163, 35000),
(164, 50000),
(165, 47000),
(166, 25000),
(167, 65000),
(168, 27000),
(169, 31000),
(170, 53000),
(171, 67000),
(172, 63000),
(173, 59000),
(174, 61000),
(175, 55000),
(176, 57000),
(177, 37000),
(178, 39000),
(179, 43000),
(180, 54000),
(181, 45000),
(182, 21000),
(183, 41101),
(184, 41102),
(185, 41103),
(186, 41104),
(187, 41105),
(188, 41106),
(189, 41107),
(190, 41108),
(191, 41109),
(192, 41110),
(193, 41111),
(194, 41112),
(195, 41113),
(196, 41114),
(197, 41115),
(198, 41116),
(199, 41117),
(200, 41118),
(201, 41119),
(202, 41120),
(203, 41201),
(204, 41202),
(205, 41203),
(206, 41204),
(207, 41205),
(208, 41206),
(209, 41207),
(210, 41208),
(211, 41209),
(212, 41210),
(213, 41211),
(214, 41212),
(215, 41213),
(216, 41214),
(217, 41215),
(218, 41216),
(219, 41217),
(220, 41218),
(221, 41219),
(222, 41220),
(223, 41221),
(224, 41301),
(225, 41302),
(226, 41303),
(227, 41304),
(228, 41305),
(229, 41306),
(230, 41307),
(231, 41308),
(232, 41309),
(233, 41310),
(234, 41311),
(235, 41312),
(236, 41313),
(237, 41314),
(238, 41315),
(239, 41316),
(240, 41317),
(241, 41318),
(242, 41319),
(243, 41320),
(244, 41321),
(245, 41322),
(246, 41401),
(247, 41402),
(248, 41403),
(249, 41404),
(250, 41405),
(251, 41406),
(252, 41407),
(253, 41408),
(254, 41409),
(255, 41410),
(256, 41411),
(257, 41412),
(258, 41413),
(259, 41414),
(260, 41415),
(261, 41416),
(262, 41417),
(263, 41418),
(264, 41419),
(265, 41420),
(266, 41421),
(267, 41422),
(268, 41423),
(269, 41424),
(270, 41425),
(271, 41426),
(272, 41427),
(273, 41428),
(274, 41429),
(275, 41430),
(276, 41431),
(277, 41432),
(278, 41433),
(279, 41434),
(280, 41435),
(281, 41436),
(282, 41437),
(283, 41501),
(284, 41502),
(285, 41503),
(286, 41504),
(287, 41505),
(288, 41506),
(289, 41507),
(290, 41508),
(291, 41509),
(292, 41510),
(293, 41511),
(294, 41512),
(295, 41513),
(296, 41514),
(297, 41515),
(298, 41516),
(299, 41517),
(300, 41518),
(301, 41519),
(302, 41520),
(303, 41521),
(304, 41522),
(305, 45101),
(306, 45102),
(307, 45103),
(308, 45104),
(309, 45105),
(310, 45106),
(311, 45107),
(312, 45108),
(313, 45109),
(314, 45110),
(315, 45111),
(316, 45112),
(317, 45113),
(318, 45114),
(319, 45115),
(320, 45116),
(321, 45117),
(322, 45118),
(323, 45119),
(324, 45120),
(325, 45121),
(326, 45122),
(327, 45123),
(328, 45124),
(329, 45125),
(330, 45126),
(331, 45127),
(332, 45128),
(333, 45129),
(334, 45201),
(335, 45202),
(336, 45203),
(337, 45204),
(338, 45205),
(339, 45206),
(340, 45207),
(341, 45208),
(342, 45209),
(343, 45210),
(344, 45211),
(345, 45212),
(346, 45213),
(347, 45214),
(348, 45215),
(349, 45216),
(350, 45217),
(351, 45218),
(352, 45219),
(353, 45220),
(354, 45221),
(355, 45222),
(356, 45223),
(357, 45224),
(358, 45225),
(359, 45226),
(360, 45227),
(361, 45228),
(362, 45229),
(363, 45230),
(364, 45301),
(365, 45302),
(366, 45303),
(367, 45304),
(368, 45305),
(369, 45306),
(370, 45307),
(371, 45308),
(372, 45309),
(373, 45310),
(374, 45311),
(375, 45312),
(376, 45313),
(377, 45314),
(378, 45315),
(379, 45316),
(380, 45317),
(381, 45318),
(382, 45319),
(383, 45320),
(384, 45401),
(385, 45402),
(386, 45403),
(387, 45404),
(388, 45405),
(389, 45406),
(390, 45407),
(391, 45408),
(392, 45409),
(393, 45410),
(394, 45411),
(395, 45412),
(396, 45413),
(397, 45414),
(398, 45415),
(399, 45416),
(400, 45417),
(401, 45418),
(402, 45419),
(403, 45420),
(404, 45421),
(405, 45422),
(406, 45423),
(407, 45424),
(408, 45425),
(409, 45426),
(410, 45427),
(411, 21101),
(412, 21102),
(413, 21103),
(414, 21104),
(415, 21105),
(416, 21106),
(417, 21107),
(418, 21108),
(419, 21109),
(420, 21110),
(421, 21111),
(422, 21112),
(423, 21113),
(424, 21114),
(425, 21115),
(426, 21116),
(427, 21117),
(428, 21201),
(429, 21202),
(430, 21203),
(431, 21204),
(432, 21205),
(433, 21206),
(434, 21207),
(435, 21208),
(436, 21209),
(437, 21210),
(438, 21301),
(439, 21302),
(440, 21303),
(441, 21304),
(442, 21305),
(443, 21306),
(444, 21307),
(445, 21308),
(446, 21309),
(447, 21310),
(448, 21311),
(449, 21312),
(450, 21313),
(451, 21314),
(452, 21401),
(453, 21402),
(454, 21403),
(455, 21404),
(456, 21405),
(457, 21406),
(458, 21407),
(459, 21408),
(460, 21409),
(461, 21410),
(462, 21411),
(463, 21412),
(464, 21413),
(465, 21414),
(466, 21415),
(467, 21416),
(468, 21417),
(469, 21418),
(470, 21419),
(471, 21420),
(472, 21421),
(473, 21422),
(474, 21423),
(475, 21424),
(476, 21425),
(477, 21426),
(478, 21427),
(479, 21428),
(480, 21429),
(481, 21430),
(482, 21431),
(483, 21432),
(484, 21433),
(485, 21434),
(486, 21435),
(487, 21436),
(488, 21437),
(489, 21501),
(490, 21502),
(491, 21503),
(492, 21504),
(493, 21505),
(494, 21506),
(495, 21507),
(496, 21508),
(497, 21509),
(498, 21510),
(499, 21511),
(500, 21512),
(501, 21513),
(502, 21514),
(503, 21515),
(504, 21516),
(505, 21517),
(506, 21518),
(507, 21519),
(508, 21520),
(509, 21521),
(510, 21522),
(511, 21601),
(512, 21602),
(513, 21603),
(514, 21604),
(515, 21605),
(516, 21606),
(517, 21607),
(518, 21608),
(519, 21609),
(520, 21610),
(521, 21611),
(522, 21612),
(523, 21613),
(524, 21614),
(525, 21615),
(526, 21616),
(527, 21617),
(528, 21618),
(529, 21619),
(530, 21620),
(531, 21621),
(532, 21622),
(533, 21623),
(534, 21624),
(535, 21625),
(536, 21626),
(537, 21627),
(538, 21628),
(539, 21629),
(540, 21630),
(541, 21631),
(542, 21632),
(543, 21633),
(544, 21634),
(545, 67303),
(546, 67304),
(547, 67305),
(548, 67306),
(549, 67307),
(550, 67308),
(551, 67309),
(552, 67310),
(553, 67311),
(554, 67312),
(555, 67313),
(556, 67314),
(557, 67315),
(558, 67316),
(559, 67317),
(560, 67318),
(561, 67319),
(562, 67320),
(563, 67321),
(564, 67322),
(565, 67323),
(566, 67324),
(567, 67325),
(568, 67326),
(569, 67327),
(570, 67328),
(571, 67329),
(572, 67330),
(573, 67401),
(574, 67402),
(575, 67403),
(576, 67404),
(577, 67405),
(578, 67406),
(579, 67407),
(580, 67408),
(581, 67409),
(582, 67410),
(583, 67411),
(584, 67412),
(585, 67413),
(586, 67414),
(587, 67415),
(588, 67416),
(589, 67417),
(590, 67418),
(591, 67419),
(592, 67420),
(593, 67421),
(594, 67422),
(595, 67423),
(596, 67424),
(597, 67425),
(598, 67426),
(599, 67427),
(600, 67428),
(601, 67429),
(602, 67430),
(603, 67431),
(604, 67432),
(605, 67433),
(606, 67434),
(607, 67435),
(608, 67436),
(609, 67437),
(610, 67438),
(611, 67439),
(612, 67440),
(613, 67501),
(614, 67502),
(615, 67503),
(616, 67504),
(617, 67505),
(618, 67506),
(619, 67507),
(620, 67508),
(621, 67509),
(622, 67510),
(623, 67511),
(624, 67512),
(625, 67513),
(626, 67514),
(627, 67515),
(628, 67516),
(629, 67517),
(630, 67518),
(631, 67519),
(632, 67520),
(633, 67521),
(634, 67522),
(635, 67523),
(636, 67524),
(637, 67525),
(638, 67526),
(639, 67527),
(640, 67528),
(641, 67529),
(642, 67530),
(643, 67531),
(644, 67532),
(645, 67533),
(646, 67534),
(647, 67535),
(648, 67601),
(649, 67602),
(650, 67603),
(651, 67604),
(652, 57101),
(653, 57102),
(654, 57103),
(655, 57104),
(656, 57105),
(657, 57106),
(658, 57107),
(659, 57108),
(660, 57109),
(661, 57110),
(662, 57111),
(663, 57112),
(664, 57113),
(665, 57114),
(666, 57115),
(667, 57116),
(668, 57117),
(669, 57118),
(670, 57119),
(671, 57120),
(672, 57121),
(673, 57201),
(674, 57202),
(675, 57203),
(676, 57204),
(677, 57205),
(678, 57206),
(679, 57207),
(680, 57208),
(681, 57209),
(682, 57210),
(683, 57211),
(684, 57212),
(685, 57213),
(686, 57214),
(687, 57215),
(688, 57216),
(689, 57217),
(690, 57218),
(691, 57219),
(692, 57220),
(693, 57221),
(694, 57222),
(695, 57223),
(696, 57224),
(697, 57225),
(698, 57226),
(699, 57228),
(700, 57229),
(701, 57230),
(702, 57301),
(703, 57302),
(704, 57303),
(705, 57304),
(706, 57305),
(707, 57306),
(708, 57307),
(709, 57308),
(710, 57309),
(711, 57310),
(712, 57311),
(713, 57312),
(714, 57313),
(715, 57314),
(716, 57315),
(717, 57316),
(718, 57317),
(719, 57318),
(720, 57319),
(721, 57320),
(722, 57321),
(723, 57401),
(724, 57402),
(725, 57403),
(726, 57404),
(727, 57405),
(728, 57406),
(729, 57407),
(730, 57408),
(731, 57409),
(732, 57410),
(733, 57411),
(734, 57412),
(735, 57413),
(736, 57414),
(737, 57415),
(738, 57420),
(739, 57421),
(740, 57422),
(741, 57423),
(742, 57427),
(743, 57428),
(744, 57429),
(745, 57430),
(746, 57431),
(747, 57437),
(748, 57438),
(749, 57439),
(750, 57440),
(751, 57441),
(752, 57442),
(753, 57443),
(754, 57444),
(755, 57445),
(756, 57446),
(757, 57505),
(758, 57506),
(759, 57507),
(760, 57508),
(761, 57509),
(762, 57510),
(763, 57515),
(764, 57516),
(765, 57517),
(766, 57518),
(767, 57519),
(768, 57520),
(769, 57601),
(770, 57731),
(771, 55101),
(772, 55102),
(773, 55103),
(774, 55104),
(775, 55105),
(776, 55106),
(777, 55107),
(778, 55108),
(779, 55109),
(780, 55110),
(781, 55111),
(782, 55112),
(783, 55113),
(784, 55114),
(785, 55115),
(786, 55116),
(787, 55117),
(788, 55118),
(789, 55119),
(790, 55201),
(791, 55202),
(792, 55203),
(793, 55204),
(794, 55205),
(795, 55206),
(796, 55207),
(797, 55208),
(798, 55209),
(799, 55210),
(800, 55211),
(801, 55212),
(802, 55213),
(803, 55214),
(804, 55215),
(805, 55216),
(806, 55217),
(807, 55218),
(808, 55219),
(809, 55220),
(810, 55221),
(811, 55222),
(812, 55223),
(813, 55224),
(814, 55225),
(815, 55226),
(816, 55227),
(817, 55301),
(818, 55302),
(819, 55303),
(820, 55304),
(821, 55305),
(822, 55307),
(823, 55308),
(824, 55309),
(825, 55310),
(826, 55311),
(827, 55312),
(828, 55313),
(829, 55314),
(830, 55315),
(831, 55316),
(832, 55317),
(833, 55318),
(834, 55319),
(835, 55320),
(836, 55321),
(837, 55322),
(838, 55323),
(839, 55324),
(840, 55325),
(841, 55326),
(842, 55327),
(843, 55328),
(844, 55329),
(845, 55401),
(846, 55402),
(847, 55403),
(848, 55404),
(849, 55405),
(850, 55406),
(851, 55408),
(852, 55409),
(853, 55410),
(854, 55411),
(855, 55412),
(856, 55413),
(857, 55414),
(858, 55415),
(859, 55416),
(860, 55417),
(861, 55418),
(862, 55419),
(863, 55420),
(864, 55421),
(865, 55422),
(866, 55423),
(867, 55424),
(868, 30101),
(869, 30102),
(870, 30103),
(871, 30104),
(872, 30105),
(873, 30106),
(874, 30107),
(875, 30108),
(876, 30109),
(877, 30511),
(878, 30512),
(879, 30513),
(880, 30514),
(881, 30515),
(882, 35101),
(883, 35102),
(884, 35103),
(885, 35104),
(886, 35105),
(887, 35106),
(888, 35107),
(889, 35108),
(890, 35109),
(891, 35110),
(892, 35111),
(893, 35112),
(894, 35113),
(895, 35114),
(896, 35201),
(897, 35202),
(898, 35203),
(899, 35204),
(900, 35205),
(901, 35206),
(902, 35207),
(903, 35208),
(904, 35822),
(905, 35823),
(906, 53101),
(907, 53102),
(908, 53103),
(909, 53104),
(910, 53105),
(911, 53106),
(912, 53107),
(913, 53108),
(914, 53109),
(915, 53110),
(916, 53111),
(917, 53112),
(918, 53113),
(919, 53114),
(920, 53115),
(921, 53116),
(922, 53117),
(923, 53118),
(924, 53119),
(925, 53120),
(926, 53121),
(927, 53122),
(928, 53123),
(929, 53124),
(930, 53125),
(931, 53126),
(932, 53127),
(933, 53128),
(934, 53129),
(935, 53130),
(936, 53131),
(937, 53132),
(938, 53133),
(939, 53134),
(940, 53820),
(941, 53821),
(942, 53822),
(943, 53823),
(944, 53825),
(945, 53826),
(946, 63101),
(947, 63102),
(948, 63103),
(949, 63104),
(950, 63105),
(951, 63106),
(952, 63107),
(953, 63108),
(954, 63109),
(955, 63110),
(956, 63111),
(957, 63112),
(958, 63113),
(959, 63114),
(960, 63115),
(961, 63116),
(962, 63117),
(963, 63118),
(964, 63201),
(965, 63202),
(966, 63203),
(967, 63204),
(968, 63614),
(969, 63615),
(970, 63616),
(971, 23101),
(972, 23102),
(973, 23103),
(974, 23104),
(975, 23105),
(976, 25101),
(977, 25102),
(978, 25103),
(979, 25104),
(980, 25105),
(981, 25106),
(982, 25107),
(983, 25108),
(984, 25109),
(985, 27101),
(986, 27102),
(987, 27103),
(988, 31101),
(989, 31102),
(990, 31103),
(991, 31104),
(992, 31105),
(993, 31106),
(994, 31107),
(995, 37101),
(996, 37102),
(997, 37103),
(998, 37104),
(999, 37105),
(1000, 37106),
(1001, 37107),
(1002, 39101),
(1003, 39102),
(1004, 39103),
(1005, 39104),
(1006, 39105),
(1007, 43101),
(1008, 43102),
(1009, 43103),
(1010, 43104),
(1011, 43105),
(1012, 51101),
(1013, 51102),
(1014, 51103),
(1015, 59101),
(1016, 59102),
(1017, 65101),
(1018, 65102),
(1019, 65103),
(1020, 65104),
(1021, 65105),
(1022, 65106),
(1023, 65107),
(1024, 65108),
(1025, 11101),
(1026, 11102),
(1027, 11103),
(1028, 11104),
(1029, 11105),
(1030, 11106),
(1031, 11107),
(1032, 11108),
(1033, 16109),
(1034, 16110),
(1035, 16111),
(1036, 16112),
(1037, 16113),
(1038, 16114),
(1039, 17101),
(1040, 17102),
(1041, 47101),
(1042, 47102),
(1043, 47103),
(1044, 47104),
(1045, 47709),
(1046, 50101),
(1047, 50102),
(1048, 50103),
(1049, 50104),
(1050, 50319),
(1051, 50320),
(1052, 50321),
(1053, 50322);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `event_ad_images`
--
ALTER TABLE `event_ad_images`
  ADD PRIMARY KEY (`images_ad_id`);

--
-- Indexes for table `event_ad_video`
--
ALTER TABLE `event_ad_video`
  ADD PRIMARY KEY (`video_ad_id`);

--
-- Indexes for table `event_announcements`
--
ALTER TABLE `event_announcements`
  ADD PRIMARY KEY (`announcement_id`);

--
-- Indexes for table `event_asset_rentals`
--
ALTER TABLE `event_asset_rentals`
  ADD PRIMARY KEY (`rental_id`);

--
-- Indexes for table `event_asset_returns`
--
ALTER TABLE `event_asset_returns`
  ADD PRIMARY KEY (`return_id`);

--
-- Indexes for table `event_attendees`
--
ALTER TABLE `event_attendees`
  ADD PRIMARY KEY (`attendee_id`);

--
-- Indexes for table `event_basic_info`
--
ALTER TABLE `event_basic_info`
  ADD PRIMARY KEY (`event_id`);

--
-- Indexes for table `event_chatroom`
--
ALTER TABLE `event_chatroom`
  ADD PRIMARY KEY (`chat_id`);

--
-- Indexes for table `event_funding_records`
--
ALTER TABLE `event_funding_records`
  ADD PRIMARY KEY (`funding_id`);

--
-- Indexes for table `event_fundraise_info`
--
ALTER TABLE `event_fundraise_info`
  ADD PRIMARY KEY (`fundraise_id`);

--
-- Indexes for table `event_fundraise_tags`
--
ALTER TABLE `event_fundraise_tags`
  ADD PRIMARY KEY (`fundraise_tag_id`);

--
-- Indexes for table `event_groupchat_messages`
--
ALTER TABLE `event_groupchat_messages`
  ADD PRIMARY KEY (`groupchat_message_id`);

--
-- Indexes for table `event_groupchat_participants`
--
ALTER TABLE `event_groupchat_participants`
  ADD PRIMARY KEY (`groupchart_participant_id`);

--
-- Indexes for table `event_invitees`
--
ALTER TABLE `event_invitees`
  ADD PRIMARY KEY (`invitee_id`);

--
-- Indexes for table `event_privatechat_messages`
--
ALTER TABLE `event_privatechat_messages`
  ADD PRIMARY KEY (`privatechat_message_id`);

--
-- Indexes for table `event_schedule_participants`
--
ALTER TABLE `event_schedule_participants`
  ADD PRIMARY KEY (`schedule_participant_id`);

--
-- Indexes for table `event_schedule_timetable`
--
ALTER TABLE `event_schedule_timetable`
  ADD PRIMARY KEY (`schedule_id`);

--
-- Indexes for table `event_service_hiring`
--
ALTER TABLE `event_service_hiring`
  ADD PRIMARY KEY (`hire_id`);

--
-- Indexes for table `event_service_ratings`
--
ALTER TABLE `event_service_ratings`
  ADD PRIMARY KEY (`review_id`);

--
-- Indexes for table `event_shared_media`
--
ALTER TABLE `event_shared_media`
  ADD PRIMARY KEY (`media_id`);

--
-- Indexes for table `fundraise_user_transactions`
--
ALTER TABLE `fundraise_user_transactions`
  ADD PRIMARY KEY (`fundraiseuser_id`);

--
-- Indexes for table `user_basic_info`
--
ALTER TABLE `user_basic_info`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `user_email` (`user_email`);

--
-- Indexes for table `user_event_asset`
--
ALTER TABLE `user_event_asset`
  ADD PRIMARY KEY (`asset_id`);

--
-- Indexes for table `user_event_jobs`
--
ALTER TABLE `user_event_jobs`
  ADD PRIMARY KEY (`profile_id`);

--
-- Indexes for table `user_gateway_info`
--
ALTER TABLE `user_gateway_info`
  ADD PRIMARY KEY (`gateway_id`);

--
-- Indexes for table `user_wallet_info`
--
ALTER TABLE `user_wallet_info`
  ADD PRIMARY KEY (`account_id`);

--
-- Indexes for table `user_wallet_transactions`
--
ALTER TABLE `user_wallet_transactions`
  ADD PRIMARY KEY (`transaction_id`);

--
-- Indexes for table `zip_validation`
--
ALTER TABLE `zip_validation`
  ADD PRIMARY KEY (`zip_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `event_ad_images`
--
ALTER TABLE `event_ad_images`
  MODIFY `images_ad_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_ad_video`
--
ALTER TABLE `event_ad_video`
  MODIFY `video_ad_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_announcements`
--
ALTER TABLE `event_announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_basic_info`
--
ALTER TABLE `event_basic_info`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `event_chatroom`
--
ALTER TABLE `event_chatroom`
  MODIFY `chat_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_funding_records`
--
ALTER TABLE `event_funding_records`
  MODIFY `funding_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_groupchat_messages`
--
ALTER TABLE `event_groupchat_messages`
  MODIFY `groupchat_message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_groupchat_participants`
--
ALTER TABLE `event_groupchat_participants`
  MODIFY `groupchart_participant_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_privatechat_messages`
--
ALTER TABLE `event_privatechat_messages`
  MODIFY `privatechat_message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_schedule_timetable`
--
ALTER TABLE `event_schedule_timetable`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_service_hiring`
--
ALTER TABLE `event_service_hiring`
  MODIFY `hire_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_service_ratings`
--
ALTER TABLE `event_service_ratings`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fundraise_user_transactions`
--
ALTER TABLE `fundraise_user_transactions`
  MODIFY `fundraiseuser_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_wallet_transactions`
--
ALTER TABLE `user_wallet_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `zip_validation`
--
ALTER TABLE `zip_validation`
  MODIFY `zip_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1054;

DELIMITER $$
--
-- Events
--
CREATE DEFINER=`root`@`localhost` EVENT `auto_in_session` ON SCHEDULE EVERY 1 MINUTE STARTS '2026-06-16 19:06:04' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    UPDATE event_basic_info
    SET event_activeness = 'In Session'
    WHERE event_activeness = 'Announced'
      AND event_date = CURDATE()
      AND event_time <= CURTIME();
END$$

CREATE DEFINER=`root`@`localhost` EVENT `auto_terminated` ON SCHEDULE EVERY 1 MINUTE STARTS '2026-06-16 19:06:04' ON COMPLETION NOT PRESERVE ENABLE DO BEGIN
    UPDATE event_basic_info
    SET event_activeness = 'Terminated'
    WHERE event_activeness = 'In Session'
      AND termination_date = CURDATE()
      AND termination_time <= CURTIME();
END$$

DELIMITER ;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
