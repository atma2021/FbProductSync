<?php

namespace Atma\FacebookSync\Model\Config\Source;

use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory;
use Magento\Framework\Option\ArrayInterface;

class CustomAttributes implements ArrayInterface
{
    public function __construct(
        protected CollectionFactory $attributeCollectionFactory
    ) {}

    /**
     * Get all product attributes suitable for Facebook posts
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $options = [];
        
        // Add special attributes first (most commonly used)
        $options[] = [
            'value' => 'url',
            'label' => __('Product URL') . ' (' . __('Recommended') . ')'
        ];
        
        $options[] = [
            'value' => 'description',
            'label' => __('Product Description') . ' (' . __('Recommended') . ')'
        ];
        
        $options[] = [
            'value' => 'short_description',
            'label' => __('Short Description')
        ];
        
        $options[] = [
            'value' => 'name',
            'label' => __('Product Name')
        ];
        
        // Get all product attributes
        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('entity_type_id', 4); // 4 = Product entity type
        $collection->addFieldToFilter('is_user_defined', 1); // Only custom attributes
        $collection->addFieldToFilter('frontend_input', ['in' => [
            'text', 'textarea', 'price', 'select', 'multiselect', 'boolean', 'date'
        ]]);
        
        foreach ($collection as $attribute) {
            // Skip attributes that are not suitable for display
            if (in_array($attribute->getAttributeCode(), [
                'status', 'visibility', 'tax_class_id', 'category_ids', 'gallery'
            ])) {
                continue;
            }
            
            $label = $attribute->getFrontendLabel() ?: $attribute->getAttributeCode();
            $options[] = [
                'value' => $attribute->getAttributeCode(),
                'label' => __($label)
            ];
        }
        
        // Add common system attributes
        $systemAttributes = [
            'price' => __('Price'),
            'special_price' => __('Special Price'),
            'final_price' => __('Final Price'),
            'sku' => __('SKU'),
            'weight' => __('Weight'),
            'created_at' => __('Created Date'),
            'updated_at' => __('Updated Date')
        ];
        
        foreach ($systemAttributes as $code => $label) {
            $options[] = [
                'value' => $code,
                'label' => $label
            ];
        }
        
        // Sort by label
        usort($options, function($a, $b) {
            return strcmp($a['label'], $b['label']);
        });
        
        return $options;
    }
}
