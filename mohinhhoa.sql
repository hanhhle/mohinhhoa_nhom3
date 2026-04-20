-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: localhost
-- Thời gian đã tạo: Th4 20, 2026 lúc 05:08 AM
-- Phiên bản máy phục vụ: 10.4.28-MariaDB
-- Phiên bản PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `mohinhhoa`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Appointments`
--

CREATE TABLE `Appointments` (
  `appointment_id` int(11) NOT NULL,
  `appointment_code` varchar(20) DEFAULT NULL,
  `numerical_order` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `patient_symptoms_note` text DEFAULT NULL,
  `status` enum('Scheduled','In Progress','Completed','Cancelled') DEFAULT 'Scheduled',
  `fee_status` enum('Paid','Unpaid') DEFAULT 'Unpaid',
  `fee_amount` decimal(10,2) DEFAULT NULL,
  `payment_method` enum('Tiền mặt','Chuyển khoản') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `patient_notes` text DEFAULT NULL,
  `ai_prediction_label` varchar(50) DEFAULT NULL,
  `ai_confidence_score` decimal(5,2) DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Appointments`
--

INSERT INTO `Appointments` (`appointment_id`, `appointment_code`, `numerical_order`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `patient_symptoms_note`, `status`, `fee_status`, `fee_amount`, `payment_method`, `created_at`, `patient_notes`, `ai_prediction_label`, `ai_confidence_score`, `treatment_plan`) VALUES
(1, NULL, NULL, 5, 2, '2026-04-14', '10:30:00', NULL, 'Completed', 'Paid', NULL, 'Chuyển khoản', '2026-04-14 09:23:16', NULL, NULL, NULL, NULL),
(2, NULL, NULL, 9, 8, '2026-04-16', '09:00:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-14 09:34:03', NULL, NULL, NULL, NULL),
(4, NULL, NULL, 12, 2, '2026-04-16', '13:30:00', NULL, 'Completed', 'Paid', NULL, 'Chuyển khoản', '2026-04-14 10:09:45', 'AI Result: Negative (94.9%)\nDoctor Conclusion: The AI result is normal. No signs of Pneumonia detected. Continue home care.', NULL, NULL, NULL),
(5, NULL, NULL, 12, 2, '2026-04-17', '15:00:00', '', 'Completed', 'Paid', NULL, 'Chuyển khoản', '2026-04-15 03:44:50', 'normal', NULL, NULL, 'work out more'),
(7, NULL, NULL, 12, 6, '2026-04-18', '09:00:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-17 07:11:20', '', NULL, NULL, NULL),
(8, NULL, NULL, 12, 2, '2026-04-17', '10:30:00', NULL, 'Completed', 'Paid', NULL, 'Chuyển khoản', '2026-04-17 07:21:25', '', NULL, NULL, NULL),
(9, NULL, NULL, 12, 2, '2026-04-20', '09:00:00', NULL, 'Cancelled', 'Unpaid', NULL, 'Tiền mặt', '2026-04-17 07:44:09', '', NULL, NULL, NULL),
(10, NULL, NULL, 13, 7, '2026-04-17', '10:30:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-17 13:25:37', NULL, NULL, NULL, NULL),
(11, NULL, NULL, 5, 8, '2026-04-20', '09:00:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-18 10:21:28', 'cough', NULL, NULL, NULL),
(12, NULL, NULL, 5, 7, '2026-04-18', '09:00:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-18 14:30:14', '', NULL, NULL, NULL),
(13, NULL, NULL, 5, 6, '2026-04-18', '09:00:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-18 14:30:24', '', NULL, NULL, NULL),
(14, NULL, NULL, 5, 7, '2026-04-18', '10:30:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-18 14:30:33', '', NULL, NULL, NULL),
(16, NULL, NULL, 9, 8, '2026-04-20', '10:30:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-18 15:07:25', NULL, NULL, NULL, NULL),
(17, NULL, NULL, 9, 7, '2026-04-21', '15:00:00', NULL, 'Cancelled', 'Unpaid', NULL, NULL, '2026-04-18 15:31:24', '', NULL, NULL, NULL),
(18, NULL, NULL, 12, 2, '2026-04-19', '09:00:00', '', 'Completed', 'Paid', NULL, 'Chuyển khoản', '2026-04-18 16:17:01', 'Doctor Conclusion: Normal lung scan. No signs of pneumonia detected by AI.', 'Negative', 94.70, 'normal\r\nexercise more\r\ndrink more hot water'),
(19, NULL, NULL, 12, 6, '2026-04-21', '09:00:00', NULL, 'Cancelled', 'Paid', NULL, NULL, '2026-04-19 13:38:38', '', NULL, NULL, NULL),
(20, NULL, NULL, 12, 6, '2026-04-19', '10:30:00', NULL, 'Cancelled', 'Paid', NULL, 'Chuyển khoản', '2026-04-19 13:50:35', '', NULL, NULL, NULL),
(21, NULL, NULL, 12, 2, '2026-04-20', '10:30:00', NULL, 'In Progress', 'Paid', NULL, NULL, '2026-04-20 03:00:22', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Doctor_Profiles`
--

CREATE TABLE `Doctor_Profiles` (
  `doctor_id` int(11) NOT NULL,
  `speciality` varchar(100) DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT NULL,
  `clinic_address` text DEFAULT NULL,
  `room_details` text DEFAULT NULL,
  `undergraduate_edu` text DEFAULT NULL,
  `medical_edu` text DEFAULT NULL,
  `training` text DEFAULT NULL,
  `affiliations` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 5.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Doctor_Profiles`
--

INSERT INTO `Doctor_Profiles` (`doctor_id`, `speciality`, `consultation_fee`, `clinic_address`, `room_details`, `undergraduate_edu`, `medical_edu`, `training`, `affiliations`, `bio`, `rating`) VALUES
(2, 'Pulmonology & Critical Care', 350000.00, NULL, NULL, 'University of Notre Dame, South Bend, IN', 'Harvard Medical School, Boston, MA', 'Internal Medicine Residency: Massachusetts General Hospital', NULL, 'Dr. Hoang Anh is a Pulmonologist and Critical Care specialist with extensive expertise. She completed his medical degree at Harvard Medical School, followed by rigorous internal medicine training at Massachusetts General Hospital. To further hone his specialized skills, she completed elite fellowships in Pulmonary Medicine at the Mayo Clinic and Interventional Pulmonology at MD Anderson Cancer Center.', 5.0),
(6, 'Hô hấp & Hen suyễn', 300000.00, NULL, NULL, 'Đại học Y Hà Nội', 'Đại học Y Hà Nội', 'Nội trú Bệnh viện Bạch Mai', NULL, 'Bác sĩ Trần Văn A có hơn 10 năm kinh nghiệm trong việc điều trị các bệnh lý hen suyễn và viêm phế quản mãn tính (COPD).', 4.8),
(7, 'Phẫu thuật Lồng ngực', 500000.00, NULL, NULL, 'Đại học Y Dược TP.HCM', 'Đại học Y Dược TP.HCM', 'Tu nghiệp Phẫu thuật xâm lấn tối thiểu tại Singapore', NULL, 'Bác sĩ Nguyễn Thị B chuyên phẫu thuật nội soi lồng ngực, điều trị ung thư phổi và các bệnh lý màng phổi phức tạp.', 5.0),
(8, 'Phục hồi chức năng Hô hấp', 250000.00, NULL, NULL, 'Đại học Y Dược Huế', 'Đại học Y Dược Huế', 'Chứng chỉ Trị liệu Hô hấp Quốc tế', NULL, 'Bác sĩ Phạm Văn C tập trung vào các phương pháp vật lý trị liệu hô hấp, hỗ trợ bệnh nhân cai máy thở và phục hồi sau phẫu thuật.', 4.9);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Expert_Comments`
--

CREATE TABLE `Expert_Comments` (
  `comment_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `comment_content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Expert_Comments`
--

INSERT INTO `Expert_Comments` (`comment_id`, `appointment_id`, `doctor_id`, `comment_content`, `created_at`) VALUES
(1, 5, 2, 'this case no probs', '2026-04-16 15:34:05');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Medical_History`
--

CREATE TABLE `Medical_History` (
  `history_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `condition_name` text NOT NULL,
  `type` enum('Disease','Surgery') DEFAULT NULL,
  `date_recorded` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Medical_History`
--

INSERT INTO `Medical_History` (`history_id`, `patient_id`, `condition_name`, `type`, `date_recorded`) VALUES
(1, 12, 'Penicillin allergies', 'Disease', '2026-04-17'),
(2, 12, 'liver surgery', 'Surgery', '2026-04-17');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Messages`
--

CREATE TABLE `Messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `receiver_id` int(11) DEFAULT NULL,
  `message_content` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Messages`
--

INSERT INTO `Messages` (`message_id`, `sender_id`, `receiver_id`, `message_content`, `sent_at`, `is_read`) VALUES
(8, 12, 1, 'hello', '2026-04-17 07:15:59', 1),
(9, 12, 2, 'hello', '2026-04-17 07:28:26', 0),
(10, 1, 12, 'hello bạn cần gì', '2026-04-17 08:06:54', 0),
(11, 1, 5, 'chào cương, bạn chưa thanh toán tiền', '2026-04-17 08:25:05', 0);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Patient_Profiles`
--

CREATE TABLE `Patient_Profiles` (
  `patient_id` int(11) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `identity_card_number` varchar(20) DEFAULT NULL,
  `health_insurance_code` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Patient_Profiles`
--

INSERT INTO `Patient_Profiles` (`patient_id`, `date_of_birth`, `gender`, `blood_group`, `phone_number`, `address`, `identity_card_number`, `health_insurance_code`) VALUES
(5, '2001-01-01', 'Female', 'A+', '0123451234', 'Hai Ba Trung, Ha Noi', '00112341234', NULL),
(9, '2004-11-01', 'Female', 'O+', '0989976023', 'Hung Yen 2', '001123456789', NULL),
(12, '2005-12-07', 'Female', 'O+', '0382538619', 'Tu Son, Bac Ninh', '001123456781', NULL),
(13, '2004-02-11', 'Male', NULL, '0123456780', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Treatment_Plans`
--

CREATE TABLE `Treatment_Plans` (
  `plan_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `diagnose_note` text DEFAULT NULL,
  `clinical_notes` text DEFAULT NULL,
  `treatment_steps` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `Users`
--

CREATE TABLE `Users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('Admin','Doctor','Patient') NOT NULL,
  `avatar_url` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `Users`
--

INSERT INTO `Users` (`user_id`, `email`, `password_hash`, `full_name`, `role`, `avatar_url`, `is_active`, `created_at`) VALUES
(1, 'admin@pneumocare.com', '$2y$10$JRTjB85n16qhDJyDJaXOyeRExaop.XpR1xIQVQFFnMR7eFmrh4Rtq', 'Nguyen Minh Tien', 'Admin', NULL, 1, '2026-04-14 09:09:38'),
(2, 'hoanganh@pneumocare.com', '$2y$10$JRTjB85n16qhDJyDJaXOyeRExaop.XpR1xIQVQFFnMR7eFmrh4Rtq', 'Lê Hoàng Anh', 'Doctor', NULL, 1, '2026-04-14 09:09:38'),
(5, 'nguyenvancuong@gmail.com', '$2y$10$RA4Gydcrws4WoZWwKjlB1.un71ICsfg79znAfZpPO6bpEpvlHAlE2', 'Van Cuong Nguyen', 'Patient', NULL, 2, '2026-04-14 09:22:26'),
(6, 'tranvana@pneumocare.com', '$2y$10$JRTjB85n16qhDJyDJaXOyeRExaop.XpR1xIQVQFFnMR7eFmrh4Rtq', 'Trần Văn A', 'Doctor', NULL, 1, '2026-04-14 09:26:37'),
(7, 'nguyenthib@pneumocare.com', '$2y$10$JRTjB85n16qhDJyDJaXOyeRExaop.XpR1xIQVQFFnMR7eFmrh4Rtq', 'Nguyễn Thị B', 'Doctor', NULL, 1, '2026-04-14 09:26:37'),
(8, 'phamvanc@pneumocare.com', '$2y$10$JRTjB85n16qhDJyDJaXOyeRExaop.XpR1xIQVQFFnMR7eFmrh4Rtq', 'Phạm Văn C', 'Doctor', NULL, 1, '2026-04-14 09:26:37'),
(9, 'pthuy11@gmail.com', '$2y$10$YHR69hynSQz8vtmiy/1cdeidLamFX8TOBbkqHrrHeMcCDSI0s3U6u', 'Thi Phuong Thuy Nguyen', 'Patient', NULL, 1, '2026-04-14 09:31:52'),
(12, 'dohuonggiang@gmail.com', '$2y$10$SNXVp1/6oFacAzW38prNeesHTb4sFt3msHcVYSTx3ruOAQQH6M4vG', 'Huong Giang Do', 'Patient', NULL, 1, '2026-04-14 10:09:06'),
(13, 'ngoduc@gmail.com', '$2y$10$p6BpL4SGAYt/ugBMkQ22p.SoVAdRkF/vT5uR9U.wo/wwc7rfG0qf6', 'Ngo Xuan Duc', 'Patient', NULL, 1, '2026-04-17 13:25:37');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `Appointments`
--
ALTER TABLE `Appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD UNIQUE KEY `appointment_code` (`appointment_code`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Chỉ mục cho bảng `Doctor_Profiles`
--
ALTER TABLE `Doctor_Profiles`
  ADD PRIMARY KEY (`doctor_id`);

--
-- Chỉ mục cho bảng `Expert_Comments`
--
ALTER TABLE `Expert_Comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Chỉ mục cho bảng `Medical_History`
--
ALTER TABLE `Medical_History`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Chỉ mục cho bảng `Messages`
--
ALTER TABLE `Messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Chỉ mục cho bảng `Patient_Profiles`
--
ALTER TABLE `Patient_Profiles`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `identity_card_number` (`identity_card_number`);

--
-- Chỉ mục cho bảng `Treatment_Plans`
--
ALTER TABLE `Treatment_Plans`
  ADD PRIMARY KEY (`plan_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Chỉ mục cho bảng `Users`
--
ALTER TABLE `Users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `Appointments`
--
ALTER TABLE `Appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT cho bảng `Expert_Comments`
--
ALTER TABLE `Expert_Comments`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `Medical_History`
--
ALTER TABLE `Medical_History`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `Messages`
--
ALTER TABLE `Messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `Treatment_Plans`
--
ALTER TABLE `Treatment_Plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `Users`
--
ALTER TABLE `Users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `Appointments`
--
ALTER TABLE `Appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `Patient_Profiles` (`patient_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `Doctor_Profiles` (`doctor_id`);

--
-- Các ràng buộc cho bảng `Doctor_Profiles`
--
ALTER TABLE `Doctor_Profiles`
  ADD CONSTRAINT `doctor_profiles_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `Expert_Comments`
--
ALTER TABLE `Expert_Comments`
  ADD CONSTRAINT `expert_comments_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `Appointments` (`appointment_id`),
  ADD CONSTRAINT `expert_comments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `Doctor_Profiles` (`doctor_id`);

--
-- Các ràng buộc cho bảng `Medical_History`
--
ALTER TABLE `Medical_History`
  ADD CONSTRAINT `medical_history_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `Patient_Profiles` (`patient_id`);

--
-- Các ràng buộc cho bảng `Messages`
--
ALTER TABLE `Messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `Users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `Users` (`user_id`);

--
-- Các ràng buộc cho bảng `Patient_Profiles`
--
ALTER TABLE `Patient_Profiles`
  ADD CONSTRAINT `patient_profiles_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `Users` (`user_id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `Treatment_Plans`
--
ALTER TABLE `Treatment_Plans`
  ADD CONSTRAINT `treatment_plans_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `Appointments` (`appointment_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
