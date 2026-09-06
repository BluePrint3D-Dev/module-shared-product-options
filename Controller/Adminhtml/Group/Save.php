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
namespace BluePrint3D\SharedProductOptions\Controller\Adminhtml\Group;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use BluePrint3D\SharedProductOptions\Model\GroupFactory;
use BluePrint3D\SharedProductOptions\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use BluePrint3D\SharedProductOptions\Model\OptionFactory;
use BluePrint3D\SharedProductOptions\Model\ResourceModel\Option\CollectionFactory as OptionCollectionFactory;
use BluePrint3D\SharedProductOptions\Model\ResourceModel\Group as GroupResource;

/**
 * Class Save
 * Saves Shared Option Group data and its option values
 */
class Save extends Action
{
    public const ADMIN_RESOURCE = 'BluePrint3D_SharedProductOptions::group';

    /**
     * @var GroupFactory
     */
    protected $groupFactory;

    /**
     * @var GroupCollectionFactory
     */
    protected $groupCollectionFactory;

    /**
     * @var OptionFactory
     */
    protected $optionFactory;

    /**
     * @var OptionCollectionFactory
     */
    protected $optionCollectionFactory;

    /**
     * @var GroupResource
     */
    protected $groupResource;

    /**
     * Save constructor.
     *
     * @param Context $context
     * @param GroupFactory $groupFactory
     * @param GroupCollectionFactory $groupCollectionFactory
     * @param OptionFactory $optionFactory
     * @param OptionCollectionFactory $optionCollectionFactory
     * @param GroupResource $groupResource
     */
    public function __construct(
        Context $context,
        GroupFactory $groupFactory,
        GroupCollectionFactory $groupCollectionFactory,
        OptionFactory $optionFactory,
        OptionCollectionFactory $optionCollectionFactory,
        GroupResource $groupResource
    ) {
        parent::__construct($context);
        $this->groupFactory = $groupFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
        $this->optionFactory = $optionFactory;
        $this->optionCollectionFactory = $optionCollectionFactory;
        $this->groupResource = $groupResource;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $model = $this->groupFactory->create();
        $id = $this->getRequest()->getParam('group_id');

        if (!empty($data['group_id']) && is_numeric($data['group_id']) && $data['group_id'] > 0) {
            $id = $data['group_id'];
            $model->load($id);
        } else {
            unset($data['group_id']);
        }

        // --- FREE TIER LIMITATION CHECK ---
        if (!$id && $this->isLimitReached()) {
            $msg = __('Free Tier Limit Reached (6/6). You are growing fast! ') .
                __('Upgrade to BluePrint3D Shared Options Pro to unlock unlimited groups, percentage pricing, ') .
                __('targeted visibility, and dynamic image switching. ') .
                __('Get your license today at: https://blueprint3d.dev/#SPOP-Pro');
            $this->messageManager->addErrorMessage($msg);
            return $resultRedirect->setPath('*/*/');
        }

        $this->mapBaseGroupFields($model, $data);

        try {
            $model->save();
            $groupId = (int)$model->getId();

            // --- SAVE PRODUCT ASSIGNMENTS FROM PRO GRID ---
            $assignedProducts = $data['general']['assigned_products'] ?? null;
            if ($assignedProducts !== null && $assignedProducts !== '') {
                $productIds = [];
                $decoded = json_decode((string)$assignedProducts, true);
                if (is_array($decoded)) {
                    $productIds = array_keys($decoded);
                }
                $this->groupResource->assignProductsToGroup($groupId, $productIds);
            }

            if (isset($data['option_values']) && is_array($data['option_values'])) {
                $this->saveOptionValues($groupId, $data['option_values']);
            }

            $this->messageManager->addSuccessMessage(__('The shared option rules have been preserved.'));
            return $resultRedirect->setPath('*/*/');
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Save Error: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('*/*/edit', ['id' => $id]);
    }

    /**
     * Map posted data to the group model
     *
     * @param \BluePrint3D\SharedProductOptions\Model\Group $model
     * @param array $data
     * @return void
     */
    private function mapBaseGroupFields($model, array $data): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'option_values' || $key === 'assigned_products' || $key === 'general') {
                continue;
            }

