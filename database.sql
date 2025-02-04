-- 创建分类表
CREATE TABLE IF NOT EXISTS `categories` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `description` text,
    `sort_order` int(11) DEFAULT '0',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建链接表
CREATE TABLE IF NOT EXISTS `links` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `category_id` int(11) NOT NULL,
    `title` varchar(255) NOT NULL,
    `url` varchar(1000) NOT NULL,
    `icon` varchar(1000) DEFAULT NULL,
    `description` text,
    `screenshots` text,
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `category_id` (`category_id`),
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建用户表
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `status` tinyint(1) DEFAULT '1',
    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建访问统计表
CREATE TABLE IF NOT EXISTS `visits` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `visit_date` date NOT NULL,
    `visit_count` int(11) DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `visit_date` (`visit_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 删除旧表（如果存在）
DROP TABLE IF EXISTS `smtp_config`;

-- 创建新的SMTP配置表
CREATE TABLE IF NOT EXISTS `smtp_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` int(11) NOT NULL,
  `smtp_user` varchar(255) NOT NULL,
  `smtp_pass` varchar(255) NOT NULL,
  `smtp_from` varchar(255) NOT NULL,
  `smtp_from_name` varchar(255) DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 添加 smtp_from_name 字段
ALTER TABLE `smtp_config` 
ADD COLUMN `smtp_from_name` varchar(255) DEFAULT '' AFTER `smtp_from`;

-- 插入一个默认管理员用户（密码为 'admin'）
INSERT INTO `users` (`username`, `email`, `password`, `status`) 
VALUES ('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1)
ON DUPLICATE KEY UPDATE `id`=`id`;