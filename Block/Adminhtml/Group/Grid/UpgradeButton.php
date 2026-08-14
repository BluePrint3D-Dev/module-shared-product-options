<?php
/**
 * Copyright (c) 2026 BluePrint3D Ltd. All rights reserved.
 */
namespace BluePrint3D\SharedProductOptions\Block\Adminhtml\Group\Grid;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Class UpgradeButton
 * Provides the "Upgrade to Pro" button for the grid toolbar.
 */
class UpgradeButton implements ButtonProviderInterface
{
    /**
     * Retrieve button data configuration
     *
     * @return array
     */
    public function getButtonData()
    {
        return [
            'label' => __('⭐ Upgrade to Pro'),
            'class' => 'action-secondary',
            'on_click' => "window.open('https://www.blueprint3d.dev/modules/shared-product-options-pro', '_blank')",
            'sort_order' => 20
        ];
    }
}
