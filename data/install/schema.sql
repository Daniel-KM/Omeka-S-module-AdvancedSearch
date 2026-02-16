CREATE TABLE `search_engine` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `adapter` VARCHAR(190) NOT NULL,
    `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE `search_config` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `engine_id` INT NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `slug` VARCHAR(190) NOT NULL,
    `form_adapter` VARCHAR(190) NOT NULL,
    `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    INDEX IDX_D684063E78C9C0A (`engine_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE `search_suggester` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `engine_id` INT NOT NULL,
    `name` VARCHAR(190) NOT NULL,
    `settings` LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)',
    `created` DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    INDEX IDX_F64D915AE78C9C0A (`engine_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE `search_suggestion` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `suggester_id` INT NOT NULL,
    `text` VARCHAR(190) NOT NULL,
    FULLTEXT INDEX (`text`),
    UNIQUE INDEX (`suggester_id`, `text`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

CREATE TABLE `search_suggestion_site` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `suggestion_id` INT NOT NULL,
    `site_id` INT NOT NULL DEFAULT 0,
    `total` INT NOT NULL DEFAULT 0,
    `total_public` INT NOT NULL DEFAULT 0,
    INDEX (`site_id`),
    UNIQUE INDEX (`suggestion_id`, `site_id`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;

ALTER TABLE `search_config` ADD CONSTRAINT FK_D684063E78C9C0A FOREIGN KEY (`engine_id`) REFERENCES `search_engine` (`id`) ON DELETE CASCADE;
ALTER TABLE `search_suggester` ADD CONSTRAINT FK_F64D915AE78C9C0A FOREIGN KEY (`engine_id`) REFERENCES `search_engine` (`id`) ON DELETE CASCADE;
ALTER TABLE `search_suggestion` ADD CONSTRAINT `FK_536C3D170913F08` FOREIGN KEY (`suggester_id`) REFERENCES `search_suggester` (`id`) ON DELETE CASCADE;
ALTER TABLE `search_suggestion_site` ADD CONSTRAINT `FK_B99A0399A41BB822` FOREIGN KEY (`suggestion_id`) REFERENCES `search_suggestion` (`id`) ON DELETE CASCADE;
-- No FK on site_id because site_id = 0 means global (not a real site).
