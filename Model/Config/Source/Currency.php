<?php

namespace Atma\FacebookSync\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Currency implements ArrayInterface
{
    /**
     * Get currency options for Facebook posts
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'EUR', 'label' => __('Euro (€)')],
            ['value' => 'USD', 'label' => __('US Dollar ($)')],
            ['value' => 'GBP', 'label' => __('British Pound (£)')],
            ['value' => 'RON', 'label' => __('Romanian Leu (RON)')],
            ['value' => 'CAD', 'label' => __('Canadian Dollar (CAD)')],
            ['value' => 'AUD', 'label' => __('Australian Dollar (AUD)')],
            ['value' => 'JPY', 'label' => __('Japanese Yen (¥)')],
            ['value' => 'CHF', 'label' => __('Swiss Franc (CHF)')],
            ['value' => 'SEK', 'label' => __('Swedish Krona (SEK)')],
            ['value' => 'NOK', 'label' => __('Norwegian Krone (NOK)')],
            ['value' => 'DKK', 'label' => __('Danish Krone (DKK)')],
            ['value' => 'PLN', 'label' => __('Polish Złoty (PLN)')],
            ['value' => 'CZK', 'label' => __('Czech Koruna (CZK)')],
            ['value' => 'HUF', 'label' => __('Hungarian Forint (HUF)')],
            ['value' => 'BGN', 'label' => __('Bulgarian Lev (BGN)')],
            ['value' => 'store', 'label' => __('Use Store Currency (Auto)')],
        ];
    }
}
