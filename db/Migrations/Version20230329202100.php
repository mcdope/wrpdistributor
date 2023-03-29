<?php

declare(strict_types=1);

namespace AmiDev\WrpDistributor\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230329202100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE IF NOT EXISTS `sessions`
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
        ");

        $this->addSql("
            CREATE TABLE IF NOT EXISTS `statistics`
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
        ");
    }
    public function down(Schema $schema): void
    {
    }
}
