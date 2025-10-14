<?php
namespace Atma\FacebookSync\Model\Repository;

use Atma\FacebookSync\Model\FbProducts;
use Atma\FacebookSync\Model\FbProductsFactory;
use Atma\FacebookSync\Model\ResourceModel\FbProducts\CollectionFactory as FbProductsCollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;

class FbProductsRepository
{
    /**
     * @var FbProductsFactory
     */
    protected $fbProductsFactory;

    /**
     * @var FbProductsCollectionFactory
     */
    protected $collectionFactory;

    /**
     * @param FbProductsFactory $fbProductsFactory
     * @param FbProductsCollectionFactory $collectionFactory
     */
    public function __construct(
        FbProductsFactory $fbProductsFactory,
        FbProductsCollectionFactory $collectionFactory
    ) {
        $this->fbProductsFactory = $fbProductsFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Save Facebook Product
     *
     * @param FbProducts $fbProduct
     * @return FbProducts
     * @throws CouldNotSaveException
     */
    public function save(FbProducts $fbProduct)
    {
        try {
            $fbProduct->save();
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the Facebook Product: %1', $exception->getMessage()),
                $exception
            );
        }
        return $fbProduct;
    }

    /**
     * Get Facebook Product by ID
     *
     * @param int $id
     * @return FbProducts
     * @throws NoSuchEntityException
     */
    public function getById($id)
    {
        $fbProduct = $this->fbProductsFactory->create();
        $fbProduct->load($id);
        
        if (!$fbProduct->getId()) {
            throw new NoSuchEntityException(__('Facebook Product with ID "%1" does not exist.', $id));
        }
        
        return $fbProduct;
    }

    /**
     * Get Facebook Product by SKU
     *
     * @param string $sku
     * @return FbProducts
     * @throws NoSuchEntityException
     */
    public function getBySku($sku)
    {
        $collection = $this->collectionFactory->create()
            ->addSkuFilter($sku)
            ->setPageSize(1);
            
        $fbProduct = $collection->getFirstItem();
        
        if (!$fbProduct->getId()) {
            throw new NoSuchEntityException(__('Facebook Product with SKU "%1" does not exist.', $sku));
        }
        
        return $fbProduct;
    }

    /**
     * Get collection
     *
     * @return \Atma\FacebookSync\Model\ResourceModel\FbProducts\Collection
     */
    public function getCollection()
    {
        return $this->collectionFactory->create();
    }

    /**
     * Get pending Facebook Products
     *
     * @param int $limit
     * @return \Atma\FacebookSync\Model\ResourceModel\FbProducts\Collection
     */
    public function getPendingProducts($limit = 10)
    {
        return $this->collectionFactory->create()
            ->addStatusFilter(FbProducts::STATUS_PENDING)
            ->addScheduledAtFilter(date('Y-m-d H:i:s'))
            ->setOrder('scheduled_at', 'ASC')
            ->setPageSize($limit);
    }

    /**
     * Create new Facebook Product entry
     *
     * @param array $data
     * @return FbProducts
     */
    public function create(array $data = [])
    {
        $defaults = [
            'status' => FbProducts::STATUS_PENDING,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'scheduled_at' => date('Y-m-d H:i:s')
        ];

        $data = array_merge($defaults, $data);
        
        $fbProduct = $this->fbProductsFactory->create();
        $fbProduct->setData($data);
        
        return $fbProduct;
    }

    /**
     * Delete Facebook Product
     *
     * @param FbProducts $fbProduct
     * @return bool
     * @throws StateException
     */
    public function delete(FbProducts $fbProduct)
    {
        try {
            $fbProduct->delete();
        } catch (\Exception $e) {
            throw new StateException(
                __('Unable to remove Facebook Product with ID %1', $fbProduct->getId())
            );
        }
        
        return true;
    }
}
