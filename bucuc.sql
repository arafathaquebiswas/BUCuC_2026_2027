-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 22, 2025 at 06:55 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bucuc`
--

-- --------------------------------------------------------

--
-- Table structure for table `adminpanel`
--

CREATE TABLE `adminpanel` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('main_admin','admin') DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `adminpanel`
--

INSERT INTO `adminpanel` (`id`, `username`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(2, 'Super Admin 2', 'superadmin@bucuc.com', '$2y$10$WAGN/px0rrgNTIRyc5BzxehnzzZ/dG./IjR/3J5sFsyA652VFuIR6', 'main_admin', 'active', '2025-12-22 06:27:40', '2025-12-22 06:27:40'),
(5, 'ahsanauddry', 'ahsanauddry.ndc@gmail.com', '$2y$10$vTtMgKsnLFhQr6HzLXA3vul6veSRsC5QgXaRUmoYeLQBwGzRnOQI.', 'admin', 'active', '2025-12-22 17:40:44', '2025-12-22 17:40:44');

-- --------------------------------------------------------

--
-- Table structure for table `application_settings`
--

CREATE TABLE `application_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(100) NOT NULL,
  `setting_value` varchar(255) NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `application_settings`
--

INSERT INTO `application_settings` (`id`, `setting_name`, `setting_value`, `updated_at`) VALUES
(1, 'application_system_enabled', 'true', '2025-12-22 06:09:03'),
(2, 'welcome_email_enabled', 'false', '2025-12-22 06:09:03');

-- --------------------------------------------------------

--
-- Table structure for table `chart_data`
--

CREATE TABLE `chart_data` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `value` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chart_data`
--

INSERT INTO `chart_data` (`id`, `category`, `label`, `value`, `created_at`) VALUES
(10, 'member_growth', 'Fall2025', 2800, '2025-12-22 17:38:13'),
(11, 'member_growth', 'Summer 2025', 0, '2025-12-22 17:38:13'),
(12, 'member_growth', 'Summar 2027', 2000, '2025-12-22 17:38:13');

-- --------------------------------------------------------

--
-- Table structure for table `dashboardmanagement`
--

CREATE TABLE `dashboardmanagement` (
  `id` int(11) NOT NULL,
  `totalmembers` int(11) DEFAULT 0,
  `pending_applications` int(11) DEFAULT 0,
  `completedevents` int(11) DEFAULT 0,
  `others` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `mg_spring` int(11) DEFAULT 0,
  `mg_summer` int(11) DEFAULT 0,
  `mg_fall` int(11) DEFAULT 0,
  `gd_male` int(11) DEFAULT 0,
  `gd_female` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dashboardmanagement`
--

