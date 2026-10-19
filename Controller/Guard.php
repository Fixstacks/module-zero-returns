<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Controller;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\UrlInterface;

/**
 * The returns pages are the customer's: a guest is sent to sign in and
 * brought back afterwards. (Magento's AccountInterface plugin only works
 * for controllers that extend its Action class.)
 */
class Guard
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * The redirect to sign in, or null for a signed-in customer.
     *
     * @param array<string, mixed> $params
     */
    public function signIn(string $route, array $params = []): ?Redirect
    {
        if ($this->customerSession->isLoggedIn()) {
            return null;
        }
        $this->customerSession->setBeforeAuthUrl($this->url->getUrl($route, $params));

        return $this->redirectFactory->create()->setPath('customer/account/login');
    }
}
