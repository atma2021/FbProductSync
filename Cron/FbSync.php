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
        protected Configuration $configuration
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

            // Get all products created today
            $products = $this->getLatestProducts();

            $this->logger->info(sprintf('Attempting to post %d products to Facebook.', count($products)));

            if (empty($products)) {
                $this->logger->info('No products found to post on Facebook.');
                return;
            }

            // Post each product to Facebook
            $successCount = 0;
            $failCount = 0;

            foreach ($products as $product) {
                if ($this->postToFacebook($product, $pageId, $accessToken)) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            }

            $this->logger->info(sprintf(
                'Facebook Sync completed: %d successful, %d failed out of %d products.',
                $successCount,
                $failCount,
                count($products)
            ));
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

            // Get today's date range (from midnight to now)
            $todayStart = date('Y-m-d 00:00:00');
            $todayEnd = date('Y-m-d 23:59:59');

            $collection = $this->productCollectionFactory->create();
            $collection->addAttributeToSelect(['name', 'sku', 'image', 'short_description', 'price', 'type_id'])
                ->addAttributeToFilter('status', Status::STATUS_ENABLED)
                ->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE])
                ->addAttributeToFilter('created_at', ['from' => $todayStart, 'to' => $todayEnd]);

            // Exclude already posted products
            if (!empty($postedSkus)) {
                $collection->addFieldToFilter('sku', ['nin' => $postedSkus]);
            }

            $collection->setOrder('created_at', 'DESC');

            $products = [];
            foreach ($collection as $product) {
                $imageUrl = $this->getImageUrl($product);

                // Create Facebook Product entry
                $fbProduct = $this->fbProductsRepository->create([
                    'sku' => $product->getSku(),
                    'product_name' => $product->getName(),
                    'product_type' => $product->getTypeId(),
                    'image_url' => $imageUrl,
                    'status' => FbProducts::STATUS_PENDING,
                    'scheduled_at' => $this->dateTime->date('Y-m-d H:i:s')
                ]);

                try {
                    $this->fbProductsRepository->save($fbProduct);

                    $products[] = [
                        'id' => $fbProduct->getId(),
                        'name' => $product->getName(),
                        'sku' => $product->getSku(),
                        'url' => $product->getProductUrl(),
                        'price' => $product->getFinalPrice(),
                        'description' => $product->getShortDescription() ?: $product->getName(),
                        'image_url' => $imageUrl
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
     * Get product image URL
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return string
     */
    protected function getImageUrl($product)
    {
        try {
            // TEMPORARY: Use hardcoded URL for testing
//            return 'https://servusimobiliare.ro/media/mf_webp/png/media/catalog/product/cache/a1a7ca4652235e5d691fe22930aeb2c1/i/m/image1-2_sell_11097.webp';

            $imageUrl = $product->getImage();
            if ($imageUrl && $imageUrl !== 'no_selection') {
                // Get base URL
                $baseUrl = $this->scopeConfig->getValue(
                    'web/secure/base_url',
                    ScopeInterface::SCOPE_STORE
                );

                // Ensure base URL ends with /
                $baseUrl = rtrim($baseUrl, '/') . '/';

                // Build full image URL
                $fullImageUrl = $baseUrl . 'pub/media/catalog/product' . $imageUrl;

                return $fullImageUrl;
            }
            return '';
        } catch (Exception $e) {
            $this->logger->error('Error getting product image URL: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Post product to Facebook
     *
     * @param array $product
     * @param string $pageId
     * @param string $accessToken
     * @return bool
     */
    protected function postToFacebook($product, $pageId, $accessToken)
    {
        if (empty($product['image_url'])) {
            $this->logger->warning(sprintf('Skipping product %s - no image available', $product['name']));

            // Update status to failed
            try {
                $fbProduct = $this->fbProductsRepository->getById($product['id']);
                $fbProduct->markAsFailed('No image available');
                $this->fbProductsRepository->save($fbProduct);
            } catch (\Exception $e) {
                $this->logger->error('Error updating Facebook Product status: ' . $e->getMessage());
            }

            return false;
        }

        try {
            // Post with photo using /photos endpoint
            $url = "https://graph.facebook.com/v24.0/{$pageId}/photos";

            $message = "🆕 {$product['name']} 🏠\n\n";
            $message .= $product['description'] . "\n\n";
            $message .= "💶 " . __("Price:") . " " . number_format($product['price'], 2) . " EUR\n";
            $message .= "🔗 " . __("Details") . " " . $product['url'];

            $params = [
                'url' => $product['image_url'],
                'caption' => $message,
                'access_token' => $accessToken
            ];

            $this->logger->info('Posting to Facebook: ' . $product['name'], [
                'url' => $url,
                'image_url' => $product['image_url']
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

            // Update status to published
            try {
                $fbProduct = $this->fbProductsRepository->getById($product['id']);
                $fbProduct->setStatus(FbProducts::STATUS_PUBLISHED);
                $fbProduct->setFacebookPostId($response['id']);
                $fbProduct->setPostId($response['id']); // Save to post_id column
                $fbProduct->setPublishedAt($this->dateTime->date('Y-m-d H:i:s'));
                $fbProduct->setMessage($message);
                $fbProduct->setErrorMessage(null);
                $this->fbProductsRepository->save($fbProduct);

                $this->logger->info(sprintf('Successfully posted product to Facebook: %s (Post ID: %s)',
                    $product['name'],
                    $response['id']
                ));
            } catch (\Exception $e) {
                $this->logger->error('Error updating Facebook Product to published status: ' . $e->getMessage());
            }

            return true;
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->logger->error(sprintf('Error posting to Facebook for product %s: %s',
                $product['name'],
                $errorMessage
            ));

            // Update status to failed
            try {
                $fbProduct = $this->fbProductsRepository->getById($product['id']);
                $fbProduct->setStatus(FbProducts::STATUS_FAILED);
                $fbProduct->setErrorMessage($errorMessage);
                $fbProduct->setPublishedAt($this->dateTime->date('Y-m-d H:i:s'));
                $this->fbProductsRepository->save($fbProduct);

                $this->logger->info(sprintf('Updated product %s to failed status', $product['name']));
            } catch (\Exception $e) {
                $this->logger->error('Error updating Facebook Product to failed status: ' . $e->getMessage());
            }

            return false;
        }
    }
}
