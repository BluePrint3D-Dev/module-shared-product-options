<?php
/**
 * Copyright (c) 2026 BluePrint3D Ltd. All rights reserved.
 */
namespace BluePrint3D\SharedProductOptions\Block\Adminhtml\Group\Grid;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class UpgradeButton implements ButtonProviderInterface
{
    public function getButtonData()
    {
        return [
            'label' => __('⭐ Upgrade to Pro'),
            'class' => 'action-secondary',
            'on_click' => "window.open('https://blueprint3d.dev/pro', '_blank')",
            'sort_order' => 20
        ];
    }
}