<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260101000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add intStock and intPricePence columns to tblProductData to capture supplier stock level and price';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE tblProductData
                ADD COLUMN intStock INT NOT NULL DEFAULT 0 AFTER strProductCode,
                ADD COLUMN intPricePence INT NOT NULL DEFAULT 0 AFTER intStock
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE tblProductData
                DROP COLUMN intPricePence,
                DROP COLUMN intStock
        ');
    }
}
