-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 13, 2026 at 06:44 AM
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
-- Database: `assetflow`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `employee_id`, `action`, `details`, `created_at`) VALUES
(1, 1, 'System Initialized', 'Database setup completed and seeded with mock data.', '2026-07-12 03:27:23'),
(2, 2, 'Asset Registered', 'Registered MacBook Pro 16 (AF-0001)', '2026-07-12 03:27:23'),
(3, 2, 'Asset Allocated', 'Allocated AF-0001 to Priya Sharma', '2026-07-12 03:27:23'),
(4, 2, 'Asset Allocated', 'Allocated AF-0006 to Sunil Verma', '2026-07-12 03:27:23'),
(5, 8, 'Maintenance Requested', 'Reported issue with Ergonomic Chair (AF-0005)', '2026-07-12 03:27:23'),
(6, 1, 'Audit Cycle Created', 'Created Q3 IT Equipment Audit (ID: 1)', '2026-07-12 03:27:23'),
(7, 1, 'User Login', 'Logged into the system.', '2026-07-12 03:44:54'),
(8, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 03:46:21'),
(9, 3, 'User Login', 'Logged into the system.', '2026-07-12 03:46:32'),
(10, 3, 'Role Simulated', 'Simulated role changed to: employee', '2026-07-12 03:46:48'),
(11, 3, 'User Logout', 'Logged out of the system.', '2026-07-12 03:46:51'),
(12, 6, 'User Login', 'Logged into the system.', '2026-07-12 03:47:07'),
(13, 6, 'User Logout', 'Logged out of the system.', '2026-07-12 03:47:13'),
(14, 6, 'User Login', 'Logged into the system.', '2026-07-12 03:47:15'),
(15, 6, 'User Logout', 'Logged out of the system.', '2026-07-12 04:31:05'),
(16, 1, 'User Login', 'Logged into the system.', '2026-07-12 04:31:19'),
(17, 1, 'Role Simulated', 'Simulated role changed to: asset_manager', '2026-07-12 04:32:04'),
(18, 1, 'Role Simulated', 'Simulated role changed to: employee', '2026-07-12 04:32:10'),
(19, 1, 'Role Simulated', 'Simulated role changed to: admin', '2026-07-12 04:32:17'),
(20, 1, 'Role Simulated', 'Simulated role changed to: employee', '2026-07-12 04:32:25'),
(21, 1, 'Role Simulated', 'Simulated role changed to: admin', '2026-07-12 04:32:28'),
(22, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 04:32:30'),
(23, 3, 'User Login', 'Logged into the system.', '2026-07-12 04:32:37'),
(24, 3, 'Role Simulated', 'Simulated role changed to: employee', '2026-07-12 04:32:40'),
(25, 3, 'Role Simulated', 'Simulated role changed to: dept_head', '2026-07-12 04:32:45'),
(26, 3, 'Role Simulated', 'Simulated role changed to: asset_manager', '2026-07-12 04:32:48'),
(27, 3, 'Role Simulated', 'Simulated role changed to: admin', '2026-07-12 04:32:51'),
(28, 3, 'Role Simulated', 'Simulated role changed to: employee', '2026-07-12 04:32:57'),
(29, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 04:39:35'),
(30, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 06:55:47'),
(31, 2, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 06:55:58'),
(32, 2, 'User Logout', 'Logged out of the system.', '2026-07-12 06:56:24'),
(33, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 07:00:04'),
(34, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 08:09:09'),
(35, 6, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 08:09:25'),
(36, 6, 'Booked Shared Resource', 'Booked AF-0003 (Conference Table A) on 2026-07-16 from 11:01 to 13:02', '2026-07-12 08:10:02'),
(37, 6, 'Raised Maintenance Ticket', 'Raised maintenance ticket for AF-0001. Priority: High', '2026-07-12 08:21:51'),
(38, 6, 'User Logout', 'Logged out of the system.', '2026-07-12 08:31:54'),
(39, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 08:32:35'),
(40, 1, 'Approved Maintenance', 'Approved maintenance request for asset ID 1', '2026-07-12 08:32:47'),
(41, 1, 'Updated Maintenance Status', 'Status for maintenance request ID 2 changed to: Technician Assigned', '2026-07-12 08:33:45'),
(42, 1, 'Closed Audit Cycle', 'Locked audit cycle ID 1. Results: 0 assets flagged Lost, 0 flagged Damaged.', '2026-07-12 08:34:31'),
(43, 1, 'Asset Returned', 'Asset AF-0001 returned. Check-in Notes: sfdxhj. Condition: Good', '2026-07-12 08:37:47'),
(44, 1, 'Allocated Asset', 'Allocated AF-0002 to Department: IT Department', '2026-07-12 08:56:47'),
(45, 1, 'Asset Returned', 'Asset AF-0002 returned. Check-in Notes: good. Condition: Good', '2026-07-12 08:58:23'),
(46, 1, 'Allocated Asset', 'Allocated AF-0004 to Sneha Patel', '2026-07-12 08:58:49'),
(47, 1, 'Cancelled Booking', 'Cancelled booking ID 1 for asset AF-0003', '2026-07-12 08:59:05'),
(48, 1, 'Cancelled Booking', 'Cancelled booking ID 2 for asset AF-0003', '2026-07-12 08:59:07'),
(49, 1, 'Resolved Maintenance', 'Maintenance resolved for asset ID 1. Notes: done', '2026-07-12 09:00:57'),
(50, 1, 'Resolved Maintenance', 'Maintenance resolved for asset ID 5. Notes: Assigned Bob to perform cylinder replacement', '2026-07-12 09:01:02'),
(51, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 09:01:10'),
(52, 8, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 09:01:25'),
(53, 8, 'Booked Shared Resource', 'Booked AF-0003 (Conference Table A) on 2026-07-12 from 14:35 to 15:00', '2026-07-12 09:02:27'),
(54, 8, 'User Logout', 'Logged out of the system.', '2026-07-12 09:02:58'),
(55, 2, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 09:03:09'),
(56, 2, 'User Logout', 'Logged out of the system.', '2026-07-12 09:03:54'),
(57, 3, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 09:04:03'),
(58, 3, 'User Logout', 'Logged out of the system.', '2026-07-12 09:04:18'),
(59, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 09:04:29'),
(60, 1, 'Requested Transfer', 'Requested transfer for asset AF-0004', '2026-07-12 09:07:37'),
(61, 1, 'Requested Transfer', 'Requested transfer for asset AF-0004', '2026-07-12 09:08:17'),
(62, 1, 'Rejected Asset Transfer', 'Rejected transfer request ID 1', '2026-07-12 09:08:35'),
(63, 1, 'Approved Asset Transfer', 'Approved transfer for AF-0004 to target recipient.', '2026-07-12 09:08:38'),
(64, 1, 'Asset Returned', 'Asset AF-0006 returned. Check-in Notes: sgsdg. Condition: Good', '2026-07-12 09:09:14'),
(65, 1, 'Asset Returned', 'Asset AF-0004 returned. Check-in Notes: g. Condition: Good', '2026-07-12 09:13:19'),
(66, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 09:23:29'),
(67, 3, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 09:23:39'),
(68, 3, 'User Logout', 'Logged out of the system.', '2026-07-12 09:30:44'),
(69, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 09:30:55'),
(70, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 09:31:02'),
(71, 2, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 09:31:10'),
(72, 2, 'User Logout', 'Logged out of the system.', '2026-07-12 09:31:41'),
(73, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 09:32:10'),
(74, 1, 'Delete Department', 'Deleted department: Nikhil Kotak', '2026-07-12 11:22:00'),
(75, 1, 'Delete Department', 'Deleted department: Kotak Nikhil', '2026-07-12 11:22:22'),
(76, 1, 'Create Department', 'Created department: Kotak Nikhil', '2026-07-12 11:24:17'),
(77, 1, 'Delete Department', 'Deleted department: Kotak Nikhil', '2026-07-12 11:24:21'),
(78, 1, 'Create Department', 'Created department: Nikhil Kotak', '2026-07-12 11:51:43'),
(79, 1, 'Delete Department', 'Deleted department: Nikhil Kotak', '2026-07-12 11:51:46'),
(80, 1, 'Raised Maintenance Ticket', 'Raised maintenance ticket for AF-0003. Priority: Critical', '2026-07-12 12:03:08'),
(81, 1, 'Raised Maintenance Ticket', 'Raised maintenance ticket for AF-0004. Priority: Medium', '2026-07-12 12:03:18'),
(82, 1, 'Approved Maintenance', 'Approved maintenance request for asset ID 4', '2026-07-12 12:03:19'),
(83, 1, 'Updated Maintenance Status', 'Status for maintenance request ID 4 changed to: Technician Assigned', '2026-07-12 12:03:31'),
(84, 1, 'Approved Maintenance', 'Approved maintenance request for asset ID 3', '2026-07-12 12:03:35'),
(85, 1, 'Raised Maintenance Ticket', 'Raised maintenance ticket for AF-0007. Priority: High', '2026-07-12 12:03:46'),
(86, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 12:04:58'),
(87, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 12:05:51'),
(88, 1, 'Create Employee', 'Created employee: Nikhil Kotak (kotaknikhil0@gmail.com)', '2026-07-12 12:08:41'),
(89, 1, 'User Logout', 'Logged out of the system.', '2026-07-12 12:11:05'),
(90, 2, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 12:11:16'),
(91, 2, 'User Logout', 'Logged out of the system.', '2026-07-12 12:11:33'),
(92, 3, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 12:11:42'),
(93, 3, 'User Logout', 'Logged out of the system.', '2026-07-12 12:11:54'),
(94, 6, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 12:12:04'),
(95, 6, 'User Logout', 'Logged out of the system.', '2026-07-12 12:13:01'),
(96, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 12:20:17'),
(97, 1, 'User Login', 'Logged in successfully via Multi-Page portal.', '2026-07-12 12:27:31');

-- --------------------------------------------------------

--
-- Table structure for table `allocations`
--

CREATE TABLE `allocations` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `department_id` int(11) DEFAULT NULL,
  `allocated_by` int(11) NOT NULL,
  `allocation_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` timestamp NULL DEFAULT NULL,
  `condition_on_return` varchar(255) DEFAULT NULL,
  `status` enum('Active','Returned','Overdue') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `allocations`
--

INSERT INTO `allocations` (`id`, `asset_id`, `employee_id`, `department_id`, `allocated_by`, `allocation_date`, `expected_return_date`, `actual_return_date`, `condition_on_return`, `status`) VALUES
(1, 1, 3, NULL, 2, '2026-07-12 03:27:23', '2026-12-31', '2026-07-12 08:37:47', 'Good', 'Returned'),
(2, 6, 7, NULL, 2, '2026-07-12 03:27:23', '2026-07-10', '2026-07-12 09:09:14', 'Good', 'Returned'),
(3, 2, NULL, 1, 1, '2026-07-12 08:56:47', '2026-07-13', '2026-07-12 08:58:23', 'Good', 'Returned'),
(4, 4, 6, NULL, 1, '2026-07-12 08:58:49', '2026-07-15', '2026-07-12 09:08:38', 'Transferred', 'Returned'),
(5, 4, 3, NULL, 1, '2026-07-12 09:08:38', NULL, '2026-07-12 09:13:19', 'Good', 'Returned');

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `category_id` int(11) NOT NULL,
  `tag` varchar(50) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `acquisition_date` date NOT NULL,
  `acquisition_cost` decimal(10,2) NOT NULL,
  `condition_state` enum('New','Good','Fair','Poor','Damaged') DEFAULT 'Good',
  `location` varchar(100) NOT NULL,
  `is_shared` tinyint(1) DEFAULT 0,
  `status` enum('Available','Allocated','Reserved','Under Maintenance','Lost','Retired','Disposed') DEFAULT 'Available',
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `name`, `category_id`, `tag`, `serial_number`, `acquisition_date`, `acquisition_cost`, `condition_state`, `location`, `is_shared`, `status`, `photo`) VALUES
(1, 'MacBook Pro 16', 1, 'AF-0001', 'SN-MBP-9812', '2025-01-15', 2500.00, 'Good', 'Bangalore Office', 0, 'Available', NULL),
(2, 'Dell XPS 15', 1, 'AF-0002', 'SN-DELL-4412', '2025-05-10', 1800.00, 'Good', 'Mumbai Office', 0, 'Available', NULL),
(3, 'Conference Table A', 2, 'AF-0003', 'SN-TAB-001', '2024-08-20', 1200.00, 'Good', 'Bangalore Office', 1, 'Under Maintenance', NULL),
(4, 'Tesla Model 3', 3, 'AF-0004', 'SN-TESLA-889', '2024-11-05', 45000.00, 'Good', 'San Francisco Office', 1, 'Under Maintenance', NULL),
(5, 'Ergonomic Chair', 2, 'AF-0005', 'SN-CHR-771', '2025-03-12', 350.00, 'Damaged', 'Bangalore Office', 0, 'Available', NULL),
(6, 'iPad Air', 1, 'AF-0006', 'SN-IPAD-332', '2025-06-01', 600.00, 'Good', 'Delhi Office', 0, 'Available', NULL),
(7, 'Epson Projector', 1, 'AF-0007', 'SN-PROJ-3829', '2025-02-10', 800.00, 'Good', 'Bangalore Office', 1, 'Available', NULL),
(8, 'Meeting Room B', 2, 'AF-0008', 'SN-RM-B02', '2024-05-15', 0.00, 'Good', 'Mumbai Office', 1, 'Available', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_auditors`
--

CREATE TABLE `audit_auditors` (
  `audit_cycle_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_auditors`
--

INSERT INTO `audit_auditors` (`audit_cycle_id`, `employee_id`) VALUES
(1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `audit_cycles`
--

CREATE TABLE `audit_cycles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Active','Closed') DEFAULT 'Active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_cycles`
--

INSERT INTO `audit_cycles` (`id`, `name`, `department_id`, `location`, `start_date`, `end_date`, `status`, `created_by`, `created_at`) VALUES
(1, 'Q3 IT Equipment Audit', 1, 'Bangalore Office', '2026-07-01', '2026-07-31', 'Closed', 1, '2026-07-12 03:27:23');

-- --------------------------------------------------------

--
-- Table structure for table `audit_items`
--

CREATE TABLE `audit_items` (
  `id` int(11) NOT NULL,
  `audit_cycle_id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `status` enum('Pending','Verified','Missing','Damaged') DEFAULT 'Pending',
  `notes` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_items`
--

INSERT INTO `audit_items` (`id`, `audit_cycle_id`, `asset_id`, `status`, `notes`, `updated_at`) VALUES
(1, 1, 1, 'Verified', '', '2026-07-12 08:34:24'),
(2, 1, 6, 'Verified', '', '2026-07-12 08:34:20');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('Upcoming','Ongoing','Completed','Cancelled') DEFAULT 'Upcoming',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `asset_id`, `employee_id`, `booking_date`, `start_time`, `end_time`, `status`, `created_at`) VALUES
(1, 3, 6, '2026-07-12', '09:00:00', '10:30:00', 'Cancelled', '2026-07-12 03:27:23'),
(2, 3, 6, '2026-07-16', '11:01:00', '13:02:00', 'Cancelled', '2026-07-12 08:10:02'),
(3, 3, 8, '2026-07-12', '14:35:00', '15:00:00', 'Completed', '2026-07-12 09:02:27');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `custom_fields` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `custom_fields`) VALUES
(1, 'Electronics', '{\"warranty_months\":24,\"brand_required\":true}'),
(2, 'Furniture', '{\"material_required\":true}'),
(3, 'Vehicles', '{\"license_plate_required\":true,\"next_service_required\":true}'),
(4, 'Office Supplies', '{\"reorder_level_required\":true}');

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `head_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `name`, `head_id`, `parent_id`, `status`) VALUES
(1, 'IT Department', 3, NULL, 'Active'),
(2, 'Operations', 4, NULL, 'Active'),
(3, 'Marketing', 2, NULL, 'Active'),
(4, 'HR Department', 5, NULL, 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `role` enum('admin','asset_manager','dept_head','employee') DEFAULT 'employee',
  `status` enum('Active','Inactive') DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `name`, `email`, `password`, `department_id`, `role`, `status`) VALUES
(1, 'Admin User', 'admin@assetflow.com', '$2y$10$40LOkza1vmca7XCW21E3Z.i9CGjVWJeY7t0Q0LeASbdpJ5R3j9oX.', 2, 'admin', 'Active'),
(2, 'Vikram Malhotra', 'vikram@assetflow.com', '$2y$10$40LOkza1vmca7XCW21E3Z.i9CGjVWJeY7t0Q0LeASbdpJ5R3j9oX.', 2, 'asset_manager', 'Active'),
(3, 'Priya Sharma', 'priya@assetflow.com', '$2y$10$40LOkza1vmca7XCW21E3Z.i9CGjVWJeY7t0Q0LeASbdpJ5R3j9oX.', 1, 'dept_head', 'Active'),
(4, 'Rajesh Kumar', 'rajesh@assetflow.com', '$2y$10$40LOkza1vmca7XCW21E3Z.i9CGjVWJeY7t0Q0LeASbdpJ5R3j9oX.', 2, 'dept_head', 'Active'),
(5, 'Amit Singh', 'amit@assetflow.com', '$2y$10$40LOkza1vmca7XCW21E3Z.i9CGjVWJeY7t0Q0LeASbdpJ5R3j9oX.', 4, 'dept_head', 'Active'),
(6, 'Sneha Patel', 'sneha@assetflow.com', '$2y$10$40LOkza1vmca7XCW21E3Z.i9CGjVWJeY7t0Q0LeASbdpJ5R3j9oX.', 3, 'employee', 'Active'),
(7, 'Sunil Verma', 'sunil@assetflow.com', '$2y$10$40LOkza1vmca7XCW21E3Z.i9CGjVWJeY7t0Q0LeASbdpJ5R3j9oX.', 1, 'employee', 'Active'),
(8, 'Neha Gupta', 'neha@assetflow.com', '$2y$10$40LOkza1vmca7XCW21E3Z.i9CGjVWJeY7t0Q0LeASbdpJ5R3j9oX.', 1, 'employee', 'Active'),
(9, 'Nikhil Kotak', 'kotaknikhil0@gmail.com', '$2y$10$UXBuQs53PhEQS8NTC0ubWeEnnUKGVMgBBMPQTCqxTfY4ep0rs5Yyu', 2, 'employee', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `reported_by` int(11) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('Low','Medium','High','Critical') DEFAULT 'Medium',
  `photo_path` varchar(255) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected','Technician Assigned','In Progress','Resolved') DEFAULT 'Pending',
  `assigned_technician` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`id`, `asset_id`, `reported_by`, `description`, `priority`, `photo_path`, `status`, `assigned_technician`, `notes`, `created_at`, `updated_at`) VALUES
(1, 5, 8, 'Gas lift cylinder leaking, sinks to lowest height', 'High', NULL, 'Resolved', 'Technician Bob', 'Assigned Bob to perform cylinder replacement', '2026-07-12 03:27:23', '2026-07-12 09:01:02'),
(2, 1, 6, 'do fast', 'High', NULL, 'Resolved', 'Nikhil', 'done', '2026-07-12 08:21:51', '2026-07-12 09:00:57'),
(3, 3, 1, 'fsdb', 'Critical', NULL, 'Approved', NULL, NULL, '2026-07-12 12:03:08', '2026-07-12 12:03:35'),
(4, 4, 1, 'dbfdb', 'Medium', NULL, 'Technician Assigned', 'Nikhil', 'sgdf', '2026-07-12 12:03:18', '2026-07-12 12:03:31'),
(5, 7, 1, 'dfbf', 'High', NULL, 'Pending', NULL, NULL, '2026-07-12 12:03:46', '2026-07-12 12:03:46');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `employee_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 3, 'New Asset Allocated', 'MacBook Pro 16 (AF-0001) has been allocated to you.', 'info', 0, '2026-07-12 03:27:23'),
(2, 7, 'Overdue Return Warning', 'The iPad Air (AF-0006) allocation is overdue! Please return it or contact the Asset Manager.', 'warning', 0, '2026-07-12 03:27:23'),
(3, NULL, 'Audit Discrepancy Flagged', 'Sunil Verma\'s iPad Air was flagged as overdue during Q3 IT Equipment Audit.', 'danger', 1, '2026-07-12 03:27:23'),
(4, 2, 'Audit Cycle Assigned', 'You have been assigned as an auditor for Q3 IT Equipment Audit.', 'info', 0, '2026-07-12 03:27:23'),
(5, NULL, 'New Maintenance Request', 'A ticket has been raised for asset AF-0001. Priority: High.', 'warning', 1, '2026-07-12 08:21:51'),
(6, 6, 'Maintenance Approved', 'Your maintenance request has been approved. Asset status flipped to Under Maintenance.', 'success', 0, '2026-07-12 08:32:47'),
(7, NULL, 'Audit Cycle Closed', 'Audit \'Q3 IT Equipment Audit\' has been closed. Discrepancy report: 0 assets confirmed Missing (reverted to Lost), 0 assets flagged Damaged.', 'info', 0, '2026-07-12 08:34:31'),
(8, 6, 'Asset Allocated', 'Asset Tesla Model 3 (AF-0004) has been allocated to you.', 'info', 0, '2026-07-12 08:58:49'),
(9, 6, 'Maintenance Resolved', 'Repairs for your reported asset are complete. Status set to Available.', 'success', 0, '2026-07-12 09:00:57'),
(10, 8, 'Maintenance Resolved', 'Repairs for your reported asset are complete. Status set to Available.', 'success', 0, '2026-07-12 09:01:02'),
(11, 3, 'Transfer Requested', 'A transfer request for asset AF-0004 has been raised targeting you.', 'info', 0, '2026-07-12 09:08:17'),
(12, 3, 'Transfer Approved', 'The transfer of asset Tesla Model 3 (AF-0004) to you was approved.', 'success', 0, '2026-07-12 09:08:38'),
(13, NULL, 'Department Deleted', 'Department \'Nikhil Kotak\' was deleted.', 'info', 0, '2026-07-12 11:22:00'),
(14, NULL, 'Department Deleted', 'Department \'Kotak Nikhil\' was deleted.', 'info', 0, '2026-07-12 11:22:22'),
(15, NULL, 'New Department Created', 'Department \'Kotak Nikhil\' was created in Organization Setup.', 'info', 0, '2026-07-12 11:24:17'),
(16, NULL, 'Department Deleted', 'Department \'Kotak Nikhil\' was deleted.', 'info', 0, '2026-07-12 11:24:21'),
(17, NULL, 'New Department Created', 'Department \'Nikhil Kotak\' was created in Organization Setup.', 'info', 0, '2026-07-12 11:51:43'),
(18, NULL, 'Department Deleted', 'Department \'Nikhil Kotak\' was deleted.', 'info', 0, '2026-07-12 11:51:46'),
(19, NULL, 'New Maintenance Request', 'A ticket has been raised for asset AF-0003. Priority: Critical.', 'warning', 0, '2026-07-12 12:03:08'),
(20, NULL, 'New Maintenance Request', 'A ticket has been raised for asset AF-0004. Priority: Medium.', 'warning', 0, '2026-07-12 12:03:18'),
(21, 1, 'Maintenance Approved', 'Your maintenance request has been approved. Asset status flipped to Under Maintenance.', 'success', 0, '2026-07-12 12:03:19'),
(22, 1, 'Maintenance Approved', 'Your maintenance request has been approved. Asset status flipped to Under Maintenance.', 'success', 0, '2026-07-12 12:03:35'),
(23, NULL, 'New Maintenance Request', 'A ticket has been raised for asset AF-0007. Priority: High.', 'warning', 0, '2026-07-12 12:03:46'),
(24, 9, 'Welcome to AssetFlow', 'Your employee profile has been created by the administrator.', 'info', 0, '2026-07-12 12:08:41');

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `from_employee_id` int(11) DEFAULT NULL,
  `to_employee_id` int(11) DEFAULT NULL,
  `to_department_id` int(11) DEFAULT NULL,
  `requested_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_date` timestamp NULL DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transfers`
--

INSERT INTO `transfers` (`id`, `asset_id`, `from_employee_id`, `to_employee_id`, `to_department_id`, `requested_by`, `approved_by`, `request_date`, `approval_date`, `status`) VALUES
(1, 4, 6, NULL, 2, 1, 1, '2026-07-12 09:07:37', '2026-07-12 09:08:35', 'Rejected'),
(2, 4, 6, 3, NULL, 1, 1, '2026-07-12 09:08:17', '2026-07-12 09:08:38', 'Approved');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `allocations`
--
ALTER TABLE `allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `allocated_by` (`allocated_by`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tag` (`tag`),
  ADD UNIQUE KEY `serial_number` (`serial_number`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `audit_auditors`
--
ALTER TABLE `audit_auditors`
  ADD PRIMARY KEY (`audit_cycle_id`,`employee_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `audit_cycles`
--
ALTER TABLE `audit_cycles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `audit_items`
--
ALTER TABLE `audit_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `audit_cycle_id` (`audit_cycle_id`),
  ADD KEY `asset_id` (`asset_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `fk_dept_head` (`head_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `reported_by` (`reported_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `from_employee_id` (`from_employee_id`),
  ADD KEY `to_employee_id` (`to_employee_id`),
  ADD KEY `to_department_id` (`to_department_id`),
  ADD KEY `requested_by` (`requested_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `allocations`
--
ALTER TABLE `allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `audit_cycles`
--
ALTER TABLE `audit_cycles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_items`
--
ALTER TABLE `audit_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `allocations`
--
ALTER TABLE `allocations`
  ADD CONSTRAINT `allocations_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `allocations_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `allocations_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `allocations_ibfk_4` FOREIGN KEY (`allocated_by`) REFERENCES `employees` (`id`);

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `audit_auditors`
--
ALTER TABLE `audit_auditors`
  ADD CONSTRAINT `audit_auditors_ibfk_1` FOREIGN KEY (`audit_cycle_id`) REFERENCES `audit_cycles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `audit_auditors_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `audit_cycles`
--
ALTER TABLE `audit_cycles`
  ADD CONSTRAINT `audit_cycles_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `audit_cycles_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `employees` (`id`);

--
-- Constraints for table `audit_items`
--
ALTER TABLE `audit_items`
  ADD CONSTRAINT `audit_items_ibfk_1` FOREIGN KEY (`audit_cycle_id`) REFERENCES `audit_cycles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `audit_items_ibfk_2` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`);

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dept_head` FOREIGN KEY (`head_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `employees` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`from_employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`to_employee_id`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `transfers_ibfk_4` FOREIGN KEY (`to_department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `transfers_ibfk_5` FOREIGN KEY (`requested_by`) REFERENCES `employees` (`id`),
  ADD CONSTRAINT `transfers_ibfk_6` FOREIGN KEY (`approved_by`) REFERENCES `employees` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
