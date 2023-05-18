<?php

declare(strict_types=1);

namespace AmiDev\WrpDistributor\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230518110600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add uniqueClientIps to statistics';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE `statistics` ADD COLUMN `uniqueClientIps` SMALLINT UNSIGNED NOT NULL;
        ");
    }
    public function down(Schema $schema): void
    {
        $this->addSql("
            ALTER TABLE `statistics` DROP COLUMN `uniqueClientIps`;
        ");
    }
}
