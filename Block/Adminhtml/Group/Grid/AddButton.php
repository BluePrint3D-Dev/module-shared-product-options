<?php
/**
 * Copyright (c) 2026 BluePrint3D Ltd. All rights reserved.
 */
namespace BluePrint3D\SharedProductOptions\Block\Adminhtml\Group\Grid;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Magento\Backend\Block\Widget\Context;
use BluePrint3D\SharedProductOptions\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;

class AddButton implements ButtonProviderInterface
{
    protected $context;
    protected $groupCollectionFactory;

    public function __construct(
        Context $context,
        GroupCollectionFactory $groupCollectionFactory
    ) {
        $this->context = $context;
        $this->groupCollectionFactory = $groupCollectionFactory;
    }

    public function getButtonData()
    {
        if ($this->isLimitReached()) {
            return [];
        }

        return [
            'label' => __('Add New Group'),
            'class' => 'primary',
            'on_click' => sprintf("location.href = '%s';", $this->context->getUrlBuilder()->getUrl('*/*/new')),
            'sort_order' => 10
        ];
    }

    public function isLimitReached(): bool
    {
        return $this->groupCollectionFactory->create()->getSize() >= 6;
    }
}