            if (is_array($value)) {
                $model->setData($key, implode(',', $value));
            } else {
                $model->setData($key, $value);
            }
        }

        if (!$model->getData('title')) {
            $model->setData('title', '');
        }
    }

    /**
     * Save option rows for the group.
     *
     * Updates rows in place (matched by item_id) instead of deleting everything up front,
     * so a row's data is never lost just because it wasn't touched in a given save. Rows
     * that genuinely no longer appear in the posted data are removed once the rest of the
     * save has succeeded, and only among the IDs we can account for.
     *
     * @param int $groupId
     * @param array $optionValues
     * @return void
     */
    private function saveOptionValues(int $groupId, array $optionValues): void
    {
        // A completely empty payload almost certainly means the sub-form failed to
        // serialize rather than "the user deleted every row" - do nothing rather than
        // risk wiping the group.
        if (empty($optionValues)) {
            return;
        }

        usort($optionValues, function ($a, $b) {
            return ((int)($a['position'] ?? 0)) <=> ((int)($b['position'] ?? 0));
        });

        $keptItemIds = [];
        $position = 0;

        foreach ($optionValues as $row) {
            if (empty($row['title'])) {
                continue;
            }

            $optionType = !empty($row['type']) ? $row['type'] : 'field';
            $itemId = !empty($row['item_id']) ? (int)$row['item_id'] : 0;

            $optionModel = $this->optionFactory->create();
            if ($itemId) {
                $optionModel->load($itemId);
            }

            $optionModel->addData([
                'group_id'       => $groupId,
                'parent_id'      => 0,
                'type'           => $optionType,
                'title'          => $row['title'],
                'price_modifier' => !empty($row['price']) ? (float)$row['price'] : 0.0000,
                'sku_modifier'   => !empty($row['sku']) ? $row['sku'] : null,
                'is_required'    => !empty($row['is_required']) ? (int)$row['is_required'] : 0,
                'placeholder'    => !empty($row['placeholder']) ? $row['placeholder'] : null,
                'sort_order'     => $position++
            ]);

            foreach ($row as $rKey => $rVal) {
                if ($rKey !== 'item_id' && !is_array($rVal)) {
                    $optionModel->setData($rKey, $rVal);
                }
            }

            $optionModel->save();
            $parentOptionId = (int)$optionModel->getId();
            $keptItemIds[] = $parentOptionId;

            $subValues = [];
            if (isset($row['values']['values']) && is_array($row['values']['values'])) {
                $subValues = $row['values']['values'];
            } elseif (isset($row['values']) && is_array($row['values'])) {
                $subValues = $row['values'];
            }

            if (in_array($optionType, ['drop_down', 'multiple'])) {
                if (!empty($subValues)) {
                    $keptItemIds = array_merge(
                        $keptItemIds,
                        $this->saveChildValues($groupId, $parentOptionId, $optionType, $subValues)
                    );
                } elseif ($itemId) {
                    // This row already existed and is a dropdown/multiselect, but no
                    // values came through in this save - preserve whatever is already
                    // stored rather than treating it as "delete all the values".
                    $existingChildren = $this->optionCollectionFactory->create()
                        ->addFieldToFilter('parent_id', $parentOptionId);
                    foreach ($existingChildren as $existingChild) {
                        $keptItemIds[] = (int)$existingChild->getId();
                    }
                }
            }
        }

        // Remove only the rows that are genuinely no longer present in the posted data.
        $existingItems = $this->optionCollectionFactory->create()->addFieldToFilter('group_id', $groupId);
        foreach ($existingItems as $existingItem) {
            if (!in_array((int)$existingItem->getId(), $keptItemIds, true)) {
                $existingItem->delete();
            }
        }
    }

    /**
     * Save child option values, updating existing rows in place (matched by value_id).
     *
     * @param int $groupId
     * @param int $parentId
     * @param string $optionType
     * @param array $subValues
     * @return int[] IDs of the child rows that were saved, so the caller can protect them
     *               from the top-level cleanup pass
     */
    private function saveChildValues(int $groupId, int $parentId, string $optionType, array $subValues): array
    {
        $keptIds = [];
        $subPosition = 0;

        foreach ($subValues as $valueRow) {
            if (empty($valueRow['title'])) {
                continue;
            }

            $childType = ($optionType === 'multiple') ? 'multiple_value' : 'drop_down_value';
            $valueId = !empty($valueRow['value_id']) ? (int)$valueRow['value_id'] : 0;

            $valueModel = $this->optionFactory->create();
            if ($valueId) {
                $valueModel->load($valueId);
            }

            $valueModel->addData([
                'group_id'       => $groupId,
                'parent_id'      => $parentId,
                'type'           => $childType,
                'title'          => $valueRow['title'],
                'price_modifier' => !empty($valueRow['price']) ? (float)$valueRow['price'] : 0.0000,
                'sku_modifier'   => null,
                'sort_order'     => $subPosition++
            ]);

            foreach ($valueRow as $vrKey => $vrVal) {
                if ($vrKey !== 'value_id' && !is_array($vrVal)) {
                    $valueModel->setData($vrKey, $vrVal);
                }
            }

            $valueModel->save();
            $keptIds[] = (int)$valueModel->getId();
        }

        return $keptIds;
    }

    /**
     * Check if the free tier limit of 6 groups has been reached.
     *
     * @return bool
     */
    public function isLimitReached(): bool
    {
        $groupCollection = $this->groupCollectionFactory->create();
        return $groupCollection->getSize() >= 6;
    }
}
