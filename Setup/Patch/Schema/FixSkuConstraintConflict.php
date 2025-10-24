<?php

namespace Atma\FacebookSync\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Psr\Log\LoggerInterface;

class FixSkuConstraintConflict implements SchemaPatchInterface
{
    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
        protected LoggerInterface $logger
    ) {}

    /**
     * Get array of patches that have to be executed prior to this.
     *
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * Get patch aliases (previous names) for this patch.
     *
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Run code inside patch
     *
     * @return $this
     */
    public function apply(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $tableName = $this->moduleDataSetup->getTable('atma_fb_products');

        try {
            // Check if table exists
            if (!$connection->isTableExists($tableName)) {
                $this->logger->info("Facebook Sync: Table {$tableName} does not exist, skipping constraint cleanup");
                $this->moduleDataSetup->getConnection()->endSetup();
                return $this;
            }

            // Drop existing conflicting indexes/constraints if they exist
            $indexesToDrop = [
                'ATMA_FB_PRODUCTS_SKU',
                'IDX_ATMA_FB_PRODUCTS_SKU',
                'UNQ_ATMA_FB_PRODUCTS_SKU'
            ];

            foreach ($indexesToDrop as $indexName) {
                try {
                    // Try to drop as index first
                    $connection->dropIndex($tableName, $indexName);
                    $this->logger->info("Facebook Sync: Dropped index {$indexName} from {$tableName}");
                } catch (\Exception $e) {
                    // Index might not exist or might be a constraint, continue
                }

                try {
                    // Try to drop as foreign key constraint
                    $connection->dropForeignKey($tableName, $indexName);
                    $this->logger->info("Facebook Sync: Dropped foreign key {$indexName} from {$tableName}");
                } catch (\Exception $e) {
                    // Constraint might not exist, continue
                }
            }

            // Clean up any duplicate SKUs before adding unique constraint
            $duplicateQuery = "
                DELETE t1 FROM {$tableName} t1
                INNER JOIN {$tableName} t2 
                WHERE t1.sku = t2.sku 
                AND t1.entity_id < t2.entity_id
            ";

            $deletedRows = $connection->query($duplicateQuery)->rowCount();
            if ($deletedRows > 0) {
                $this->logger->info("Facebook Sync: Cleaned up {$deletedRows} duplicate SKU entries");
            }

            $this->logger->info("Facebook Sync: Successfully prepared table for unique SKU constraint");

        } catch (\Exception $e) {
            $this->logger->error("Facebook Sync: Error fixing SKU constraint conflict: " . $e->getMessage());
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }
}
