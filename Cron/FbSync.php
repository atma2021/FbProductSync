<?php

namespace Atma\FacebookSync\Cron;

use Atma\FacebookSync\Helper\Configuration;
use Atma\FacebookSync\Model\FbProducts;
use Atma\FacebookSync\Model\Repository\FbProductsRepository;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\ScopeInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Framework\UrlInterface;
use Psr\Log\LoggerInterface;
use Exception;

class FbSync
{
    /**
     * @param LoggerInterface $logger
     * @param ProductCollectionFactory $productCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param FbProductsRepository $fbProductsRepository
     * @param DateTime $dateTime
     * @param Configuration $configuration
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected ProductCollectionFactory $productCollectionFactory,
        protected ScopeConfigInterface $scopeConfig,
        protected \Magento\Framework\HTTP\Client\Curl $curl,
        protected FbProductsRepository $fbProductsRepository,
        protected DateTime $dateTime,
        protected Configuration $configuration,
        protected \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {}

    /**
     * Execute the cron job
     *
     * @return void
     */
    public function execute()
    {
        try {
            $isEnabled = $this->configuration->enableFbUsersSyncCron();

            if (!$isEnabled) {
                $this->logger->info('Facebook Sync is disabled. Skipping...');
                return;
            }

            $pageId = $this->configuration->getFbPageId();
            $accessToken = $this->configuration->getFbAccessToken();
            if (empty($pageId) || empty($accessToken)) {
                $this->logger->error('Facebook Page ID or Access Token is not configured.');
                return;
            }
            // Debug: Log token info (first/last 10 chars and length for security)
            $tokenLength = strlen($accessToken);
            $tokenPreview = substr($accessToken, 0, 10) . '...' . substr($accessToken, -10);
            $this->logger->info(sprintf(
                'Using Access Token: %s (length: %d, starts with: %s)',
                $tokenPreview,
                $tokenLength,
                substr($accessToken, 0, 3)
            ));

            // Get all products created yesterday
            $products = $this->getLatestProducts();

            $this->logger->info(sprintf('Found %d products to post to Facebook.', count($products)));

            if (empty($products)) {
                $this->logger->info('No products found to post on Facebook.');
                return;
            }

            // Post all products in a single Facebook post
            if ($this->postProductsToFacebook($products, $pageId, $accessToken)) {
                $this->logger->info('Successfully posted all products to Facebook.');
            } else {
                $this->logger->error('Failed to post products to Facebook.');
            }
        } catch (Exception $e) {
            $this->logger->error('Error in Facebook Sync: ' . $e->getMessage());
        }
    }

    /**
     * Get products created today that haven't been posted to Facebook yet
     *
     * @return array
     */
    protected function getLatestProducts()
    {
        try {
            // Get already successfully posted product SKUs (published status only)
            $postedSkus = [];
            $postedCollection = $this->fbProductsRepository->getCollection()
                ->addStatusFilter(FbProducts::STATUS_PUBLISHED)
                ->addFieldToSelect('sku');

            foreach ($postedCollection as $item) {
                $postedSkus[] = $item->getSku();
            }

            // Get yesterday's date range (from midnight to 23:59:59)
            $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
            $yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));