INSERT INTO `dashboardmanagement` (`id`, `totalmembers`, `pending_applications`, `completedevents`, `others`, `created_at`, `mg_spring`, `mg_summer`, `mg_fall`, `gd_male`, `gd_female`) VALUES
(1, 0, 0, 0, 0, '2025-12-22 06:09:03', 0, 0, 0, 0, 0),
(2, 0, 0, 600, 0, '2025-12-22 12:52:06', 0, 0, 0, 0, 0),
(3, 0, 0, 600, 0, '2025-12-22 12:57:25', 0, 0, 0, 0, 0),
(4, 0, 0, 600, 0, '2025-12-22 17:36:59', 0, 0, 0, 0, 0),
(5, 0, 0, 600, 0, '2025-12-22 17:37:33', 0, 0, 0, 0, 0),
(6, 0, 0, 600, 0, '2025-12-22 17:38:13', 0, 0, 0, 70, 30);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `event_gender_summary`
-- (See below for the actual view)
--
CREATE TABLE `event_gender_summary` (
`event_category` enum('Music','Dance','Drama','Art','Poetry','General')
,`male_count` decimal(22,0)
,`female_count` decimal(22,0)
,`other_count` decimal(22,0)
,`total_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Table structure for table `event_participants`
--

CREATE TABLE `event_participants` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `status` enum('registered','attended') DEFAULT 'registered',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `gender_distribution_view`
-- (See below for the actual view)
--
CREATE TABLE `gender_distribution_view` (
`event_category` enum('Music','Dance','Drama','Art','Poetry','General')
,`gender` enum('Male','Female','Other')
,`count` bigint(21)
,`percentage` decimal(26,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `university_id` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `gsuite_email` varchar(100) DEFAULT NULL,
  `department` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `gender` varchar(20) NOT NULL,
  `date_of_birth` date NOT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `event_category` enum('Music','Dance','Drama','Art','Poetry','General') NOT NULL DEFAULT 'General',
  `gender_tracking` enum('Male','Female','Other') NOT NULL DEFAULT 'Male',
  `firstPriority` varchar(100) DEFAULT NULL,
  `secondPriority` varchar(100) DEFAULT NULL,
  `application_status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `status` enum('active','inactive','pending') DEFAULT 'pending',
  `motivation` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `member_statistics`
-- (See below for the actual view)
--
CREATE TABLE `member_statistics` (
`total_members` bigint(21)
,`pending_applications` decimal(22,0)
,`accepted_members` decimal(22,0)
,`active_members` decimal(22,0)
,`total_males` decimal(22,0)
,`total_females` decimal(22,0)
,`total_others` decimal(22,0)
);

-- --------------------------------------------------------

--
-- Table structure for table `pending_applications`
--

CREATE TABLE `pending_applications` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `university_id` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `gsuite_email` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `gender` varchar(20) NOT NULL,
  `facebook_url` text DEFAULT NULL,
  `firstPriority` varchar(100) NOT NULL,
  `secondPriority` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `performance_metrics`
--

CREATE TABLE `performance_metrics` (
  `id` int(11) NOT NULL,
  `metric_type` varchar(50) NOT NULL,
  `metric_name` varchar(100) NOT NULL,
  `metric_value` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shortlisted_members`
--

CREATE TABLE `shortlisted_members` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `university_id` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `gsuite_email` varchar(255) NOT NULL,
  `department` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `semester` varchar(50) NOT NULL,
  `gender` varchar(20) NOT NULL,
  `facebook_url` text DEFAULT NULL,
  `firstPriority` varchar(100) NOT NULL,
  `secondPriority` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shortlisted_members`
--

INSERT INTO `shortlisted_members` (`id`, `full_name`, `university_id`, `email`, `gsuite_email`, `department`, `phone`, `semester`, `gender`, `facebook_url`, `firstPriority`, `secondPriority`, `created_at`, `updated_at`) VALUES
(3, 'Ahsan Habib', '22201027', 'ahsanauddry.ndc@gmail.com', 'ahsanauddry.ndc@gmail.com', 'CSE', '01300502013', '10th+', 'Male', 'https://javtiful.com/video/83394/jur-044', 'Performance', 'RD', '2025-12-22 17:45:39', '2025-12-22 17:46:04');

-- --------------------------------------------------------

--
-- Table structure for table `signup_status`
--

CREATE TABLE `signup_status` (
  `id` int(11) NOT NULL DEFAULT 1,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `signup_status`
--

INSERT INTO `signup_status` (`id`, `is_enabled`, `updated_at`, `updated_by`) VALUES
(1, 1, '2025-12-22 06:09:03', 'System');

-- --------------------------------------------------------

--
-- Table structure for table `venuinfo`
--

CREATE TABLE `venuinfo` (
  `venue_id` int(11) NOT NULL,
  `venue_name` varchar(255) NOT NULL,
  `venue_location` varchar(255) NOT NULL,
  `venue_dateTime` datetime NOT NULL,
  `venue_startingTime` varchar(20) NOT NULL,
  `venue_endingTime` varchar(20) NOT NULL,
  `venu_ampm` varchar(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `event_gender_summary`
--
DROP TABLE IF EXISTS `event_gender_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `event_gender_summary`  AS SELECT `members`.`event_category` AS `event_category`, sum(case when `members`.`gender_tracking` = 'Male' then 1 else 0 end) AS `male_count`, sum(case when `members`.`gender_tracking` = 'Female' then 1 else 0 end) AS `female_count`, sum(case when `members`.`gender_tracking` = 'Other' then 1 else 0 end) AS `other_count`, count(0) AS `total_count` FROM `members` WHERE `members`.`status` = 'active' GROUP BY `members`.`event_category` ORDER BY count(0) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `gender_distribution_view`
--
DROP TABLE IF EXISTS `gender_distribution_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `gender_distribution_view`  AS SELECT `members`.`event_category` AS `event_category`, `members`.`gender_tracking` AS `gender`, count(0) AS `count`, round(count(0) * 100.0 / sum(count(0)) over ( partition by `members`.`event_category`),2) AS `percentage` FROM `members` WHERE `members`.`status` = 'active' GROUP BY `members`.`event_category`, `members`.`gender_tracking` ORDER BY `members`.`event_category` ASC, `members`.`gender_tracking` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `member_statistics`
--
DROP TABLE IF EXISTS `member_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `member_statistics`  AS SELECT count(0) AS `total_members`, sum(case when `members`.`application_status` = 'pending' then 1 else 0 end) AS `pending_applications`, sum(case when `members`.`application_status` = 'accepted' then 1 else 0 end) AS `accepted_members`, sum(case when `members`.`status` = 'active' then 1 else 0 end) AS `active_members`, sum(case when `members`.`gender` = 'Male' then 1 else 0 end) AS `total_males`, sum(case when `members`.`gender` = 'Female' then 1 else 0 end) AS `total_females`, sum(case when `members`.`gender` = 'Other' then 1 else 0 end) AS `total_others` FROM `members` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `adminpanel`
--
ALTER TABLE `adminpanel`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `application_settings`
--
ALTER TABLE `application_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `chart_data`
--
ALTER TABLE `chart_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dashboardmanagement`
--
ALTER TABLE `dashboardmanagement`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `university_id` (`university_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `gsuite_email` (`gsuite_email`);

--
-- Indexes for table `pending_applications`
--
ALTER TABLE `pending_applications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD PRIMARY KEY (`id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `shortlisted_members`
--
ALTER TABLE `shortlisted_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `signup_status`
--
ALTER TABLE `signup_status`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `venuinfo`
--
ALTER TABLE `venuinfo`
  ADD PRIMARY KEY (`venue_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `adminpanel`
--
ALTER TABLE `adminpanel`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `application_settings`
--
ALTER TABLE `application_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `chart_data`
--
ALTER TABLE `chart_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `dashboardmanagement`
--
ALTER TABLE `dashboardmanagement`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_participants`
--
ALTER TABLE `event_participants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pending_applications`
--
ALTER TABLE `pending_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shortlisted_members`
--
ALTER TABLE `shortlisted_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `venuinfo`
--
ALTER TABLE `venuinfo`
  MODIFY `venue_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `event_participants`
--
ALTER TABLE `event_participants`
  ADD CONSTRAINT `event_participants_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `event_participants_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`);

--
-- Constraints for table `performance_metrics`
--
ALTER TABLE `performance_metrics`
  ADD CONSTRAINT `performance_metrics_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `adminpanel` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
