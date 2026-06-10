-- تعطيل فحص العلاقات مؤقتاً لتجنب أخطاء الترتيب أثناء إنشاء الجداول
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. جدول المستخدمين (Users Table)
-- --------------------------------------------------------
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('user', 'admin') DEFAULT 'user',
  `profile_picture` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Active', 'Suspended') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. جدول التصنيفات (Categories Table)
-- --------------------------------------------------------
CREATE TABLE `categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `icon` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. جدول الأماكن (Places Table)
-- --------------------------------------------------------
CREATE TABLE `places` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `Maps_url` TEXT DEFAULT NULL,
  `average_rating` DECIMAL(3,2) DEFAULT 0.00,
  `reviews_count` INT UNSIGNED DEFAULT 0,
  `price_level` TINYINT UNSIGNED CHECK (`price_level` BETWEEN 1 AND 5),
  `status` ENUM('Open', 'Temporarily Closed', 'Closed') DEFAULT 'Open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT,
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. جدول صور الأماكن (Place Images Table)
-- --------------------------------------------------------
CREATE TABLE `place_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `place_id` BIGINT UNSIGNED NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  `is_primary` BOOLEAN DEFAULT FALSE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`place_id`) REFERENCES `places`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. جدول جهات الاتصال (Place Contacts Table)
-- --------------------------------------------------------
CREATE TABLE `place_contacts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `place_id` BIGINT UNSIGNED NOT NULL,
  `platform` ENUM('phone', 'whatsapp', 'instagram', 'website') NOT NULL,
  `contact_value` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`place_id`) REFERENCES `places`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. جدول أوقات العمل (Working Hours Table)
-- --------------------------------------------------------
CREATE TABLE `working_hours` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `place_id` BIGINT UNSIGNED NOT NULL,
  `day_of_week` TINYINT UNSIGNED NOT NULL CHECK (`day_of_week` BETWEEN 0 AND 6),
  `open_time` TIME DEFAULT NULL,
  `close_time` TIME DEFAULT NULL,
  `is_closed` BOOLEAN DEFAULT FALSE,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`place_id`) REFERENCES `places`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. جدول المرافق (Amenities Table)
-- --------------------------------------------------------
CREATE TABLE `amenities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `icon` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. جدول الربط بين الأماكن والمرافق (Place Amenities Table)
-- --------------------------------------------------------
CREATE TABLE `place_amenities` (
  `place_id` BIGINT UNSIGNED NOT NULL,
  `amenity_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`place_id`, `amenity_id`),
  FOREIGN KEY (`place_id`) REFERENCES `places`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`amenity_id`) REFERENCES `amenities`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. جدول المفضلة (Favorites Table)
-- --------------------------------------------------------
CREATE TABLE `favorites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `place_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_place` (`user_id`, `place_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`place_id`) REFERENCES `places`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 11. جدول التقييمات والمراجعات (Reviews Table)
-- --------------------------------------------------------
CREATE TABLE `reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `place_id` BIGINT UNSIGNED NOT NULL,
  `rating` TINYINT UNSIGNED NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `comment` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`place_id`) REFERENCES `places`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE places ADD COLUMN lat DECIMAL(10, 8);
ALTER TABLE places ADD COLUMN lng DECIMAL(11, 8);

-- إعادة تفعيل فحص العلاقات
SET FOREIGN_KEY_CHECKS = 1;