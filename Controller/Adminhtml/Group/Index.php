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
use Magento\Framework\View\Result\PageFactory;
use BluePrint3D\SharedProductOptions\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;

class Index extends Action
{
    /**
     * Authority gate: checks ACL permission key matching menu.xml
     */
    const ADMIN_RESOURCE = 'BluePrint3D_SharedProductOptions::group';

    protected $resultPageFactory;
    protected $groupCollectionFactory;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        GroupCollectionFactory $groupCollectionFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->groupCollectionFactory = $groupCollectionFactory;
    }

    /**
     * Process page request and set up dashboard layout window parameters
     */
    public function execute()
    {
        if ($this->isLimitReached()) {
            $this->messageManager->addNoticeMessage(
                __('⚠️ You have reached your Free Tier limit (6/6). You cannot create any more shared option groups. Click "Upgrade to Pro" to unlock unlimited groups and advanced merchandising tools!')
            );
        }

        $resultPage = $this->resultPageFactory->create();

        // Active menu sidebar highlighting flag
        $resultPage->setActiveMenu('BluePrint3D_SharedProductOptions::shared_options_group');
        $resultPage->getConfig()->getTitle()->prepend(__('Shared Product Options Groups'));

        return $resultPage;
    }

    public function isLimitReached(): bool
    {
        return $this->groupCollectionFactory->create()->getSize() >= 6;
    }
}