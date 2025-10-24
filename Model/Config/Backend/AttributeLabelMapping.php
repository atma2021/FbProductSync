<?php

namespace Atma\FacebookSync\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\ValidatorException;

class AttributeLabelMapping extends Value
{
    /**
     * Validate and process the attribute label mapping before saving
     *
     * @return $this
     * @throws ValidatorException
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        
        if (empty($value)) {
            return parent::beforeSave();
        }

        // Parse and validate the mapping format
        $mappings = [];
        $lines = explode("\n", $value);
        
        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }
            
            // Check format: attribute_name|custom_label
            $parts = explode('|', $line, 2);
            if (count($parts) !== 2) {
                throw new ValidatorException(
                    __('Invalid format on line %1. Use format: "attribute_name|custom_label"', $lineNumber + 1)
                );
            }
            
            $attributeName = trim($parts[0]);
            $customLabel = trim($parts[1]);
            
            // Validate attribute name (allow letters, numbers, underscores, hyphens, and spaces)
            if (!preg_match('/^[a-zA-Z0-9_\s-]+$/', $attributeName)) {
                throw new ValidatorException(
                    __('Invalid attribute name "%1" on line %2. Use only letters, numbers, underscores, hyphens, and spaces.', 
                       $attributeName, $lineNumber + 1)
                );
            }
            
            // Validate custom label is not empty
            if (empty($customLabel)) {
                throw new ValidatorException(
                    __('Custom label cannot be empty on line %1', $lineNumber + 1)
                );
            }
            
            $mappings[$attributeName] = $customLabel;
        }
        
        // Store as JSON for easy retrieval
        $this->setValue(json_encode($mappings));
        
        return parent::beforeSave();
    }
    
    /**
     * Process the value after loading from database
     *
     * @return $this
     */
    public function afterLoad()
    {
        $value = $this->getValue();
        
        if (!empty($value)) {
            // Convert JSON back to readable format for admin display
            $mappings = json_decode($value, true);
            if (is_array($mappings)) {
                $lines = [];
                foreach ($mappings as $attribute => $label) {
                    $lines[] = $attribute . '|' . $label;
                }
                $this->setValue(implode("\n", $lines));
            }
        }
        
        return parent::afterLoad();
    }
}
