CREATE TABLE `statistics`
(
    `id`                       INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `activeSessions`           SMALLINT UNSIGNED NOT NULL,
    `containersRunningTotal`   SMALLINT UNSIGNED NOT NULL,
    `remainingContainersTotal` SMALLINT UNSIGNED NOT NULL,
    `containerHostsAvailable`  SMALLINT UNSIGNED NOT NULL,
    `containersInUsePerHost`   TEXT NOT NULL,
    `timeOfCapture`            DATETIME          NOT NULL DEFAULT NOW(),
    PRIMARY KEY (`id`),
    INDEX `timeOfCapture` (`timeOfCapture` DESC) VISIBLE
);
