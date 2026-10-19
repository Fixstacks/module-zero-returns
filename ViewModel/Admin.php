<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\ViewModel;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Zero\Returns\Model\Labels;
use Zero\Returns\Model\Returns;

/**
 * Sales > Returns (view/adminhtml/templates/index.phtml).
 */
class Admin implements ArgumentInterface
{
    public function __construct(
        private readonly Returns $returns,
        private readonly Labels $labels,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url
    ) {
    }

    public function status(): string
    {
        return (string) $this->request->getParam('status', '');
    }

    public function search(): string
    {
        return trim((string) $this->request->getParam('q', ''));
    }

    /**
     * @return array<int, array>
     */
    public function all(): array
    {
        return $this->returns->all($this->status(), $this->search());
    }

    public function current(): ?array
    {
        $id = (int) $this->request->getParam('id');

        return $id ? $this->returns->get($id) : null;
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

    public function number(array $return): string
    {
        return Returns::number($return);
    }

    public function statusLabel(string $status): string
    {
        return $this->labels->status($status);
    }

    public function resolutionLabel(string $resolution): string
    {
        return $this->labels->resolution($resolution);
    }

    /**
     * @return array<string, string>
     */
    public function statuses(): array
    {
        return $this->labels->statuses();
    }

    /**
     * The statuses a return can move to from its current one.
     *
     * @return array<string, string>
     */
    public function nextStatuses(array $return): array
    {
        $next = match ((string) $return['status']) {
            Returns::PENDING => [Returns::APPROVED, Returns::REJECTED],
            Returns::APPROVED => [Returns::RECEIVED, Returns::REJECTED, Returns::CLOSED],
            Returns::RECEIVED => [Returns::REFUNDED, Returns::CLOSED],
            Returns::REFUNDED => [Returns::CLOSED],
            default => [],
        };

        return array_intersect_key($this->labels->statuses(), array_flip($next));
    }

    public function storeName(array $return): string
    {
        try {
            return (string) $this->storeManager->getStore((int) $return['store_id'])->getName();
        } catch (\Exception) {
            return '#' . (int) $return['store_id'];
        }
    }

    public function url(string $route, array $params = []): string
    {
        return $this->url->getUrl($route, $params);
    }

    public function orderUrl(array $return): string
    {
        return $this->url->getUrl('sales/order/view', ['order_id' => (int) $return['order_id']]);
    }

    public function creditMemoUrl(array $return): string
    {
        return $this->url->getUrl('sales/order_creditmemo/new', ['order_id' => (int) $return['order_id']]);
    }
}
