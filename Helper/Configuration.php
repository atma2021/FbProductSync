<?php

namespace Atma\FacebookSync\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\UrlInterface;

class Configuration extends AbstractHelper
{
    const ENABLE_FB_SYNC_CRON = 'configuration/general/enable_fb_sync';
    const FB_PAGE_ID = 'configuration/general/fb_page_id';
    const FB_ACCESS_TOKEN = 'configuration/general/fb_access_token';
    const FB_POST_IMAGE = 'configuration/general/fb_post_image';
    const FB_CUSTOM_ATTRIBUTES = 'configuration/general/fb_custom_attributes';
    const FB_CURRENCY = 'configuration/general/fb_currency';
    const FB_ATTRIBUTE_LABEL_MAPPING = 'configuration/general/fb_attribute_label_mapping';

    public function __construct(
        Context $context,
        protected WriterInterface $configWriter,
        protected EncryptorInterface $encryptor,
        protected \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    public function enableFbUsersSyncCron(): bool
    {
        return (bool) $this->scopeConfig->getValue(self::ENABLE_FB_SYNC_CRON, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get Facebook Page ID
     *
     * @return string|null
     */
    public function getFbPageId(): ?string
    {
        return $this->scopeConfig->getValue(self::FB_PAGE_ID, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get Facebook Access Token
     *
     * @return string|null
     */
    public function getFbAccessToken(): ?string
    {
        $encryptedToken = $this->scopeConfig->getValue(self::FB_ACCESS_TOKEN, ScopeInterface::SCOPE_STORE);

        if (!$encryptedToken) {
            return null;
        }

        // Decrypt the token (Magento encrypts it because we used backend_model Encrypted)
        $token = $this->encryptor->decrypt($encryptedToken);

        // Remove any whitespace, quotes, or newlines that might have been added
        return $token ? trim(str_replace(['"', "'", "\n", "\r"], '', $token)) : null;
    }

    /**
     * Get Facebook Post Image URL
     *
     * @return string|null
     */
    public function getFbPostImageUrl(): ?string
    {
        $imagePath = $this->scopeConfig->getValue(self::FB_POST_IMAGE, ScopeInterface::SCOPE_STORE);

        if (!$imagePath) {
            return null;
        }

        try {
            $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
            return $mediaUrl . 'atma/facebook_sync/' . $imagePath;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get Facebook Custom Attributes
     *
     * @return array
     */
    public function getFbCustomAttributes(): array
    {
        $attributesString = $this->scopeConfig->getValue(self::FB_CUSTOM_ATTRIBUTES, ScopeInterface::SCOPE_STORE);
        
        if (empty($attributesString)) {
            // Default attributes if none selected
            return ['name', 'price', 'url'];
        }
        
        return explode(',', $attributesString);
    }

    /**
     * Get Facebook Price Attribute Code (backward compatibility)
     *
     * @return string
     */
    public function getFbPriceAttribute(): string
    {
        $customAttributes = $this->getFbCustomAttributes();
        
        // Look for price-related attributes in the selected custom attributes
        $priceAttributes = ['price', 'final_price', 'special_price'];
        foreach ($priceAttributes as $priceAttr) {
            if (in_array($priceAttr, $customAttributes)) {
                return $priceAttr;
            }
        }
        
        // Fallback to 'price' if no price attribute is selected
        return 'price';
    }

    /**
     * Get Facebook Post Currency
     *
     * @return string
     */
    public function getFbCurrency(): string
    {
        return $this->scopeConfig->getValue(self::FB_CURRENCY, ScopeInterface::SCOPE_STORE) ?: 'store';
    }

    /**
     * Get Attribute Label Mapping
     *
     * @return array
     */
    public function getFbAttributeLabelMapping(): array
    {
        $mappingJson = $this->scopeConfig->getValue(self::FB_ATTRIBUTE_LABEL_MAPPING, ScopeInterface::SCOPE_STORE);
        
        if (empty($mappingJson)) {
            return [];
        }
        
        $mappings = json_decode($mappingJson, true);
        return is_array($mappings) ? $mappings : [];
    }
}
