<?php declare(strict_types=1);

namespace TfcSwOzi\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1622163015CreateCarTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1622163015;
    }

    public function update(Connection $connection): void
    {
        if (!$this->tableExists($connection, 'tfc_car')) {
            $connection->executeUpdate('
            CREATE TABLE `tfc_car` (
                `id` BINARY(16) NOT NULL,
                `hsn` VARCHAR(255) NOT NULL,
                `tsn` VARCHAR(255) NOT NULL,
                `hersteller` VARCHAR(255) NOT NULL,
                `modell` VARCHAR(255) NOT NULL,
                `baujahr` VARCHAR(255) NOT NULL,
                `bemerkung` VARCHAR(255) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }
        
    }

    public function updateDestructive(Connection $connection): void
    {
        // Destructive updates go here, if necessary
    }

    private function tableExists(Connection $connection, string $tableName): bool
    {
        $schemaManager = $connection->getSchemaManager();
        return $schemaManager->tablesExist([$tableName]);
    }
}
