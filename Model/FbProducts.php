<?php
namespace Atma\FacebookSync\Model;

use Magento\Framework\Model\AbstractModel;
use Atma\FacebookSync\Model\ResourceModel\FbProducts as FbProductsResource;

class FbProducts extends AbstractModel
{
    const STATUS_PENDING = 'pending';
    const STATUS_PUBLISHED = 'published';
    const STATUS_FAILED = 'failed';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(FbProductsResource::class);
    }

    /**
     * Get status options
     *
     * @return array
     */
    public function getStatusOptions()
    {
        return [
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_PUBLISHED => __('Published'),
            self::STATUS_FAILED => __('Failed')
        ];
    }

    /**
     * Get status label
     *
     * @return string
     */
    public function getStatusLabel()
    {
        $statuses = $this->getStatusOptions();
        return $statuses[$this->getStatus()] ?? '';
    }

    /**
     * Mark product as published
     *
     * @param string $postId
     * @return $this
     */
    public function markAsPublished($postId)
    {
        $this->setData([
            'status' => self::STATUS_PUBLISHED,
            'facebook_post_id' => $postId,
            'published_at' => date('Y-m-d H:i:s'),
            'error_message' => null
        ]);
        return $this;
    }

    /**
     * Mark product as failed
     *
     * @param string $error
     * @return $this
     */
    public function markAsFailed($error)
    {
        $this->setData([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
            'published_at' => date('Y-m-d H:i:s')
        ]);
        return $this;
    }
}
