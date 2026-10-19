<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Controller\Request;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Zero\Returns\Controller\Guard;
use Zero\Returns\Model\Config;
use Zero\Returns\ViewModel\Storefront;

/**
 * /returns/request?order=<id>: the request form for one of the customer's
 * orders (templates/request.phtml).
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
        if ($signIn = $this->guard->signIn('returns/request/index', ['_query' => ['order' => (int) $this->request->getParam('order')]])) {
            return $signIn;
        }
        $order = $this->storefront->order();
        if (!$order || !$this->storefront->canRequest($order)) {
            $this->messageManager->addErrorMessage(__('This order has nothing left to return, or its return window has passed.'));

            return $this->redirectFactory->create()->setPath('sales/order/history');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Return items of order #%1', $order->getIncrementId()));

        return $page;
    }
}
