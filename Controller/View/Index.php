<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Controller\View;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Zero\Returns\Controller\Guard;
use Zero\Returns\Model\Config;
use Zero\Returns\Model\Returns;
use Zero\Returns\ViewModel\Storefront;

/**
 * /returns/view/index/id/<id>: one of the customer's returns, its items,
 * status and comments (templates/view.phtml).
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly RequestInterface $request,
        private readonly Guard $guard,
        private readonly Storefront $storefront,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }
        if ($signIn = $this->guard->signIn('returns/view/index', ['id' => (int) $this->request->getParam('id')])) {
            return $signIn;
        }
        $return = $this->storefront->current();
        if (!$return) {
            $this->messageManager->addErrorMessage(__('That return was not found.'));

            return $this->redirectFactory->create()->setPath('returns/account/index');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Return %1', Returns::number($return)));

        return $page;
    }
}
