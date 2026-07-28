<?php
/**
 * Copyright (c) 2026 BluePrint3D Ltd. All rights reserved.
 *
 * @author    BluePrint3D Ltd <support@blueprint3d.dev>
 * @copyright 2026 BluePrint3D Ltd (Company No. 13473806)
 * @license   Custom Proprietary EULA (See LICENSE.txt)
 */
namespace BluePrint3D\SharedProductOptions\Plugin;

use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

class ProductSavePlugin
{
    protected $request;
    protected $resourceConnection;
    protected $logger;

    public function __construct(
        RequestInterface $request,
        ResourceConnection $resourceConnection,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->resourceConnection = $resourceConnection;
        $this->logger = $logger;
    }

    public function afterSave(ProductResource $subject, $result, AbstractModel $product)
    {
        // Safeguard: Only execute on full product saves (ignores API/Grid inline edits)
        if ($this->request->getFullActionName() !== 'catalog_product_save') {
            return $result;
        }

        $productId = (int)$product->getId();
        $postData = $this->request->getPostValue();

        if (empty($postData)) {
            return $result;
        }

        $submittedGroupIds = $this->findKeyRecursively($postData, 'shared_option_groups');

        // MAGIC FIX: If it's a full save and the key is missing, it means the user unchecked all boxes.
        if ($submittedGroupIds === null) {
            $submittedGroupIds = [];
        }

        if (!is_array($submittedGroupIds)) {
            $submittedGroupIds = !empty($submittedGroupIds) ? explode(',', (string)$submittedGroupIds) : [];
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $connection->getTableName('blueprint3d_shared_option_product');

        // 1. Wipe out stale relationships
        $connection->delete($tableName, ['product_id = ?' => $productId]);

        // 2. Insert records sequentially using aggressive sanitizer
        $rowsToInsert = [];
        foreach ($submittedGroupIds as $groupId) {
            $groupId = (int)$groupId;
            // Only add valid integer IDs
            if ($groupId > 0) {
                $rowsToInsert[] = [
                    'group_id'   => $groupId,
                    'product_id' => $productId
                ];
            }
        }

        if (!empty($rowsToInsert)) {
            $connection->insertMultiple($tableName, $rowsToInsert);
        }

        return $result;
    }

    private function findKeyRecursively(array $array, string $searchKey)
    {
        if (array_key_exists($searchKey, $array)) {
            return $array[$searchKey];
        }
        foreach ($array as $value) {
            if (is_array($value)) {
                $res = $this->findKeyRecursively($value, $searchKey);
                if ($res !== null) {
                    return $res;
                }
            }
        }
        return null;
    }
}