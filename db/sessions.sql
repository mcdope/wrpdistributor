CREATE TABLE `sessions`
(
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `clientIp`        VARCHAR(39)       NOT NULL,
    `clientUserAgent` TEXT              NOT NULL,
    `wrpContainerId`  VARCHAR(64)       NULL,
    `containerHost`   TEXT              NULL,
    `port`            SMALLINT UNSIGNED NULL,
    `started`         DATETIME          NOT NULL DEFAULT NOW(),
    `lastUsed`        DATETIME          NULL,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `id_UNIQUE` (`id` ASC) VISIBLE,
    UNIQUE INDEX `wrpContainerId_UNIQUE` (`wrpContainerId` ASC) VISIBLE,
    UNIQUE INDEX `containerHost_AND_port_UNIQUE` (`containerHost`(255), `port`) VISIBLE,
    UNIQUE INDEX `clientIp_AND_clientUserAgent_UNIQUE` (`clientIp`, `clientUserAgent`(255)) VISIBLE
);
