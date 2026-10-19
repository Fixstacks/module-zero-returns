<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item;
use PHPUnit\Framework\TestCase;
use Zero\Returns\Model\Config;
use Zero\Returns\Model\Eligibility;
use Zero\Returns\Model\Returns;

class EligibilityTest extends TestCase
{
    public function testShippedLessRefundedLessInOpenReturns(): void
    {
        $eligibility = $this->eligibility(['11' => 1.0]);
        $order = $this->order([
            $this->item(11, 3.0, 0.0),   // 3 shipped, 1 already in a return: 2 left
            $this->item(12, 2.0, 2.0),   // all refunded: nothing
            $this->item(13, 0.0, 0.0),   // not shipped yet: nothing
        ]);

        $lines = $eligibility->returnable($order);

        self::assertSame([11], array_keys($lines));
        self::assertSame(2.0, $lines[11]['qty']);
        self::assertTrue($eligibility->canRequest($order, new \DateTimeImmutable('2026-10-20 12:00:00', new \DateTimeZone('UTC'))));
    }

    public function testTheWindowCloses(): void
    {
        $eligibility = $this->eligibility([]);
        $order = $this->order([$this->item(11, 1.0, 0.0)]);

        self::assertTrue($eligibility->inWindow($order, new \DateTimeImmutable('2026-11-09 12:00:00', new \DateTimeZone('UTC'))));
        self::assertFalse($eligibility->inWindow($order, new \DateTimeImmutable('2026-11-10 12:00:00', new \DateTimeZone('UTC'))));
        self::assertFalse($eligibility->canRequest($order, new \DateTimeImmutable('2026-12-01', new \DateTimeZone('UTC'))));
    }

    public function testACancelledOrderOrNothingShippedCannotBeReturned(): void
    {
        $eligibility = $this->eligibility([]);
        self::assertFalse($eligibility->canRequest($this->order([$this->item(11, 0.0, 0.0)])));
        self::assertFalse($eligibility->canRequest($this->order([$this->item(11, 1.0, 0.0)], Order::STATE_CANCELED)));
    }

    /**
     * @param array<string, float> $inReturns
     */
    private function eligibility(array $inReturns): Eligibility
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturn(true);
        $scopeConfig->method('getValue')->willReturnCallback(static fn (string $path) => str_ends_with($path, 'window_days') ? '30' : '');
        $returns = $this->createMock(Returns::class);
        $returns->method('quantitiesInReturns')->willReturn(array_combine(array_map('intval', array_keys($inReturns)), $inReturns));

        return new Eligibility(new Config($scopeConfig), $returns);
    }

    private function item(int $id, float $shipped, float $refunded): Item
    {
        $item = $this->createMock(Item::class);
        $item->method('getItemId')->willReturn($id);
        $item->method('getQtyShipped')->willReturn($shipped);
        $item->method('getQtyRefunded')->willReturn($refunded);

        return $item;
    }

    /**
     * @param Item[] $items
     */
    private function order(array $items, string $state = Order::STATE_COMPLETE): Order
    {
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(100);
        $order->method('getState')->willReturn($state);
        $order->method('getCreatedAt')->willReturn('2026-10-10 12:00:00');
        $order->method('getAllVisibleItems')->willReturn($items);

        return $order;
    }
}
