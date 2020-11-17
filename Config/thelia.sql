
# This is a fix for InnoDB in MySQL >= 4.1.x
# It "suspends judgement" for fkey relationships until are tables are set.
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- dpdlabel_labels
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `dpdlabel_labels`;

CREATE TABLE `dpdlabel_labels`
(
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `order_id` INTEGER NOT NULL,
    `label_number` VARCHAR(255),
    `created_at` DATETIME,
    `updated_at` DATETIME,
    PRIMARY KEY (`id`),
    INDEX `FI_dpdlabel_labels_order_id` (`order_id`),
    CONSTRAINT `fk_dpdlabel_labels_order_id`
        FOREIGN KEY (`order_id`)
        REFERENCES `order` (`id`)
        ON UPDATE RESTRICT
        ON DELETE RESTRICT
) ENGINE=InnoDB;

# This restores the fkey checks, after having unset them earlier
SET FOREIGN_KEY_CHECKS = 1;