            // Get configured price attribute
            $priceAttribute = $this->configuration->getFbPriceAttribute();

            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect(['name', 'sku', 'image', 'short_description', $priceAttribute, 'type_id', 'url_key'])
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE])
                ->addAttributeToFilter('created_at', ['from' => $yesterdayStart, 'to' => $yesterdayEnd])
                ->addUrlRewrite();

            // Exclude already posted products
            if (!empty($postedSkus)) {
                $collection->addFieldToFilter('sku', ['nin' => $postedSkus]);
            }

            $collection->setOrder('created_at', 'DESC');

            $products = [];
            
            foreach ($collection as $product) {
                // Get friendly product URL
                $productUrl = $this->storeManager->getStore()->getBaseUrl() . $product->getUrlKey();
                
                // Get price from configured attribute
                $priceValue = $product->getData($priceAttribute);
                if (!$priceValue) {
                    $priceValue = $product->getFinalPrice();
                }

                // Create Facebook Product entry
                $fbProduct = $this->fbProductsRepository->create([
                    'sku' => $product->getSku(),
                    'product_name' => $product->getName(),
                    'product_type' => $product->getTypeId(),
                    'image_url' => $this->configuration->getFbPostImageUrl(),
                    'status' => FbProducts::STATUS_PENDING,
                    'scheduled_at' => $this->dateTime->date('Y-m-d H:i:s')
                ]);

                try {
                    $this->fbProductsRepository->save($fbProduct);

                    $products[] = [
                        'id' => $fbProduct->getId(),
                        'name' => $product->getName(),
                        'sku' => $product->getSku(),
                        'url' => $productUrl,
                        'price' => $priceValue,
                        'description' => $product->getShortDescription() ?: $product->getName(),
                    ];
                } catch (\Exception $e) {
                    $this->logger->error('Error saving Facebook Product: ' . $e->getMessage());
                }
            }

            return $products;
        } catch (Exception $e) {
            $this->logger->error('Error getting latest products: ' . $e->getMessage());
            return [];
        }
    }


    /**
     * Post all products to Facebook in a single post
     *
     * @param array $products
     * @param string $pageId
     * @param string $accessToken
     * @return bool
     */
    protected function postProductsToFacebook($products, $pageId, $accessToken)
    {
        $imageUrl = $this->configuration->getFbPostImageUrl();
        
        if (empty($imageUrl)) {
            $this->logger->error('No Facebook post image configured.');
            $this->markProductsAsFailed($products, 'No image available');
            return false;
        }

        try {
            // Post with photo using /photos endpoint
            $url = "https://graph.facebook.com/v24.0/{$pageId}/photos";

            // Build message with all products
            $message = "🏠 " . __("New Properties Available") . "\n\n";
            
            foreach ($products as $index => $product) {
                $message .= ($index + 1) . ". {$product['name']}\n";
                if (!empty($product['description'])) {
                    $message .= $product['description'] . "\n";
                }
                $message .= "💶 " . __("Price:") . " " . number_format($product['price'], 2) . " EUR\n";
                $message .= "🔗 " . __("Details") . " " . $product['url'] . "\n\n";
            }
            
            $message .= "━━━━━━━━━━━━━━━━━━━";

            $params = [
                'url' => $imageUrl,
                'caption' => $message,
                'access_token' => $accessToken
            ];

            $this->logger->info('Posting to Facebook: ' . count($products) . ' products', [
                'url' => $url,
                'image_url' => $imageUrl
            ]);

            $this->curl->post($url, $params);
            $responseBody = $this->curl->getBody();
            $httpStatus = $this->curl->getStatus();

            $this->logger->info('Facebook API Response', [
                'status' => $httpStatus,
                'body' => $responseBody
            ]);

            $response = json_decode($responseBody, true);

            if (!$response) {
                throw new Exception('Invalid JSON response from Facebook: ' . $responseBody);
            }

            if (isset($response['error'])) {
                throw new Exception($response['error']['message'] ?? 'Unknown Facebook API error');
            }

            if (!isset($response['id'])) {
                throw new Exception('No post ID returned from Facebook. Response: ' . json_encode($response));
            }

            // Update all products status to published
            foreach ($products as $product) {
                try {
                    $fbProduct = $this->fbProductsRepository->getById($product['id']);
                    $fbProduct->setStatus(FbProducts::STATUS_PUBLISHED);
                    $fbProduct->setFacebookPostId($response['id']);
                    $fbProduct->setPostId($response['id']);
                    $fbProduct->setPublishedAt($this->dateTime->date('Y-m-d H:i:s'));
                    $fbProduct->setMessage($message);
                    $fbProduct->setErrorMessage(null);
                    $this->fbProductsRepository->save($fbProduct);
                } catch (\Exception $e) {
                    $this->logger->error('Error updating Facebook Product to published status: ' . $e->getMessage());
                }
            }

            $this->logger->info(sprintf('Successfully posted %d products to Facebook (Post ID: %s)',
                count($products),
                $response['id']
            ));

            return true;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->error('Error posting to Facebook: ' . $errorMessage);
            $this->markProductsAsFailed($products, $errorMessage);
            return false;
        }
    }

    /**
     * Mark products as failed
     *
     * @param array $products
     * @param string $errorMessage
     * @return void
     */
    protected function markProductsAsFailed($products, $errorMessage)
    {
        foreach ($products as $product) {
            try {
                $fbProduct = $this->fbProductsRepository->getById($product['id']);
                $fbProduct->setStatus(FbProducts::STATUS_FAILED);
                $fbProduct->setErrorMessage($errorMessage);
                $fbProduct->setPublishedAt($this->dateTime->date('Y-m-d H:i:s'));
                $this->fbProductsRepository->save($fbProduct);
            } catch (\Exception $e) {
                $this->logger->error('Error updating product to failed status: ' . $e->getMessage());
            }
        }
    }
}
