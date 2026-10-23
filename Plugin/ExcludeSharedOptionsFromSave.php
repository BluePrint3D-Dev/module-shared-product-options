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

use Magento\Catalog\Model\Product\Option\SaveHandler;

/**
 * Keep injected shared options out of the product option save.
 *
 * InjectSharedOptionsBlock adds shared options to Product::getOptions() in memory
 * (with option IDs offset by SHARED_OPTION_OFFSET) outside the admin and cron
 * areas, so the storefront, cart and checkout (which places orders over REST)
 * can price and label them. A product saved through the REST API runs this
 * handler in that same area, and it would try to persist those in-memory
 * options as real custom options, failing on the catalog_product_option_title
 * foreign key ("The product can't be saved"). Remove them before it runs.
 */
class ExcludeSharedOptionsFromSave
{
    /**
     * Drop injected shared options from the product before its options are saved
     *
     * @param SaveHandler $subject
     * @param \Magento\Catalog\Api\Data\ProductInterface $entity
     * @param array $arguments
     * @return array
     */
    public function beforeExecute(SaveHandler $subject, $entity, $arguments = []): array
    {
        $options = $entity->getOptions();
        if (!is_array($options) || empty($options)) {
            return [$entity, $arguments];
        }

        $nativeOptions = array_filter($options, function ($option) {
            return (int)$option->getOptionId() < InjectSharedOptionsBlock::SHARED_OPTION_OFFSET;
        });

        if (count($nativeOptions) !== count($options)) {
            // InjectSharedOptionsBlock has already flagged this product as injected,
            // so later getOptions() calls during the save return this filtered list.
            $entity->setOptions(array_values($nativeOptions));
        }

        return [$entity, $arguments];
    }
}
