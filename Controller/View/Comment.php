<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Controller\View;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Zero\Returns\Model\Config;
use Zero\Returns\Model\Mailer;
use Zero\Returns\Model\Returns;
use Zero\Returns\ViewModel\Storefront;

/**
 * The customer writes on an open return: the comment is kept and the store
 * gets it by email.
 */
class Comment implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly Storefront $storefront,
        private readonly Returns $returns,
        private readonly Mailer $mailer,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        $return = $this->config->isEnabled() ? $this->storefront->current() : null;
        if (!$return) {
            return $this->redirectFactory->create()->setPath('returns/account/index');
        }
        $text = mb_substr(trim((string) $this->request->getParam('text')), 0, 4000);
        if ($text === '' || !Returns::isOpen($return)) {
            $this->messageManager->addErrorMessage(__('Write something first; a closed return can\'t be commented on.'));
        } else {
            $this->returns->comment((int) $return['return_id'], $text, false);
            $this->mailer->customerCommented($return, $text);
            $this->messageManager->addSuccessMessage(__('Your comment is added.'));
        }

        return $this->redirectFactory->create()->setPath('returns/view/index', ['id' => (int) $return['return_id']]);
    }
}
