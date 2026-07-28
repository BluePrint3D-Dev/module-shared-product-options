<?php
/**
 * Copyright (c) 2026 BluePrint3D Ltd. All rights reserved.
 *
 * This software is provided free of charge for personal or commercial use.
 * Resale, redistribution, or sublicensing of this source code, modified or
 * unmodified, for direct financial gain is strictly prohibited.
 *
 * @author    BluePrint3D Ltd <support@blueprint3d.dev>
 * @copyright 2026 BluePrint3D Ltd (Company No. 13473806)
 * @license   Custom Proprietary EULA (See LICENSE.txt)
 */
namespace BluePrint3D\SharedProductOptions\Plugin;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\App\CacheInterface;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Indexer\CacheContextFactory;
use BluePrint3D\SharedProductOptions\Model\ResourceModel\SharedOptionProduct\CollectionFactory as LinkCollectionFactory;
use BluePrint3D\SharedProductOptions\Model\OptionFactory;

/**
 * Class CacheInvalidatorPlugin
 * Invalidates cache for products linked to modified or deleted shared option groups/options.
 */
class CacheInvalidatorPlugin
{
    /**
     * @var LinkCollectionFactory
     */
    protected $linkCollectionFactory;

    /**
     * @var OptionFactory
     */
    protected $optionFactory;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var EventManager
     */
    protected $eventManager;

    /**
     * @var CacheContextFactory
     */
    protected $cacheContextFactory;

    /**
     * CacheInvalidatorPlugin constructor.
     *
     * @param LinkCollectionFactory $linkCollectionFactory
     * @param OptionFactory $optionFactory
     * @param CacheInterface $cache
     * @param EventManager $eventManager
     * @param CacheContextFactory $cacheContextFactory
     */
    public function __construct(
        LinkCollectionFactory $linkCollectionFactory,
        OptionFactory $optionFactory,
        CacheInterface $cache,
        EventManager $eventManager,
        CacheContextFactory $cacheContextFactory
    ) {
        $this->linkCollectionFactory = $linkCollectionFactory;
        $this->optionFactory = $optionFactory;
        $this->cache = $cache;
        $this->eventManager = $eventManager;
        $this->cacheContextFactory = $cacheContextFactory;
    }

    /**
     * Intercept the save action of Groups and Options
     *
     * @param mixed $subject
     * @param mixed $result
     * @param AbstractModel $model
     * @return mixed
     */
    public function afterSave($subject, $result, AbstractModel $model)
    {
        $groupId = $this->getGroupIdFromModel($model);
        if ($groupId) {
            $this->invalidateCacheByGroupId($groupId);
        }
        return $result;
    }

    /**
     * Intercept the delete action of Groups and Options (runs BEFORE delete while DB relations exist!)
     *
     * @param mixed $subject
     * @param AbstractModel $model
     * @return void
     */
    public function beforeDelete($subject, AbstractModel $model)
    {
        $groupId = $this->getGroupIdFromModel($model);
        if ($groupId) {
            $this->invalidateCacheByGroupId($groupId);
        }
    }

    /**
     * Resolve the primary Group ID from the model (handles both Group and Option models)
     *
     * @param AbstractModel $model
     * @return int|null
     */
    private function getGroupIdFromModel(AbstractModel $model): ?int
    {
        $groupId = null;

        if ($model instanceof \BluePrint3D\SharedProductOptions\Model\Group) {
            $groupId = (int)$model->getId();
        } elseif ($model instanceof \BluePrint3D\SharedProductOptions\Model\Option) {
            $groupId = (int)$model->getGroupId();

            // If it's a sub-option child value (parent_id > 0), load parent to retrieve group_id
            if (!$groupId && $model->getParentId()) {
                $parentOptionId = (int)$model->getParentId();
                $parentOption = $this->optionFactory->create()->load($parentOptionId);
                if ($parentOption && $parentOption->getId()) {
                    $groupId = (int)$parentOption->getGroupId();
                }
            }
        }

        return $groupId;
    }

    /**
     * Fetch all mapped product IDs for this group and invalidate their cache tags globally
     *
     * @param int $groupId
     * @return void
     */
    private function invalidateCacheByGroupId(int $groupId): void
    {
        if (!$groupId) {
            return;
        }

        // 1. Fetch all product IDs currently linked to this group
        $linkCollection = $this->linkCollectionFactory->create()
            ->addFieldToFilter('group_id', $groupId);

        $productIds = [];
        foreach ($linkCollection as $link) {
            $productIds[] = (int)$link->getProductId();
        }

        if (empty($productIds)) {
            return;
        }

        // 2. Generate standard product cache tags (e.g. "cat_p_123")
        $cacheTags = [];
        foreach ($productIds as $productId) {
            $cacheTags[] = ProductModel::CACHE_TAG . '_' . $productId;
        }

        if (!empty($cacheTags)) {
            // A. Clean Magento's local Block HTML cache
            $this->cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, $cacheTags);

            // B. Clean Full Page Cache, Varnish, Redis, and Fastly globally
            /** @var \Magento\Framework\Indexer\CacheContext $cacheContext */
            $cacheContext = $this->cacheContextFactory->create();
            $cacheContext->registerEntities(ProductModel::CACHE_TAG, $productIds);
            $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $cacheContext]);
        }
    }
}
