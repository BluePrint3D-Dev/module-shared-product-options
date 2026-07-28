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

use Magento\Catalog\Ui\DataProvider\Product\Form\ProductDataProvider;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Class ProductDataProviderPlugin
 * Hydrates assigned shared option group IDs into the product edit form UI component.
 */
class ProductDataProviderPlugin
{
    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * ProductDataProviderPlugin constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(ResourceConnection $resourceConnection, LoggerInterface $logger)
    {
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    /**
     * Inject assigned shared option group IDs into product UI form data
     *
     * @param ProductDataProvider $subject
     * @param array $result
     * @return array
     */
    public function afterGetData(ProductDataProvider $subject, $result)
    {
        if (empty($result)) {
            return $result;
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('blueprint3d_shared_option_product');

        foreach ($result as $productId => $productData) {
            // CRITICAL FIX: Skip the 'config' key. Only process numeric product IDs!
            if (!is_numeric($productId)) {
                continue;
            }

            $select = $connection->select()
                ->from($tableName, 'group_id')
                ->where('product_id = ?', (int)$productId);

            $selectedIds = $connection->fetchCol($select);

            // ui-select with multiple=true expects an array of strings
            $selectedIds = array_map('strval', $selectedIds);

            $this->logger->info(
                "=== DataProvider Injecting for Product ID {$productId}: " . json_encode($selectedIds) . " ==="
            );

            if (!isset($result[$productId]['product'])) {
                $result[$productId]['product'] = [];
            }

            // Map data directly into the product scope defined by your XML
            $result[$productId]['product']['shared_option_groups'] = $selectedIds;
        }

        return $result;
    }
}
