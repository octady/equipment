-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 03, 2026 at 07:44 AM
-- Server version: 8.0.30
-- PHP Version: 8.3.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `monitoring_equipment`
--

-- --------------------------------------------------------

--
-- Table structure for table `equipments`
--

CREATE TABLE `equipments` (
  `id` int NOT NULL,
  `section_id` int NOT NULL,
  `lokasi_id` int NOT NULL,
  `nama_peralatan` varchar(200) NOT NULL,
  `jam_operasi_harian` int DEFAULT '24',
  `standard_operasi` int DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `equipments`
--

INSERT INTO `equipments` (`id`, `section_id`, `lokasi_id`, `nama_peralatan`, `jam_operasi_harian`, `standard_operasi`, `created_at`) VALUES
(1, 1, 1, '1000 KVA', 24, 100, '2026-01-03 07:37:59'),
(2, 1, 1, '500 KVA', 24, 100, '2026-01-03 07:37:59'),
(3, 2, 1, 'Panel Cubicle', 24, 100, '2026-01-03 07:37:59'),
(4, 2, 1, 'Transformator Daya ( Step Down )', 24, 100, '2026-01-03 07:37:59'),
(5, 2, 1, 'Transformator Daya ( Step Down )', 24, 100, '2026-01-03 07:37:59'),
(6, 2, 2, 'Panel Cubicle', 24, 100, '2026-01-03 07:37:59'),
(7, 2, 2, 'Transformator Daya ( Step Up )', 24, 100, '2026-01-03 07:37:59'),
(8, 2, 1, 'Panel Distribusi Utama', 24, 100, '2026-01-03 07:37:59'),
(9, 2, 1, 'Panel Distribusi Utama', 24, 100, '2026-01-03 07:37:59'),
(10, 2, 3, 'Sub Panel Terminal', 24, 100, '2026-01-03 07:37:59'),
(11, 2, 4, 'Sub Panel Terminal', 24, 100, '2026-01-03 07:37:59'),
(12, 2, 5, 'Sub Panel Terminal', 24, 100, '2026-01-03 07:37:59'),
(13, 2, 6, 'Sub Panel Terminal', 24, 100, '2026-01-03 07:37:59'),
(14, 2, 7, 'Sub Panel Terminal', 24, 100, '2026-01-03 07:37:59'),
(15, 2, 8, 'Sub Panel Utama Power House', 24, 100, '2026-01-03 07:37:59'),
(16, 2, 2, 'Sub Panel AFL', 24, 100, '2026-01-03 07:37:59'),
(17, 2, 9, 'Sub Panel Kantor', 24, 100, '2026-01-03 07:37:59'),
(18, 2, 10, 'Sub Panel Kantor Bangland', 24, 100, '2026-01-03 07:37:59'),
(19, 2, 11, 'Sub Panel Kantor AAB', 24, 100, '2026-01-03 07:37:59'),
(20, 3, 12, 'UPS 1', 24, 100, '2026-01-03 07:37:59'),
(21, 3, 12, 'BATTERAY', 24, 100, '2026-01-03 07:37:59'),
(22, 3, 13, 'UPS 2', 24, 100, '2026-01-03 07:37:59'),
(23, 3, 13, 'BATTERAY', 24, 100, '2026-01-03 07:37:59'),
(24, 4, 14, 'RUNWAY EDGE LIGHT', 20, 100, '2026-01-03 07:37:59'),
(25, 4, 15, 'THRESHOLD / RUNWAY END LIGHT', 20, 100, '2026-01-03 07:37:59'),
(26, 4, 16, 'TURNING AREA LIGHT', 20, 100, '2026-01-03 07:37:59'),
(27, 4, 17, 'TURNING AREA LIGHT', 20, 100, '2026-01-03 07:37:59'),
(28, 4, 18, 'TAXIWAY EDGE LIGHT', 20, 100, '2026-01-03 07:37:59'),
(29, 4, 19, 'APRON EDGE LIGHT', 20, 100, '2026-01-03 07:37:59'),
(30, 4, 20, 'APPROACH LIGHT ( MALS )', 20, 100, '2026-01-03 07:37:59'),
(31, 4, 21, 'RTIL', 20, 100, '2026-01-03 07:37:59'),
(32, 4, 22, 'PAPI', 20, 100, '2026-01-03 07:37:59'),
(33, 4, 23, 'PAPI', 20, 100, '2026-01-03 07:37:59'),
(34, 4, 24, 'CONTROL DESK/SCREEN', 20, 100, '2026-01-03 07:37:59'),
(35, 4, 25, 'ROTATING BEACON', 20, 100, '2026-01-03 07:37:59'),
(36, 4, 26, 'LANDING TEE', 20, 100, '2026-01-03 07:37:59'),
(37, 4, 26, 'WIND DIRECTION', 20, 100, '2026-01-03 07:37:59'),
(38, 4, 26, 'SIRINE', 20, 100, '2026-01-03 07:37:59'),
(39, 4, 27, 'APRON FLOOD LIGHT ( SINGLE MAST LED )', 20, 100, '2026-01-03 07:37:59'),
(40, 4, 28, 'APRON FLOOD LIGHT ( TRIPLE MAST )', 20, 100, '2026-01-03 07:37:59'),
(41, 4, 27, 'APRON FLOOD LIGHT ( SINGLE MAST SODIUM )', 20, 100, '2026-01-03 07:37:59'),
(42, 4, 25, 'OBSTRUCTION LIGHT', 20, 100, '2026-01-03 07:37:59'),
(43, 4, 19, 'ADGS 1', 20, 100, '2026-01-03 07:37:59'),
(44, 4, 19, 'ADGS 2', 20, 100, '2026-01-03 07:37:59'),
(45, 4, 29, 'CCR - APP', 20, 100, '2026-01-03 07:37:59'),
(46, 4, 29, 'CCR - R/W', 20, 100, '2026-01-03 07:37:59'),
(47, 4, 29, 'CCR - T/W', 20, 100, '2026-01-03 07:37:59'),
(48, 4, 29, 'CCR - PAPI', 20, 100, '2026-01-03 07:37:59'),
(49, 5, 30, 'Fan Coil Unit D 01', 12, 100, '2026-01-03 07:37:59'),
(50, 5, 30, 'Fan Coil Unit D 02', 12, 100, '2026-01-03 07:37:59'),
(51, 5, 30, 'Fan Coil Unit D 03', 12, 100, '2026-01-03 07:37:59'),
(52, 5, 30, 'Fan Coil Unit D 04', 12, 100, '2026-01-03 07:37:59'),
(53, 5, 30, 'Fan Coil Unit D 05', 12, 100, '2026-01-03 07:37:59'),
(54, 5, 30, 'Fan Coil Unit D 06', 12, 100, '2026-01-03 07:37:59'),
(55, 5, 30, 'Fan Coil Unit D 07', 12, 100, '2026-01-03 07:37:59'),
(56, 5, 30, 'Fan Coil Unit D 08', 12, 100, '2026-01-03 07:37:59'),
(57, 5, 30, 'Fan Coil Unit D 09', 12, 100, '2026-01-03 07:37:59'),
(58, 5, 30, 'Fan Coil Unit D 10', 12, 100, '2026-01-03 07:37:59'),
(59, 5, 30, 'Fan Coil Unit D 11', 12, 100, '2026-01-03 07:37:59'),
(60, 5, 30, 'Fan Coil Unit D 12', 12, 100, '2026-01-03 07:37:59'),
(61, 5, 30, 'Fan Coil Unit A 01', 12, 100, '2026-01-03 07:37:59'),
(62, 5, 30, 'Fan Coil Unit A 02', 12, 100, '2026-01-03 07:37:59'),
(63, 5, 30, 'Fan Coil Unit A 03', 12, 100, '2026-01-03 07:37:59'),
(64, 5, 30, 'Fan Coil Unit A 04', 12, 100, '2026-01-03 07:37:59'),
(65, 5, 30, 'Fan Coil Unit A 05', 12, 100, '2026-01-03 07:37:59'),
(66, 5, 30, 'Fan Coil Unit A 06', 12, 100, '2026-01-03 07:37:59'),
(67, 5, 30, 'Fan Coil Unit A 07', 12, 100, '2026-01-03 07:37:59'),
(68, 5, 30, 'Fan Coil Unit A 08', 12, 100, '2026-01-03 07:37:59'),
(69, 5, 30, 'Fan Coil Unit A 09', 12, 100, '2026-01-03 07:37:59'),
(70, 5, 30, 'Fan Coil Unit A 10', 12, 100, '2026-01-03 07:37:59'),
(71, 5, 30, 'Fan Coil Unit A 11', 12, 100, '2026-01-03 07:37:59'),
(72, 5, 30, 'Fan Coil Unit A 12', 12, 100, '2026-01-03 07:37:59'),
(73, 5, 30, 'Fan Coil Unit A 13', 12, 100, '2026-01-03 07:37:59'),
(74, 5, 30, 'Fan Coil Unit A 14', 12, 100, '2026-01-03 07:37:59'),
(75, 5, 30, 'Fan Coil Unit A 15', 12, 100, '2026-01-03 07:37:59'),
(76, 5, 30, 'Fan Coil Unit A 16', 12, 100, '2026-01-03 07:37:59'),
(77, 5, 30, 'Fan Coil Unit A 17', 12, 100, '2026-01-03 07:37:59'),
(78, 5, 30, 'Fan Coil Unit A 18', 12, 100, '2026-01-03 07:37:59'),
(79, 5, 30, 'Fan Coil Unit A 19', 12, 100, '2026-01-03 07:37:59'),
(80, 5, 30, 'Fan Coil Unit A 20', 12, 100, '2026-01-03 07:37:59'),
(81, 5, 30, 'Fan Coil Unit M 01', 12, 100, '2026-01-03 07:37:59'),
(82, 5, 30, 'Fan Coil Unit M 02', 12, 100, '2026-01-03 07:37:59'),
(83, 5, 30, 'Fan Coil Unit M 03', 12, 100, '2026-01-03 07:37:59'),
(84, 5, 30, 'Fan Coil Unit M 04', 12, 100, '2026-01-03 07:37:59'),
(85, 5, 30, 'Fan Coil Unit M 05', 12, 100, '2026-01-03 07:37:59'),
(86, 5, 30, 'Fan Coil Unit M 06', 12, 100, '2026-01-03 07:37:59'),
(87, 5, 30, 'Fan Coil Unit M 07', 12, 100, '2026-01-03 07:37:59'),
(88, 5, 30, 'Fan Coil Unit M 08', 12, 100, '2026-01-03 07:37:59'),
(89, 5, 30, 'Fan Coil Unit M 09', 12, 100, '2026-01-03 07:37:59'),
(90, 5, 30, 'Fan Coil Unit M 10', 12, 100, '2026-01-03 07:37:59'),
(91, 5, 30, 'Fan Coil Unit M 11', 12, 100, '2026-01-03 07:37:59'),
(92, 5, 30, 'Fan Coil Unit M 12', 12, 100, '2026-01-03 07:37:59'),
(93, 5, 30, 'Fan Coil Unit M 13', 12, 100, '2026-01-03 07:37:59'),
(94, 5, 30, 'Fan Coil Unit M 14', 12, 100, '2026-01-03 07:37:59'),
(95, 5, 30, 'Fan Coil Unit M 15', 12, 100, '2026-01-03 07:37:59'),
(96, 5, 30, 'Fan Coil Unit garbarata 1', 12, 100, '2026-01-03 07:37:59'),
(97, 5, 30, 'Fan Coil Unit garbarata 2', 12, 100, '2026-01-03 07:37:59'),
(98, 5, 31, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(99, 5, 32, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(100, 5, 33, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(101, 5, 34, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(102, 5, 35, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(103, 5, 36, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(104, 5, 37, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(105, 5, 38, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(106, 5, 39, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(107, 5, 40, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(108, 5, 41, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(109, 5, 42, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(110, 5, 43, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(111, 5, 44, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(112, 5, 45, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(113, 5, 46, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(114, 5, 47, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(115, 5, 48, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(116, 5, 49, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(117, 5, 50, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(118, 5, 51, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(119, 5, 52, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(120, 5, 53, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(121, 5, 54, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(122, 5, 55, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(123, 5, 56, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(124, 5, 57, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(125, 5, 58, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(126, 5, 59, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(127, 5, 60, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(128, 5, 61, 'AC Split', 12, 100, '2026-01-03 07:37:59'),
(129, 6, 62, 'Water Pump', 15, 100, '2026-01-03 07:37:59'),
(130, 6, 63, 'Water Pump', 15, 100, '2026-01-03 07:37:59'),
(131, 6, 64, 'Water Pump', 15, 100, '2026-01-03 07:37:59'),
(132, 6, 65, 'Water Pump', 15, 100, '2026-01-03 07:37:59'),
(133, 7, 66, 'Baggage Handling 1', 12, 100, '2026-01-03 07:37:59'),
(134, 7, 66, 'Baggage handling 2', 12, 100, '2026-01-03 07:37:59'),
(135, 7, 66, 'Conveyor 1', 12, 100, '2026-01-03 07:37:59'),
(136, 7, 66, 'Conveyor 2', 12, 100, '2026-01-03 07:37:59'),
(137, 7, 66, 'Conveyor 3', 12, 100, '2026-01-03 07:37:59'),
(138, 7, 66, 'Conveyor 4', 12, 100, '2026-01-03 07:37:59'),
(139, 7, 66, 'Conveyor 5', 12, 100, '2026-01-03 07:37:59'),
(140, 7, 66, 'Conveyor 6', 12, 100, '2026-01-03 07:37:59'),
(141, 7, 66, 'Conveyor 7', 12, 100, '2026-01-03 07:37:59'),
(142, 7, 66, 'Conveyor 8', 12, 100, '2026-01-03 07:37:59'),
(143, 7, 66, 'Conveyor 9', 12, 100, '2026-01-03 07:37:59'),
(144, 7, 66, 'Conveyor 10', 12, 100, '2026-01-03 07:37:59'),
(145, 7, 66, 'Conveyor 11', 12, 100, '2026-01-03 07:37:59'),
(146, 7, 66, 'Conveyor 12', 12, 100, '2026-01-03 07:37:59'),
(147, 7, 66, 'Conveyor 13', 12, 100, '2026-01-03 07:37:59'),
(148, 7, 66, 'Conveyor 14', 12, 100, '2026-01-03 07:37:59'),
(149, 7, 66, 'Conveyor 15', 12, 100, '2026-01-03 07:37:59'),
(150, 7, 66, 'Conveyor 16', 12, 100, '2026-01-03 07:37:59'),
(151, 7, 66, 'Conveyor 17', 12, 100, '2026-01-03 07:37:59'),
(152, 7, 66, 'Conveyor 18', 12, 100, '2026-01-03 07:37:59'),
(153, 7, 66, 'Conveyor 19', 12, 100, '2026-01-03 07:37:59'),
(154, 7, 66, 'Conveyor 20', 12, 100, '2026-01-03 07:37:59'),
(155, 7, 67, 'Conveyor Carousel 1', 12, 100, '2026-01-03 07:37:59'),
(156, 7, 67, 'Conveyor Carousel 2', 12, 100, '2026-01-03 07:37:59'),
(157, 7, 67, 'Conveyor Carousel 3', 12, 100, '2026-01-03 07:37:59'),
(158, 8, 68, 'Escalator No.1', 12, 100, '2026-01-03 07:37:59'),
(159, 8, 68, 'Escalator No.2', 12, 100, '2026-01-03 07:37:59'),
(160, 8, 69, 'Escalator No.3', 12, 100, '2026-01-03 07:37:59'),
(161, 8, 69, 'Escalator No.4', 12, 100, '2026-01-03 07:37:59'),
(162, 8, 70, 'Escalator No.5', 12, 100, '2026-01-03 07:37:59'),
(163, 8, 71, 'Escalator No.6', 12, 100, '2026-01-03 07:37:59'),
(164, 8, 70, 'Escalator No.7', 12, 100, '2026-01-03 07:37:59'),
(165, 8, 71, 'Escalator No.8', 12, 100, '2026-01-03 07:37:59'),
(166, 8, 72, 'Escalator No.9', 12, 100, '2026-01-03 07:37:59'),
(167, 8, 73, 'Escalator No.10', 12, 100, '2026-01-03 07:37:59'),
(168, 8, 74, 'Lift No. 1', 12, 100, '2026-01-03 07:37:59'),
(169, 8, 75, 'Lift No. 2', 12, 100, '2026-01-03 07:37:59'),
(170, 8, 76, 'Lift No. 3', 12, 100, '2026-01-03 07:37:59'),
(171, 8, 77, 'Lift No. 4', 12, 100, '2026-01-03 07:37:59'),
(172, 9, 78, 'Stand 1', 20, 100, '2026-01-03 07:37:59'),
(173, 9, 79, 'Stand 2', 20, 100, '2026-01-03 07:37:59'),
(174, 10, 80, 'F1. Kenbri 204', 24, 100, '2026-01-03 07:37:59'),
(175, 10, 80, 'F2. Kenbri 303', 24, 100, '2026-01-03 07:37:59'),
(176, 10, 80, 'F3. Morita', 24, 100, '2026-01-03 07:37:59'),
(177, 10, 80, 'F4. Morita', 24, 100, '2026-01-03 07:37:59'),
(178, 10, 80, 'Command Car', 24, 100, '2026-01-03 07:37:59'),
(179, 10, 80, 'Utility Car', 24, 100, '2026-01-03 07:37:59'),
(180, 10, 81, 'NT. Hino', 24, 100, '2026-01-03 07:37:59'),
(181, 10, 80, 'A1 Ambulance', 24, 100, '2026-01-03 07:37:59'),
(182, 10, 80, 'A2 Ambulance', 24, 100, '2026-01-03 07:37:59'),
(183, 11, 82, 'Runway Sweeper', 24, 100, '2026-01-03 07:37:59'),
(184, 11, 82, 'Water Blesting', 24, 100, '2026-01-03 07:37:59'),
(185, 11, 82, 'Tractor Mower 3', 24, 100, '2026-01-03 07:37:59'),
(186, 11, 82, 'Tractor Mower 4', 24, 100, '2026-01-03 07:37:59'),
(187, 11, 82, 'Tractor MF', 24, 100, '2026-01-03 07:37:59'),
(188, 11, 82, 'Bus', 24, 100, '2026-01-03 07:37:59'),
(189, 11, 82, 'Vibrator Roller', 24, 100, '2026-01-03 07:37:59'),
(190, 11, 82, 'Forklift', 24, 100, '2026-01-03 07:37:59'),
(191, 11, 83, 'Pick Up', 24, 100, '2026-01-03 07:37:59'),
(192, 11, 83, 'Pick Up A2B', 24, 100, '2026-01-03 07:37:59'),
(193, 11, 84, 'Pick Up', 24, 100, '2026-01-03 07:37:59'),
(194, 11, 84, 'Pick Up', 24, 100, '2026-01-03 07:37:59'),
(195, 11, 84, 'Pick Up', 24, 100, '2026-01-03 07:37:59'),
(196, 11, 82, 'Pick Up', 24, 100, '2026-01-03 07:37:59'),
(197, 11, 82, 'Dump Truck', 24, 100, '2026-01-03 07:37:59');

-- --------------------------------------------------------

--
-- Table structure for table `fasilitas`
--

CREATE TABLE `fasilitas` (
  `id` int NOT NULL,
  `nama_fasilitas` varchar(100) NOT NULL,
  `kode_fasilitas` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `fasilitas`
--

INSERT INTO `fasilitas` (`id`, `nama_fasilitas`, `kode_fasilitas`, `created_at`) VALUES
(1, 'Bandara', 'AIRPORT', '2026-01-03 05:42:01');

-- --------------------------------------------------------

--
-- Table structure for table `inspections_daily`
--

CREATE TABLE `inspections_daily` (
  `id` int NOT NULL,
  `equipment_id` int NOT NULL,
  `equipment_name` varchar(255) DEFAULT NULL,
  `tanggal` date NOT NULL,
  `status` enum('O','X','V','-') NOT NULL DEFAULT 'O',
  `keterangan` text,
  `foto` varchar(255) DEFAULT NULL,
  `jam_operasi` int DEFAULT NULL,
  `checked_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inspection_photos`
--

CREATE TABLE `inspection_photos` (
  `id` int NOT NULL,
  `inspection_id` int NOT NULL,
  `foto_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lokasi`
--

CREATE TABLE `lokasi` (
  `id` int NOT NULL,
  `fasilitas_id` int NOT NULL,
  `nama_lokasi` varchar(100) NOT NULL,
  `lantai` varchar(20) DEFAULT NULL,
  `area` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `lokasi`
--

INSERT INTO `lokasi` (`id`, `fasilitas_id`, `nama_lokasi`, `lantai`, `area`, `created_at`) VALUES
(1, 1, 'Gedung Genset', NULL, NULL, '2026-01-03 07:37:59'),
(2, 1, 'Ruang CCR', NULL, NULL, '2026-01-03 07:37:59'),
(3, 1, 'Ruang Panel Terminal Lt.Dasar 1', NULL, NULL, '2026-01-03 07:37:59'),
(4, 1, 'Ruang Panel Terminal Lt.Dasar 2', NULL, NULL, '2026-01-03 07:37:59'),
(5, 1, 'Ruang Panel Terminal Lt.Atas 1', NULL, NULL, '2026-01-03 07:37:59'),
(6, 1, 'Ruang Panel Terminal Lt.Atas 2', NULL, NULL, '2026-01-03 07:37:59'),
(7, 1, 'Ruang Panel Terminal Mezanin', NULL, NULL, '2026-01-03 07:37:59'),
(8, 1, 'Ruang Panel Power House', NULL, NULL, '2026-01-03 07:37:59'),
(9, 1, 'Gudang ATK', NULL, NULL, '2026-01-03 07:37:59'),
(10, 1, 'Kantor Bangland', NULL, NULL, '2026-01-03 07:37:59'),
(11, 1, 'Kantor AAB', NULL, NULL, '2026-01-03 07:37:59'),
(12, 1, 'CCR', NULL, NULL, '2026-01-03 07:37:59'),
(13, 1, 'TERMINAL', NULL, NULL, '2026-01-03 07:37:59'),
(14, 1, 'RUNWAY EDGE', NULL, NULL, '2026-01-03 07:37:59'),
(15, 1, 'T/H 14 & 32', NULL, NULL, '2026-01-03 07:37:59'),
(16, 1, 'T/H 14', NULL, NULL, '2026-01-03 07:37:59'),
(17, 1, 'T/H 32', NULL, NULL, '2026-01-03 07:37:59'),
(18, 1, 'Taxi A,B,C & D', NULL, NULL, '2026-01-03 07:37:59'),
(19, 1, 'APRON', NULL, NULL, '2026-01-03 07:37:59'),
(20, 1, 'RUNWAY 32', NULL, NULL, '2026-01-03 07:37:59'),
(21, 1, 'RUNWAY 14', NULL, NULL, '2026-01-03 07:37:59'),
(22, 1, 'R/W 14', NULL, NULL, '2026-01-03 07:37:59'),
(23, 1, 'R/W 32', NULL, NULL, '2026-01-03 07:37:59'),
(24, 1, 'CCR & TOWER', NULL, NULL, '2026-01-03 07:37:59'),
(25, 1, 'TOWER', NULL, NULL, '2026-01-03 07:37:59'),
(26, 1, 'SHOULDER', NULL, NULL, '2026-01-03 07:37:59'),
(27, 1, 'APRON ( 2 UNIT )', NULL, NULL, '2026-01-03 07:37:59'),
(28, 1, 'APRON ( 3 UNIT )', NULL, NULL, '2026-01-03 07:37:59'),
(29, 1, 'Gedung CCR', NULL, NULL, '2026-01-03 07:37:59'),
(30, 1, 'Gedung Terminal', NULL, NULL, '2026-01-03 07:37:59'),
(31, 1, 'RUANG EGM 2', NULL, NULL, '2026-01-03 07:37:59'),
(32, 1, 'RUANG ASSTMAN FHR', NULL, NULL, '2026-01-03 07:37:59'),
(33, 1, 'RUANG ASSTMAN AOS', NULL, NULL, '2026-01-03 07:37:59'),
(34, 1, 'RUANG ASSTMAN SAR', NULL, NULL, '2026-01-03 07:37:59'),
(35, 1, 'RUANG ASSTMAN AMN', NULL, NULL, '2026-01-03 07:37:59'),
(36, 1, 'RUANG AKHLAK CORNER 1', NULL, NULL, '2026-01-03 07:37:59'),
(37, 1, 'RUANG AKHLAK CORNER 2', NULL, NULL, '2026-01-03 07:37:59'),
(38, 1, 'RUANG ASSTMAN COMERCIAL', NULL, NULL, '2026-01-03 07:37:59'),
(39, 1, 'RUANG SRQC', NULL, NULL, '2026-01-03 07:37:59'),
(40, 1, 'MUSHOLA WANITA', NULL, NULL, '2026-01-03 07:37:59'),
(41, 1, 'RUANG STAFF KEUANGAN/KEPEGAWAIAN', NULL, NULL, '2026-01-03 07:37:59'),
(42, 1, 'RUANG STAFF FHR', NULL, NULL, '2026-01-03 07:37:59'),
(43, 1, 'RUANG KOORDINATOR ELBAN', NULL, NULL, '2026-01-03 07:37:59'),
(44, 1, 'RUANGAN TEKNISI ELBAN 1', NULL, NULL, '2026-01-03 07:37:59'),
(45, 1, 'RUANGAN TEKNISI ELBAN 2', NULL, NULL, '2026-01-03 07:37:59'),
(46, 1, 'GUDANG ELBAN', NULL, NULL, '2026-01-03 07:37:59'),
(47, 1, 'KLINIK KIMIA FARMA', NULL, NULL, '2026-01-03 07:37:59'),
(48, 1, 'KANTOR DPC TKG SEKARPURA 2', NULL, NULL, '2026-01-03 07:37:59'),
(49, 1, 'KANTOR KOPERASI', NULL, NULL, '2026-01-03 07:37:59'),
(50, 1, 'KOORDINATOR INFRASTRUKTUR', NULL, NULL, '2026-01-03 07:37:59'),
(51, 1, 'RUANG TEKNISI INFRASTRUKTUR 1', NULL, NULL, '2026-01-03 07:37:59'),
(52, 1, 'RUANG TEKNISI INFRASTRUKTUR 2', NULL, NULL, '2026-01-03 07:37:59'),
(53, 1, 'RUANG TEKNISI INFRASTRUKTUR 3', NULL, NULL, '2026-01-03 07:37:59'),
(54, 1, 'KANTOR PARKIR 1', NULL, NULL, '2026-01-03 07:37:59'),
(55, 1, 'KANTOR PARKIR 2', NULL, NULL, '2026-01-03 07:37:59'),
(56, 1, 'MUSHOLA PARKIR', NULL, NULL, '2026-01-03 07:37:59'),
(57, 1, 'KANTOR APS', NULL, NULL, '2026-01-03 07:37:59'),
(58, 1, 'RUANG PANEL', NULL, NULL, '2026-01-03 07:37:59'),
(59, 1, 'RUANG TRAFO', NULL, NULL, '2026-01-03 07:37:59'),
(60, 1, 'RUANG CCR 1', NULL, NULL, '2026-01-03 07:37:59'),
(61, 1, 'RUANG CCR 2', NULL, NULL, '2026-01-03 07:37:59'),
(62, 1, 'Area Kantor Airline', NULL, NULL, '2026-01-03 07:37:59'),
(63, 1, 'Belakang Fire Station', NULL, NULL, '2026-01-03 07:37:59'),
(64, 1, 'Area A2B', NULL, NULL, '2026-01-03 07:37:59'),
(65, 1, 'Depan Fire Station', NULL, NULL, '2026-01-03 07:37:59'),
(66, 1, 'Check In Counter', NULL, NULL, '2026-01-03 07:37:59'),
(67, 1, 'Arrival', NULL, NULL, '2026-01-03 07:37:59'),
(68, 1, 'SKY BRIDGE', NULL, NULL, '2026-01-03 07:37:59'),
(69, 1, 'Terminal Lt. Dasar --> Lt.1', NULL, NULL, '2026-01-03 07:37:59'),
(70, 1, 'Terminal Lt. 1 --> Lt. Mezanine', NULL, NULL, '2026-01-03 07:37:59'),
(71, 1, 'Terminal Lt. 1 <-- Lt. Mezanine', NULL, NULL, '2026-01-03 07:37:59'),
(72, 1, 'Terminal Lt. 1 --> Domestic Dep', NULL, NULL, '2026-01-03 07:37:59'),
(73, 1, 'Terminal Lt. 1 --> International Arr', NULL, NULL, '2026-01-03 07:37:59'),
(74, 1, 'Gedung Parkir', NULL, NULL, '2026-01-03 07:37:59'),
(75, 1, 'Ruang SCP Karyawan', NULL, NULL, '2026-01-03 07:37:59'),
(76, 1, 'Ruang Kedatangan', NULL, NULL, '2026-01-03 07:37:59'),
(77, 1, 'Ruang Chekin', NULL, NULL, '2026-01-03 07:37:59'),
(78, 1, 'Stand 1', NULL, NULL, '2026-01-03 07:37:59'),
(79, 1, 'Stand 2', NULL, NULL, '2026-01-03 07:37:59'),
(80, 1, 'PKP-PK', NULL, NULL, '2026-01-03 07:37:59'),
(81, 1, 'LAMPUNG SELATAN, 30 JUNI 2025', NULL, NULL, '2026-01-03 07:37:59'),
(82, 1, 'Alat-Alat Berat', NULL, NULL, '2026-01-03 07:37:59'),
(83, 1, 'Listrik', NULL, NULL, '2026-01-03 07:37:59'),
(84, 1, 'Bangland', NULL, NULL, '2026-01-03 07:37:59');

-- --------------------------------------------------------

--
-- Table structure for table `personnel`
--

CREATE TABLE `personnel` (
  `id` int NOT NULL,
  `nama_personnel` varchar(100) NOT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `personnel`
--

INSERT INTO `personnel` (`id`, `nama_personnel`, `jabatan`, `created_at`) VALUES
(1, 'YOGA PRANATA', 'Airport Equipment Supervisor', '2026-01-03 05:42:01'),
(2, 'DENNY INDRAWAN', 'Airport Equipment Supervisor', '2026-01-03 05:42:01'),
(3, 'VICA JANUAR ROOROH', 'Airport Equipment Supervisor', '2026-01-03 05:42:01'),
(4, 'DENNIS EKA CAHYA E.', 'Airport Equipment Engineer', '2026-01-03 05:42:01'),
(5, 'DANIEL BUDI WIJAYANTO', 'Airport Equipment Engineer', '2026-01-03 05:42:01');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int NOT NULL,
  `parent_category` enum('ELECTRICAL','MECHANICAL') NOT NULL,
  `nama_section` varchar(100) NOT NULL,
  `kode_section` varchar(20) DEFAULT NULL,
  `urutan` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `parent_category`, `nama_section`, `kode_section`, `urutan`, `created_at`) VALUES
(1, 'ELECTRICAL', 'GENERATOR SET', NULL, 1, '2026-01-03 05:42:01'),
(2, 'ELECTRICAL', 'FASILITAS JARINGAN LISTRIK', NULL, 2, '2026-01-03 05:42:01'),
(3, 'ELECTRICAL', 'UNINTERRUPTIBLE POWER SUPPLY (UPS)', NULL, 3, '2026-01-03 05:42:01'),
(4, 'ELECTRICAL', 'AERONAUTICAL GROUND LIGHTING', NULL, 4, '2026-01-03 05:42:01'),
(5, 'MECHANICAL', 'SISTEM HVAC', NULL, 5, '2026-01-03 05:42:01'),
(6, 'MECHANICAL', 'WATER SUPPLY SYSTEM, STP DAN SISTEM PEMADAM', NULL, 6, '2026-01-03 05:42:01'),
(7, 'MECHANICAL', 'BAGGAGE HANDLING SYSTEM (BHS)', NULL, 7, '2026-01-03 05:42:01'),
(8, 'MECHANICAL', 'PASSENGER MOVING SYSTEM (PMS)', NULL, 8, '2026-01-03 05:42:01'),
(9, 'MECHANICAL', 'GARBARATA', NULL, 9, '2026-01-03 05:42:01'),
(10, 'MECHANICAL', 'PKP-PK', NULL, 10, '2026-01-03 05:42:01'),
(11, 'MECHANICAL', 'A2B & KENDARAAN OPERASIONAL', NULL, 11, '2026-01-03 05:42:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`, `created_at`) VALUES
(1, 'admin', '6408597507d9ffe85f5a02c628e6ed02', 'Administrator', 'admin', '2025-12-30 05:11:54'),
(2, 'user', 'fcd7258d18dd8a4000e648546b8d7e6a', 'Teknisi', 'user', '2025-12-30 05:11:54');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `equipments`
--
ALTER TABLE `equipments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `lokasi_id` (`lokasi_id`);

--
-- Indexes for table `fasilitas`
--
ALTER TABLE `fasilitas`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inspections_daily`
--
ALTER TABLE `inspections_daily`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inspection` (`equipment_id`,`tanggal`);

--
-- Indexes for table `inspection_photos`
--
ALTER TABLE `inspection_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inspection_id` (`inspection_id`);

--
-- Indexes for table `lokasi`
--
ALTER TABLE `lokasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fasilitas_id` (`fasilitas_id`);

--
-- Indexes for table `personnel`
--
ALTER TABLE `personnel`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `equipments`
--
ALTER TABLE `equipments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=198;

--
-- AUTO_INCREMENT for table `fasilitas`
--
ALTER TABLE `fasilitas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inspections_daily`
--
ALTER TABLE `inspections_daily`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=137;

--
-- AUTO_INCREMENT for table `inspection_photos`
--
ALTER TABLE `inspection_photos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lokasi`
--
ALTER TABLE `lokasi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `personnel`
--
ALTER TABLE `personnel`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `equipments`
--
ALTER TABLE `equipments`
  ADD CONSTRAINT `equipments_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `equipments_ibfk_2` FOREIGN KEY (`lokasi_id`) REFERENCES `lokasi` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inspections_daily`
--
ALTER TABLE `inspections_daily`
  ADD CONSTRAINT `inspections_daily_ibfk_1` FOREIGN KEY (`equipment_id`) REFERENCES `equipments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `inspection_photos`
--
ALTER TABLE `inspection_photos`
  ADD CONSTRAINT `inspection_photos_ibfk_1` FOREIGN KEY (`inspection_id`) REFERENCES `inspections_daily` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lokasi`
--
ALTER TABLE `lokasi`
  ADD CONSTRAINT `lokasi_ibfk_1` FOREIGN KEY (`fasilitas_id`) REFERENCES `fasilitas` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
