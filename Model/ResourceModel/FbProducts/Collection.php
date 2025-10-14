<?php
namespace Atma\FacebookSync\Model\ResourceModel\FbProducts;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Atma\FacebookSync\Model\FbProducts as FbProductsModel;
use Atma\FacebookSync\Model\ResourceModel\FbProducts as FbProductsResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'entity_id';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(
            FbProductsModel::class,
            FbProductsResource::class
        );
    }

    /**
     * Filter by status
     *
     * @param string $status
     * @return $this
     */
    public function addStatusFilter($status)
    {
        return $this->addFieldToFilter('status', $status);
    }

    /**
     * Filter by SKU
     *
     * @param string $sku
     * @return $this
     */
    public function addSkuFilter($sku)
    {
        return $this->addFieldToFilter('sku', $sku);
    }

    /**
     * Filter by Facebook post ID
     *
     * @param string $postId
     * @return $this
     */
    public function addFacebookPostIdFilter($postId)
    {
        return $this->addFieldToFilter('facebook_post_id', $postId);
    }

    /**
     * Filter by scheduled time
     *
     * @param string $date
     * @param string $comparison
     * @return $this
     */
    public function addScheduledAtFilter($date, $comparison = 'lteq')
    {
        return $this->addFieldToFilter('scheduled_at', [$comparison => $date]);
    }
}
