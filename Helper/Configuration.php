<?php

namespace Atma\FacebookSync\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class Configuration extends AbstractHelper
{
    const ENABLE_FB_SYNC_CRON = 'configuration/general/enable_fb_sync';
    const FB_PAGE_ID = 'configuration/general/fb_page_id';
    const FB_ACCESS_TOKEN = 'configuration/general/fb_access_token';

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        EncryptorInterface $encryptor
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->encryptor = $encryptor;
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
}
