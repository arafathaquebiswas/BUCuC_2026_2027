CREATE TABLE IF NOT EXISTS `venuInfo`(
    `venue_id` INT AUTO_INCREMENT PRIMARY KEY,
    `venue_name` VARCHAR(255) NOT NULL,
    `venue_location` VARCHAR(255) NOT NULL,
    `venue_dateTime` DATETIME NOT NULL,
    `venue_startingTime` VARCHAR(10) NOT NULL,
    `venue_endingTime` VARCHAR(10) NOT NULL,
    `venu_ampm` VARCHAR(2) NOT NULL DEFAULT 'PM'
);
