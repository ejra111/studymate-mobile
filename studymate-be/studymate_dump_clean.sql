-- StudyMate Clean Database Dump (MySQL)
-- Fokus: struktur tabel yang digunakan aplikasi + master data dasar (programs, courses, locations)

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `group_user`;
DROP TABLE IF EXISTS `course_user`;
DROP TABLE IF EXISTS `friends`;
DROP TABLE IF EXISTS `private_messages`;
DROP TABLE IF EXISTS `group_messages`;
DROP TABLE IF EXISTS `activities`;
DROP TABLE IF EXISTS `study_groups`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `locations`;
DROP TABLE IF EXISTS `courses`;
DROP TABLE IF EXISTS `programs`;

-- ---------------------------------------------------------
-- programs
-- ---------------------------------------------------------
CREATE TABLE `programs` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `faculty` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- courses
-- ---------------------------------------------------------
CREATE TABLE `courses` (
  `id` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `program_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `courses_program_id_foreign` (`program_id`),
  CONSTRAINT `courses_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- locations
-- ---------------------------------------------------------
CREATE TABLE `locations` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `map_hint` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- users
-- ---------------------------------------------------------
CREATE TABLE `users` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `university` varchar(255) DEFAULT NULL,
  `program_name` varchar(255) DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'student',
  `student_id` varchar(255) DEFAULT NULL,
  `program_id` varchar(255) DEFAULT NULL,
  `semester` int(11) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `interests` longtext DEFAULT NULL,
  `availability` longtext DEFAULT NULL,
  `avatar_color` varchar(255) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_program_id_foreign` (`program_id`),
  CONSTRAINT `users_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `programs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- study_groups
-- ---------------------------------------------------------
CREATE TABLE `study_groups` (
  `id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `schedule` varchar(255) NOT NULL,
  `course_id` varchar(255) NOT NULL,
  `location_id` varchar(255) NOT NULL,
  `capacity` int(11) NOT NULL,
  `owner_id` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `study_groups_course_id_foreign` (`course_id`),
  KEY `study_groups_location_id_foreign` (`location_id`),
  KEY `study_groups_owner_id_foreign` (`owner_id`),
  CONSTRAINT `study_groups_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `study_groups_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `study_groups_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- activities
-- ---------------------------------------------------------
CREATE TABLE `activities` (
  `id` varchar(255) NOT NULL,
  `actor_id` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- course_user (pivot)
-- ---------------------------------------------------------
CREATE TABLE `course_user` (
  `course_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  PRIMARY KEY (`course_id`,`user_id`),
  KEY `course_user_user_id_foreign` (`user_id`),
  CONSTRAINT `course_user_course_id_foreign` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `course_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- group_user (pivot)
-- ---------------------------------------------------------
CREATE TABLE `group_user` (
  `study_group_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  PRIMARY KEY (`study_group_id`,`user_id`),
  KEY `group_user_user_id_foreign` (`user_id`),
  CONSTRAINT `group_user_study_group_id_foreign` FOREIGN KEY (`study_group_id`) REFERENCES `study_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- private_messages
-- ---------------------------------------------------------
CREATE TABLE `private_messages` (
  `id` varchar(255) NOT NULL,
  `sender_id` varchar(255) NOT NULL,
  `receiver_id` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `private_messages_sender_receiver_idx` (`sender_id`,`receiver_id`),
  CONSTRAINT `private_messages_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `private_messages_receiver_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- group_messages (chat)
-- ---------------------------------------------------------
CREATE TABLE `group_messages` (
  `id` varchar(255) NOT NULL,
  `study_group_id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `group_messages_group_time_idx` (`study_group_id`, `created_at`),
  KEY `group_messages_user_id_idx` (`user_id`),
  CONSTRAINT `group_messages_group_fk` FOREIGN KEY (`study_group_id`) REFERENCES `study_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `group_messages_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- friends
-- ---------------------------------------------------------
CREATE TABLE `friends` (
  `id` varchar(255) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `friend_id` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `friends_user_friend_unique` (`user_id`,`friend_id`),
  KEY `friends_friend_id_foreign` (`friend_id`),
  CONSTRAINT `friends_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `friends_friend_id_foreign` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- study_notifications
-- ---------------------------------------------------------
DROP TABLE IF EXISTS `study_notifications`;
CREATE TABLE `study_notifications` (
  `id` varchar(255) NOT NULL,
  `sender_id` varchar(255) NOT NULL,
  `receiver_id` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `data` json DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `study_notifications_receiver_read` (`receiver_id`, `read_at`),
  CONSTRAINT `study_notifications_sender_fk` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `study_notifications_receiver_fk` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Master data dasar (tanpa akun demo, tanpa grup demo)
-- ---------------------------------------------------------
INSERT INTO `programs` (`id`, `name`, `faculty`, `created_at`, `updated_at`) VALUES
('prog-informatika', 'S1 Informatika', 'Fakultas Informatika', NULL, NULL),
('prog-si', 'S1 Sistem Informasi', 'Fakultas Rekayasa Industri', NULL, NULL),
('prog-dkv', 'S1 Desain Komunikasi Visual', 'Fakultas Industri Kreatif', NULL, NULL);

INSERT INTO `courses` (`id`, `code`, `name`, `program_id`, `created_at`, `updated_at`) VALUES
('course-pbo', 'IF2201', 'Pemrograman Berorientasi Objek', 'prog-informatika', NULL, NULL),
('course-basdat', 'IF2205', 'Basis Data', 'prog-informatika', NULL, NULL),
('course-web', 'IF2230', 'Pemrograman Web', 'prog-informatika', NULL, NULL),
('course-ai', 'IF3302', 'Kecerdasan Buatan', 'prog-informatika', NULL, NULL),
('course-uiux', 'DKV2102', 'Dasar UI/UX', 'prog-dkv', NULL, NULL);

INSERT INTO `locations` (`id`, `name`, `address`, `map_hint`, `created_at`, `updated_at`) VALUES
('loc-library', 'Perpustakaan Pusat', 'Gedung Learning Center Lt. 2', 'Tenang, banyak colokan, cocok untuk diskusi kecil.', NULL, NULL),
('loc-cowork', 'Co-Learning Space FIT', 'Gedung FIT Lt. 5', 'Ruang modern, ideal untuk kerja tim dan sprint tugas.', NULL, NULL),
('loc-canteen', 'Study Corner Kantin Timur', 'Area terbuka dekat kantin timur', 'Santai, ramai, cocok untuk belajar informal.', NULL, NULL);

-- ---------------------------------------------------------
-- Akun Demo & Friendlist (Opsional)
-- ---------------------------------------------------------
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `university`, `program_name`, `semester`, `avatar_color`, `created_at`, `updated_at`) VALUES
('user-1', 'Raffa Maulana', 'raffa@kampus.ac.id', '$2y$12$34gVH++bPBudMma8jMbUbHuAzSUUWoRxUJLgQsaITMU=', 'student', 'Telkom University', 'S1 INFORMATIKA', 4, '#6366f1', NOW(), NOW()),
('user-2', 'Andi Wijaya', 'andi@kampus.ac.id', '$2y$12$34gVH++bPBudMma8jMbUbHuAzSUUWoRxUJLgQsaITMU=', 'student', 'Telkom University', 'S1 INFORMATIKA', 4, '#10b981', NOW(), NOW());

INSERT INTO `friends` (`id`, `user_id`, `friend_id`, `created_at`, `updated_at`) VALUES
('friendship-1', 'user-1', 'user-2', NOW(), NOW()),
('friendship-2', 'user-2', 'user-1', NOW(), NOW());

-- ---------------------------------------------------------
-- Tambahan katalog akademik untuk dropdown Profil Akademik
-- ---------------------------------------------------------
INSERT IGNORE INTO `programs` (`id`, `name`, `faculty`, `created_at`, `updated_at`) VALUES
('prog-data-science', 'S1 Sains Data', 'Fakultas Informatika', NULL, NULL),
('prog-teknik-elektro', 'S1 Teknik Elektro', 'Fakultas Teknik Elektro', NULL, NULL),
('prog-manajemen', 'S1 Manajemen', 'Fakultas Ekonomi dan Bisnis', NULL, NULL),
('prog-akuntansi', 'S1 Akuntansi', 'Fakultas Ekonomi dan Bisnis', NULL, NULL),
('prog-komunikasi', 'S1 Ilmu Komunikasi', 'Fakultas Komunikasi dan Bisnis', NULL, NULL);

INSERT IGNORE INTO `courses` (`id`, `code`, `name`, `program_id`, `created_at`, `updated_at`) VALUES
('course-if-algo', 'IF1101', 'Algoritma dan Pemrograman', 'prog-informatika', NULL, NULL),
('course-if-struktur-data', 'IF1201', 'Struktur Data', 'prog-informatika', NULL, NULL),
('course-if-rpl', 'IF2202', 'Rekayasa Perangkat Lunak', 'prog-informatika', NULL, NULL),
('course-if-jarkom', 'IF2203', 'Jaringan Komputer', 'prog-informatika', NULL, NULL),
('course-if-os', 'IF2204', 'Sistem Operasi', 'prog-informatika', NULL, NULL),
('course-if-mobile', 'IF2301', 'Pemrograman Mobile', 'prog-informatika', NULL, NULL),
('course-if-security', 'IF3301', 'Keamanan Informasi', 'prog-informatika', NULL, NULL),
('course-if-ml', 'IF3303', 'Machine Learning', 'prog-informatika', NULL, NULL),
('course-if-cloud', 'IF3304', 'Cloud Computing', 'prog-informatika', NULL, NULL),
('course-if-data-mining', 'IF3305', 'Data Mining', 'prog-informatika', NULL, NULL),
('course-if-ta', 'IF4099', 'Tugas Akhir / Skripsi', 'prog-informatika', NULL, NULL),
('course-si-manajemen-proses', 'SI1101', 'Manajemen Proses Bisnis', 'prog-si', NULL, NULL),
('course-si-analisis', 'SI1201', 'Analisis dan Perancangan Sistem Informasi', 'prog-si', NULL, NULL),
('course-si-basdat', 'SI2101', 'Basis Data Sistem Informasi', 'prog-si', NULL, NULL),
('course-si-erp', 'SI2201', 'Enterprise Resource Planning', 'prog-si', NULL, NULL),
('course-si-bi', 'SI2301', 'Business Intelligence', 'prog-si', NULL, NULL),
('course-si-pm', 'SI3101', 'Manajemen Proyek Sistem Informasi', 'prog-si', NULL, NULL),
('course-si-audit', 'SI3201', 'Audit Sistem Informasi', 'prog-si', NULL, NULL),
('course-si-uiux', 'SI3301', 'User Experience dan Desain Produk Digital', 'prog-si', NULL, NULL),
('course-ds-stat', 'DS1101', 'Statistika Dasar untuk Sains Data', 'prog-data-science', NULL, NULL),
('course-ds-python', 'DS1201', 'Python untuk Analisis Data', 'prog-data-science', NULL, NULL),
('course-ds-visual', 'DS2101', 'Visualisasi Data', 'prog-data-science', NULL, NULL),
('course-ds-bigdata', 'DS2201', 'Big Data Analytics', 'prog-data-science', NULL, NULL),
('course-ds-ml', 'DS3101', 'Machine Learning untuk Sains Data', 'prog-data-science', NULL, NULL),
('course-ds-etl', 'DS3201', 'Data Warehouse dan ETL', 'prog-data-science', NULL, NULL),
('course-dkv-visual-basic', 'DKV1101', 'Dasar Desain Visual', 'prog-dkv', NULL, NULL),
('course-dkv-typo', 'DKV1201', 'Tipografi', 'prog-dkv', NULL, NULL),
('course-dkv-branding', 'DKV2201', 'Branding dan Identitas Visual', 'prog-dkv', NULL, NULL),
('course-dkv-motion', 'DKV3101', 'Motion Graphic', 'prog-dkv', NULL, NULL),
('course-dkv-ads', 'DKV3201', 'Creative Advertising', 'prog-dkv', NULL, NULL),
('course-te-rangkaian', 'TE1101', 'Rangkaian Listrik', 'prog-teknik-elektro', NULL, NULL),
('course-te-elektronika', 'TE1201', 'Elektronika Dasar', 'prog-teknik-elektro', NULL, NULL),
('course-te-sinyal', 'TE2101', 'Sinyal dan Sistem', 'prog-teknik-elektro', NULL, NULL),
('course-te-iot', 'TE3101', 'Internet of Things', 'prog-teknik-elektro', NULL, NULL),
('course-mj-marketing', 'MJ1101', 'Prinsip Manajemen dan Pemasaran', 'prog-manajemen', NULL, NULL),
('course-mj-finance', 'MJ2101', 'Manajemen Keuangan', 'prog-manajemen', NULL, NULL),
('course-mj-ops', 'MJ2201', 'Manajemen Operasi', 'prog-manajemen', NULL, NULL),
('course-mj-startup', 'MJ3101', 'Kewirausahaan dan Startup Digital', 'prog-manajemen', NULL, NULL),
('course-ak-basic', 'AK1101', 'Akuntansi Dasar', 'prog-akuntansi', NULL, NULL),
('course-ak-cost', 'AK2101', 'Akuntansi Biaya', 'prog-akuntansi', NULL, NULL),
('course-ak-audit', 'AK3101', 'Audit dan Assurance', 'prog-akuntansi', NULL, NULL),
('course-ik-public-speaking', 'IK1101', 'Public Speaking', 'prog-komunikasi', NULL, NULL),
('course-ik-pr', 'IK2101', 'Public Relations', 'prog-komunikasi', NULL, NULL),
('course-ik-digital', 'IK3101', 'Komunikasi Digital dan Media Sosial', 'prog-komunikasi', NULL, NULL);

SET FOREIGN_KEY_CHECKS = 1;