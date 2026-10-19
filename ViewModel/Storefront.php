<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\ViewModel;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Zero\Returns\Model\Config;
use Zero\Returns\Model\Eligibility;
use Zero\Returns\Model\Labels;
use Zero\Returns\Model\Returns;

/**
 * My Account > My Returns, the request form and a return's page: the
 * signed-in customer's own returns and orders only.
 */
class Storefront implements ArgumentInterface
{
    public function __construct(
        private readonly Returns $returns,
        private readonly Eligibility $eligibility,
        private readonly Labels $labels,
        private readonly Config $config,
        private readonly CustomerSession $customerSession,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RequestInterface $request,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * @return array<int, array>
     */
    public function mine(): array
    {
        return $this->customerSession->isLoggedIn()
            ? $this->returns->all('', '', (int) $this->customerSession->getCustomerId(), 100)
            : [];
    }

    /**
     * The return named in the URL, when it is the customer's.
     */
    public function current(): ?array
    {
        $return = $this->returns->get((int) $this->request->getParam('id'));

        return $return && $this->customerSession->isLoggedIn()
            && (int) $return['customer_id'] === (int) $this->customerSession->getCustomerId() ? $return : null;
    }

    /**
     * The customer's order named in the URL (?order=), for the request form.
     */
    public function order(): ?Order
    {
        if (!$this->customerSession->isLoggedIn()) {
            return null;
        }
        try {
            $order = $this->orderRepository->get((int) $this->request->getParam('order'));
        } catch (\Exception) {
            return null;
        }

        return $order instanceof Order && (int) $order->getCustomerId() === (int) $this->customerSession->getCustomerId() ? $order : null;
    }

    public function canRequest(Order $order): bool
    {
        return $this->canRequestFor($order);
    }

    /**
     * For the order page button: this order can have a return asked for.
     */
    public function canRequestFor(?Order $order): bool
    {
        return $order !== null && (int) $order->getCustomerId() === (int) $this->customerSession->getCustomerId()
            && $this->eligibility->canRequest($order);
    }

    /**
     * @return array<int, array{item: \Magento\Sales\Model\Order\Item, qty: float}>
     */
    public function returnable(Order $order): array
    {
        return $this->eligibility->returnable($order);
    }

    /**
     * @return string[]
     */
    public function reasons(): array
    {
        return $this->config->reasons();
    }

    /**
     * @return array<string, string>
     */
    public function resolutions(): array
    {
        return $this->labels->resolutions();
    }

    public function status(array $return): string
    {
        return $this->labels->status((string) $return['status']);
    }

    public function resolution(array $return): string
    {
        return $this->labels->resolution((string) $return['resolution']);
    }

    public function number(array $return): string
    {
        return Returns::number($return);
    }

    public function isOpen(array $return): bool
    {
        return Returns::isOpen($return);
    }

    /**
     * @return array<int, array>
     */
    public function items(array $return): array
    {
        return $this->returns->items((int) $return['return_id']);
    }

    /**
     * @return array<int, array>
     */
    public function comments(array $return): array
    {
        return $this->returns->comments((int) $return['return_id']);
    }

    public function instructions(array $return): string
    {
        return $return['status'] === Returns::APPROVED ? $this->config->instructions((int) $return['store_id']) : '';
    }

    public function date(string $utc): string
    {
        return $this->timezone->formatDateTime($utc, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
    }
}
