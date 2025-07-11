-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 24, 2025 at 04:02 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */
;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */
;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */
;
/*!40101 SET NAMES utf8mb4 */
;
--
-- Database: `digital_library`
--

-- --------------------------------------------------------
--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(150) NOT NULL,
  `isbn` varchar(20) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `year_published` int DEFAULT NULL,
  `pages` int DEFAULT NULL,
  `stock` int DEFAULT '1',
  `available_stock` int DEFAULT '1',
  `description` text,
  `cover_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;
--
-- Dumping data for table `books`
--

INSERT INTO `books` (
    `id`,
    `title`,
    `author`,
    `isbn`,
    `category_id`,
    `publisher`,
    `year_published`,
    `pages`,
    `stock`,
    `available_stock`,
    `description`,
    `cover_image`,
    `created_at`,
    `updated_at`
  )
VALUES (
    1,
    'The Great Gatsby',
    'F. Scott Fitzgerald',
    '9780743273565',
    1,
    'Scribner',
    1925,
    180,
    3,
    2,
    'Classic American novel',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    2,
    'Clean Code',
    'Robert C. Martin',
    '9780132350884',
    3,
    'Prentice Hall',
    2008,
    464,
    5,
    4,
    'A Handbook of Agile Software Craftsmanship',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    3,
    'Sapiens',
    'Yuval Noah Harari',
    '9780062316097',
    2,
    'Harper',
    2014,
    443,
    4,
    4,
    'A Brief History of Humankind',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    4,
    'The Art of War',
    'Sun Tzu',
    '9781599869773',
    2,
    'Filiquarian Publishing',
    2006,
    273,
    2,
    1,
    'Ancient Chinese military treatise',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    5,
    'Introduction to Algorithms',
    'Thomas H. Cormen',
    '9780262033848',
    3,
    'MIT Press',
    2009,
    1312,
    3,
    3,
    'Comprehensive algorithms textbook',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  );
-- --------------------------------------------------------
--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;
--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (
    `id`,
    `name`,
    `description`,
    `created_at`,
    `updated_at`
  )
VALUES (
    1,
    'Fiction',
    'Novel, cerita fiksi, dan karya sastra',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    2,
    'Non-Fiction',
    'Buku faktual, biografi, sejarah',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    3,
    'Technology',
    'Buku tentang teknologi, programming, komputer',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    4,
    'Science',
    'Buku sains, matematika, fisika, kimia',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    5,
    'Education',
    'Buku pendidikan, akademik, referensi',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    6,
    'Business',
    'Buku bisnis, ekonomi, manajemen',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    7,
    'Health',
    'Buku kesehatan, kedokteran, psikologi',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    8,
    'Art & Culture',
    'Buku seni, budaya, musik, desain',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  );
-- --------------------------------------------------------
--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int NOT NULL,
  `loan_code` varchar(20) NOT NULL,
  `member_id` int NOT NULL,
  `book_id` int NOT NULL,
  `loan_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('borrowed', 'returned', 'overdue') DEFAULT 'borrowed',
  `fine_amount` decimal(10, 2) DEFAULT '0.00',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;
--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (
    `id`,
    `loan_code`,
    `member_id`,
    `book_id`,
    `loan_date`,
    `due_date`,
    `return_date`,
    `status`,
    `fine_amount`,
    `notes`,
    `created_at`,
    `updated_at`
  )
VALUES (
    1,
    'LN001',
    1,
    1,
    '2024-01-15',
    '2024-01-29',
    NULL,
    'overdue',
    '0.00',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-24 16:00:49'
  ),
  (
    2,
    'LN002',
    2,
    2,
    '2024-01-16',
    '2024-01-30',
    NULL,
    'overdue',
    '0.00',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-24 16:00:49'
  ),
  (
    3,
    'LN003',
    3,
    3,
    '2024-01-10',
    '2024-01-24',
    NULL,
    'returned',
    '0.00',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    4,
    'LN004',
    4,
    4,
    '2024-01-18',
    '2024-02-01',
    NULL,
    'overdue',
    '0.00',
    NULL,
    '2025-06-23 04:01:20',
    '2025-06-24 16:00:49'
  );
-- --------------------------------------------------------
--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int NOT NULL,
  `member_code` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male', 'female') NOT NULL,
  `member_since` date DEFAULT (curdate()),
  `status` enum('active', 'inactive', 'suspended') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;
--
-- Dumping data for table `members`
--

INSERT INTO `members` (
    `id`,
    `member_code`,
    `full_name`,
    `email`,
    `phone`,
    `address`,
    `date_of_birth`,
    `gender`,
    `member_since`,
    `status`,
    `created_at`,
    `updated_at`
  )
VALUES (
    1,
    'MBR001',
    'John Doe',
    'john.doe@email.com',
    '081234567890',
    '123 Main Street, Jakarta',
    '1995-05-15',
    'male',
    '2025-06-23',
    'active',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    2,
    'MBR002',
    'Jane Smith',
    'jane.smith@email.com',
    '081234567891',
    '456 Oak Avenue, Bandung',
    '1992-08-22',
    'female',
    '2025-06-23',
    'active',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    3,
    'MBR003',
    'Ahmad Rahman',
    'ahmad.rahman@email.com',
    '081234567892',
    '789 Pine Road, Surabaya',
    '1998-12-10',
    'male',
    '2025-06-23',
    'active',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  ),
  (
    4,
    'MBR004',
    'Sari Dewi',
    'sari.dewi@email.com',
    '081234567893',
    '321 Elm Street, Medan',
    '1994-03-18',
    'female',
    '2025-06-23',
    'active',
    '2025-06-23 04:01:20',
    '2025-06-23 04:01:20'
  );
-- --------------------------------------------------------
--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` enum('admin', 'staff') DEFAULT 'staff',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB;
--
-- Dumping data for table `users`
--

INSERT INTO `users` (
    `id`,
    `username`,
    `password`,
    `email`,
    `full_name`,
    `role`,
    `created_at`,
    `updated_at`
  )
VALUES (
    1,
    'admin',
    '123',
    'admin@digitallibrary.com',
    'Administrator',
    'admin',
    '2025-06-23 04:01:20',
    '2025-06-24 10:48:45'
  ),
  (
    2,
    'staff1',
    '123',
    'staff@digitallibrary.com',
    'Staff Library',
    'staff',
    '2025-06-23 04:01:20',
    '2025-06-24 10:48:48'
  );
--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `isbn` (`isbn`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_books_title` (`title`),
  ADD KEY `idx_books_author` (`author`);
--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
ADD PRIMARY KEY (`id`);
--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loan_code` (`loan_code`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `book_id` (`book_id`),
  ADD KEY `idx_loans_status` (`status`),
  ADD KEY `idx_loans_date` (`loan_date`);
--
-- Indexes for table `members`
--
ALTER TABLE `members`
ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_code` (`member_code`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_members_name` (`full_name`),
  ADD KEY `idx_members_code` (`member_code`);
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
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 6;
--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 9;
--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 5;
--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 5;
--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
MODIFY `id` int NOT NULL AUTO_INCREMENT,
  AUTO_INCREMENT = 3;
--
-- Constraints for dumped tables
--

--
-- Constraints for table `books`
--
ALTER TABLE `books`
ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE
SET NULL;
--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;
COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */
;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */
;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */
;