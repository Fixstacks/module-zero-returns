<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Model;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;

/**
 * What an order can still return: its shipped items, less what was refunded
 * and what is already in an open return, inside the return window counted
 * from the day the order was placed. Only visible lines (a configurable's
 * parent, a bundle) — those are what was shipped.
 */
class Eligibility
{
    public function __construct(
        private readonly Config $config,
        private readonly Returns $returns
    ) {
    }

    public function isEnabled(Order $order): bool
    {
        return $this->config->isEnabled((int) $order->getStoreId());
    }

    /**
     * Whether a return can be asked for: the window is open and something is returnable.
     */
    public function canRequest(Order $order, ?\DateTimeImmutable $now = null): bool
    {
        return $this->isEnabled($order)
            && $order->getState() !== Order::STATE_CANCELED
            && $this->inWindow($order, $now)
            && $this->returnable($order) !== [];
    }

    public function inWindow(Order $order, ?\DateTimeImmutable $now = null): bool
    {
        $placed = new \DateTimeImmutable((string) $order->getCreatedAt(), new \DateTimeZone('UTC'));
        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return $placed->modify(sprintf('+%d days', $this->config->windowDays((int) $order->getStoreId()))) >= $now;
    }

    /**
     * The lines with a quantity left to return.
     *
     * @return array<int, array{item: Item, qty: float}> By order item id
     */
    public function returnable(Order $order): array
    {
        $inReturns = $this->returns->quantitiesInReturns((int) $order->getEntityId());
        $lines = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $qty = (float) $item->getQtyShipped() - (float) $item->getQtyRefunded() - ($inReturns[(int) $item->getItemId()] ?? 0.0);
            if ($qty > 0) {
                $lines[(int) $item->getItemId()] = ['item' => $item, 'qty' => $qty];
            }
        }

        return $lines;
    }
}
