<?php
/**
 * Copyright (c) 2026 BluePrint3D Ltd. All rights reserved.
 *
 * @author BluePrint3D Ltd <support@blueprint3d.dev>
 * @copyright 2026 BluePrint3D Ltd (Company No. 13473806)
 * @license Custom Proprietary EULA (See LICENSE.txt)
 */
namespace BluePrint3D\SharedProductOptions\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Group extends AbstractDb
{
    private const PRODUCT_RELATION_TABLE = 'blueprint3d_shared_option_product';

    protected function _construct()
    {
        $this->_init('blueprint3d_shared_option_group', 'group_id');
    }

    public function getAssignedProductIds(int $groupId): array
    {
        if (!$groupId) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::PRODUCT_RELATION_TABLE), 'product_id')
            ->where('group_id = ?', (int)$groupId);

        return array_map('intval', $connection->fetchCol($select));
    }

    public function assignProductsToGroup(int $groupId, array $productIds): self
    {
        $connection = $this->getConnection();
        $table = $this->getTable(self::PRODUCT_RELATION_TABLE);

        // 1. Aggressively sanitize, cast to integer, and remove duplicates
        $cleanProductIds = [];
        foreach ($productIds as $id) {
            $intId = (int)$id;
            // Only allow valid, positive product IDs (strips out 0, "on", "", null)
            if ($intId > 0) {
                $cleanProductIds[$intId] = true;
            }
        }
        $validProductIds = array_keys($cleanProductIds);

        $connection->beginTransaction();
        try {
            // 2. Wipe existing relationships for this group
            $connection->delete($table, ['group_id = ?' => (int)$groupId]);

            // 3. Insert the clean, verified IDs
            if (!empty($validProductIds)) {
                $data = [];
                foreach ($validProductIds as $productId) {
                    $data[] = [
                        'group_id' => (int)$groupId,
                        'product_id' => $productId
                    ];
                }
                $connection->insertMultiple($table, $data);
            }
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * Fetch array of group IDs currently assigned to a product
     * (CRITICAL for the Frontend Injector!)
     */
    public function getAssignedGroupIds(int $productId): array
    {
        if (!$productId) {
            return [];
        }

        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getTable(self::PRODUCT_RELATION_TABLE), 'group_id')
            ->where('product_id = ?', (int)$productId);

        return array_map('intval', $connection->fetchCol($select));
    }
}