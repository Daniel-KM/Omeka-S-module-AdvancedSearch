CREATE TABLE `search_index` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `adapter` VARCHAR(190) NOT NULL,
    `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
    `created` DATETIME NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE `search_config` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `index_id` INT NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `path` VARCHAR(190) NOT NULL,
    `form_adapter` VARCHAR(190) NOT NULL,
    `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
    `created` DATETIME NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    INDEX IDX_4F10A34984337261 (`index_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE `search_config` ADD CONSTRAINT FK_4F10A34984337261 FOREIGN KEY (`index_id`) REFERENCES `search_index` (`id`) ON DELETE CASCADE;
