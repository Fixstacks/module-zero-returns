<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Controller\Account;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\PageFactory;
use Zero\Returns\Controller\Guard;
use Zero\Returns\Model\Config;

/**
 * My Account > My Returns (templates/account.phtml).
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Guard $guard,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        if ($signIn = $this->guard->signIn('returns/account/index')) {
            return $signIn;
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Returns'));

        return $page;
    }
}
