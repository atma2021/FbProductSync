<?php

namespace Atma\FacebookSync\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

class CustomAttributes extends Value
{
    /**
     * Process data after load
     *
     * @return void
     */
    protected function _afterLoad()
    {
        $value = $this->getValue();
        
        // If no value is set, provide default attributes
        if (empty($value)) {
            $defaultAttributes = ['name', 'description', 'price', 'url'];
            $this->setValue(implode(',', $defaultAttributes));
        }
        
        parent::_afterLoad();
    }
    
    /**
     * Prepare data before save
     *
     * @return $this
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        
        // Ensure URL is always included if attributes are selected
        if (!empty($value)) {
            $attributes = is_array($value) ? $value : explode(',', $value);
            
            // Add URL if not present
            if (!in_array('url', $attributes)) {
                $attributes[] = 'url';
            }
            
            $this->setValue(implode(',', array_unique($attributes)));
        }
        
        return parent::beforeSave();
    }
}
