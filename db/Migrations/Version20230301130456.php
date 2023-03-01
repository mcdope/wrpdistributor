<?php

declare(strict_types=1);

namespace AmiDev\WrpDistributor\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230301130456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove token field from sessions table';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('sessions');
        $table->dropColumn('token');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE sessions ADD token VARCHAR(64) NULL;
        ');
    }
}
