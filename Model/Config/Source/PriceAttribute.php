<?php

namespace Atma\FacebookSync\Model\Config\Source;

use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory;
use Magento\Framework\Option\ArrayInterface;

class PriceAttribute implements ArrayInterface
{
    public function __construct(
        protected CollectionFactory $attributeCollectionFactory
    ) {}

    /**
     * Get all price-type product attributes
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        
        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('entity_type_id', 4); // 4 = Product entity type
        $collection->addFieldToFilter('frontend_input', 'price');
        
        foreach ($collection as $attribute) {
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => $attribute->getFrontendLabel() ?: $attribute->getAttributeCode()
            ];
        }
        
        // Sort by label
        usort($options, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        
        return $options;
    }
}
