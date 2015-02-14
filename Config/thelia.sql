
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- custom_delivery_slice
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `custom_delivery_slice`;

CREATE TABLE `custom_delivery_slice`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `area_id` INTEGER NOT NULL,
    `price_max` FLOAT DEFAULT 0,
    `weight_max` FLOAT DEFAULT 0,
    `price` FLOAT DEFAULT 0,
    PRIMARY KEY (`id`),
    INDEX `FI_area_id` (`area_id`),
    CONSTRAINT `fk_area_id`
        FOREIGN KEY (`area_id`)
        REFERENCES `area` (`id`)
        ON UPDATE RESTRICT
        ON DELETE CASCADE
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
