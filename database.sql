-- Online Voting System — Database Schema
-- Structure only (no data) — safe to commit publicly.
-- Import this into a fresh database named `online-voting`.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- Table structure for table `users`
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `voted` int(11) DEFAULT 0,
  `vote` int(11) DEFAULT 0,
  `verified` int(11) DEFAULT 0,
  `pending_vote` int(11) DEFAULT 0,
  `course` varchar(100) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `is_cr` int(11) DEFAULT 0,
  `cr_section` varchar(10) DEFAULT NULL,
  `cr_course` varchar(50) DEFAULT NULL,
  `voted_for` int(11) DEFAULT 0,
  `is_candidate` tinyint(4) DEFAULT 0,
  `reset_token` varchar(10) DEFAULT NULL,
  `reset_expires` varchar(20) DEFAULT NULL,
  `needs_reverify` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_details`
--
CREATE TABLE `user_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(200) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `roll_no` varchar(50) DEFAULT NULL,
  `erp_id` varchar(20) DEFAULT NULL,
  `user_photo` varchar(255) DEFAULT NULL,
  `college_id_photo` varchar(255) DEFAULT NULL,
  `college_id_back` varchar(255) DEFAULT NULL,
  `course` varchar(100) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--
CREATE TABLE `candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `course` varchar(100) DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `votes` int(11) DEFAULT 0,
  `pending_votes` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `fest_name` varchar(100) DEFAULT 'Ullaash',
  `time_limit` int(11) DEFAULT 10,
  `start_time` datetime DEFAULT NULL,
  `end_time` datetime DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `winner_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_candidates`
--
CREATE TABLE `event_candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `votes` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_votes`
--
CREATE TABLE `event_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `voted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ir_candidates`
--
CREATE TABLE `ir_candidates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(200) DEFAULT NULL,
  `votes` int(11) DEFAULT 0,
  `weightage_votes` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ir_votes`
--
CREATE TABLE `ir_votes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voter_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `weightage` decimal(10,2) DEFAULT 5.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ir_vote` (`voter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voting_settings`
--
CREATE TABLE `voting_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_name` varchar(100) DEFAULT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_name` (`setting_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Seed data for voting_settings (safe — no personal data)
--
INSERT INTO `voting_settings` (`setting_name`, `setting_value`) VALUES
('cr_voting_status', 'pending'),
('cr_voting_end_time', NULL),
('ir_voting_status', 'pending'),
('ir_voting_end_time', NULL);

-- --------------------------------------------------------

--
-- Foreign key constraints
--
ALTER TABLE `event_candidates`
  ADD CONSTRAINT `event_candidates_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

ALTER TABLE `event_votes`
  ADD CONSTRAINT `event_votes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `event_votes_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

COMMIT;
