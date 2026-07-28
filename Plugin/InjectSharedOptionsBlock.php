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

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\OptionFactory;
use Magento\Catalog\Model\Product\Option\ValueFactory as OptionValueFactory;
use Magento\Framework\App\State as AppState;
use Magento\Framework\App\Area as AppArea;
use BluePrint3D\SharedProductOptions\Model\ResourceModel\Group as GroupResource;
use BluePrint3D\SharedProductOptions\Model\ResourceModel\Option\CollectionFactory as OptionCollectionFactory;

class InjectSharedOptionsBlock
{
    protected $groupResource;
    protected $optionCollectionFactory;
    protected $optionFactory;
    protected $optionValueFactory;
    protected $appState;

    // High integer offset to prevent database collisions
    private const SHARED_OPTION_OFFSET = 999000;

    public function __construct(
        GroupResource $groupResource,
        OptionCollectionFactory $optionCollectionFactory,
        OptionFactory $optionFactory,
        OptionValueFactory $optionValueFactory,
        AppState $appState
    ) {
        $this->groupResource = $groupResource;
        $this->optionCollectionFactory = $optionCollectionFactory;
        $this->optionFactory = $optionFactory;
        $this->optionValueFactory = $optionValueFactory;
        $this->appState = $appState;
    }

    /**
     * Intercept hasOptions check to ensure Magento renders the option container
     * even if the product has 0 native custom options in the DB.
     */
    public function afterHasOptions(Product $product, $result)
    {
        if ($result) {
            return true;
        }

        if ($this->isAdminOrCli() || !$product || !$product->getId()) {
            return $result;
        }

        $groupIds = $this->groupResource->getAssignedGroupIds((int)$product->getId());
        return !empty($groupIds);
    }

    /**
     * Alias check for getHasOptions
     */
    public function afterGetHasOptions(Product $product, $result)
    {
        return $this->afterHasOptions($product, $result);
    }

    /**
     * Inject shared options directly into the product model whenever options are retrieved on the frontend
     */
    public function afterGetOptions(Product $product, $existingOptions)
    {
        if ($this->isAdminOrCli() || !$product || !$product->getId()) {
            return $existingOptions;
        }

        // Avoid infinite loops if getOptions() is triggered recursively during hydration
        if ($product->getData('shared_options_injected')) {
            return $existingOptions;
        }

        $existingOptions = $existingOptions ?: [];

        // Check if we have already appended our high-offset options
        foreach ($existingOptions as $existingOption) {
            if ((int)$existingOption->getOptionId() >= self::SHARED_OPTION_OFFSET) {
                return $existingOptions;
            }
        }

        // Set the injection guard flag immediately
        $product->setData('shared_options_injected', true);

        // 1. Fetch array of assigned group IDs directly from database
        $groupIds = $this->groupResource->getAssignedGroupIds((int)$product->getId());

        if (empty($groupIds)) {
            return $existingOptions;
        }

        // 2. Fetch ONLY MASTER option rows (parent_id = 0) matching our collected Group IDs
        $itemCollection = $this->optionCollectionFactory->create()
            ->addFieldToFilter('group_id', ['in' => $groupIds])
            ->addFieldToFilter('parent_id', 0)
            ->setOrder('sort_order', 'ASC');

        if ($itemCollection->getSize() === 0) {
            return $existingOptions;
        }

        // 3. Hydrate and push master items directly into product memory cache
        foreach ($itemCollection as $sharedItem) {
            /** @var \Magento\Catalog\Model\Product\Option $option */
            $option = $this->optionFactory->create();

            $optionId = (int)$sharedItem->getId();
            $optionType = $sharedItem->getType();
            $fakeOptionId = self::SHARED_OPTION_OFFSET + $optionId;

            $option->setProduct($product)
                ->setOptionId($fakeOptionId)
                ->setTitle($sharedItem->getTitle())
                ->setType($optionType)
                ->setIsRequire((bool)$sharedItem->getIsRequired())
                ->setPrice((float)$sharedItem->getPriceModifier())
                // B3D FIX: Dynamically assign price_type from database
                ->setPriceType($sharedItem->getData('price_type') ?: 'fixed')
                ->setMaxCharacters((int)$sharedItem->getData('max_characters'))
                ->setSku($sharedItem->getSkuModifier())
                ->setSortOrder((int)$sharedItem->getSortOrder())
                ->setData('placeholder', $sharedItem->getData('placeholder'));

            // If this is a selection field, load and nest its child rows natively
            if (in_array($optionType, ['drop_down', 'multiple'])) {
                $subCollection = $this->optionCollectionFactory->create()
                    ->addFieldToFilter('parent_id', $optionId)
                    ->setOrder('sort_order', 'ASC');

                $nativeValues = [];
                foreach ($subCollection as $subItem) {
                    /** @var \Magento\Catalog\Model\Product\Option\Value $value */
                    $value = $this->optionValueFactory->create();

                    $fakeValueId = self::SHARED_OPTION_OFFSET + (int)$subItem->getId();

                    $value->setOption($option)
                        ->setOptionTypeId($fakeValueId)
                        ->setId($fakeValueId)
                        ->setTitle($subItem->getTitle())
                        ->setPrice((float)$subItem->getPriceModifier())
                        // B3D FIX: Dynamically assign price_type from database
                        ->setPriceType($subItem->getData('price_type') ?: 'fixed')
                        ->setSku($subItem->getSkuModifier())
                        ->setSortOrder((int)$subItem->getSortOrder());

                    $nativeValues[$fakeValueId] = $value;
                }
                $option->setValues($nativeValues);
            }

            $existingOptions[] = $option;
        }

        // Update the cached array inside the product model memory
        $product->setOptions($existingOptions);
        $product->setHasOptions(true);

        return $existingOptions;
    }

    /**
     * Intercept getOptionById to return our injected memory options instead of executing raw SQL database queries
     */
    public function aroundGetOptionById(Product $product, \Closure $proceed, $optionId)
    {
        if ($this->isAdminOrCli()) {
            return $proceed($optionId);
        }

        // Force option hydration in memory (which calls afterGetOptions internally)
        $options = $product->getOptions();

        if (is_array($options)) {
            foreach ($options as $option) {
                if ((int)$option->getOptionId() === (int)$optionId) {
                    return $option;
                }
            }
        }

        return $proceed($optionId);
    }

    /**
     * Determine if the request is executing in the Admin Panel or via CLI compile commands
     */
    private function isAdminOrCli(): bool
    {
        try {
            $areaCode = $this->appState->getAreaCode();
            return in_array($areaCode, [
                AppArea::AREA_ADMINHTML,
                AppArea::AREA_CRONTAB
            ]);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // If area code is not set, we are running CLI compile/index commands. Treat as CLI.
            return true;
        }
    }
}