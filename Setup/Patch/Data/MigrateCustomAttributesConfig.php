<?php

namespace Atma\FacebookSync\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class MigrateCustomAttributesConfig implements DataPatchInterface, PatchRevertableInterface
{
    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
        protected WriterInterface $configWriter,
        protected ScopeConfigInterface $scopeConfig
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

        // Get existing price attribute configuration
        $oldPriceAttribute = $this->scopeConfig->getValue(
            'configuration/general/fb_price_attribute',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($oldPriceAttribute) {
            // Create new custom attributes array with the old price attribute plus defaults
            $defaultAttributes = ['name', 'description', 'url'];
            
            // Add the old price attribute if it's not already in defaults
            if (!in_array($oldPriceAttribute, $defaultAttributes)) {
                $defaultAttributes[] = $oldPriceAttribute;
            }

            // Save new configuration
            $this->configWriter->save(
                'configuration/general/fb_custom_attributes',
                implode(',', $defaultAttributes)
            );

            // Remove old configuration
            $connection = $this->moduleDataSetup->getConnection();
            $connection->delete(
                $this->moduleDataSetup->getTable('core_config_data'),
                ['path = ?' => 'configuration/general/fb_price_attribute']
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * Revert patch
     *
     * @return $this
     */
    public function revert(): self
    {
        $this->moduleDataSetup->getConnection()->startSetup();

        // Get current custom attributes configuration
        $customAttributes = $this->scopeConfig->getValue(
            'configuration/general/fb_custom_attributes',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($customAttributes) {
            $attributesArray = explode(',', $customAttributes);
            
            // Find a price-related attribute to restore as the old price attribute
            $priceAttributes = ['price', 'final_price', 'special_price'];
            $priceAttribute = 'price'; // default fallback
            
            foreach ($priceAttributes as $priceAttr) {
                if (in_array($priceAttr, $attributesArray)) {
                    $priceAttribute = $priceAttr;
                    break;
                }
            }

            // Restore old configuration
            $this->configWriter->save(
                'configuration/general/fb_price_attribute',
                $priceAttribute
            );

            // Remove new configuration
            $connection = $this->moduleDataSetup->getConnection();
            $connection->delete(
                $this->moduleDataSetup->getTable('core_config_data'),
                ['path = ?' => 'configuration/general/fb_custom_attributes']
            );
        }

        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }
}
