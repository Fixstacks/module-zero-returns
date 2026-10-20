<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Return requests (zero_return), their lines and comments. A return goes
 * pending → approved (the customer sends the items back) → received →
 * refunded (or replaced) → closed; rejected ends it from pending.
 */
class Returns
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const RECEIVED = 'received';
    public const REFUNDED = 'refunded';
    public const CLOSED = 'closed';
    public const STATUSES = [self::PENDING, self::APPROVED, self::REJECTED, self::RECEIVED, self::REFUNDED, self::CLOSED];
    /** Statuses whose items still count against what can be returned again. */
    public const OPEN = [self::PENDING, self::APPROVED, self::RECEIVED, self::REFUNDED];

    public const REFUND = 'refund';
    public const REPLACEMENT = 'replacement';
    public const RESOLUTIONS = [self::REFUND, self::REPLACEMENT];

    private const TABLE = 'zero_return';
    private const ITEMS = 'zero_return_item';
    private const COMMENTS = 'zero_return_comment';

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ?EventManager $eventManager = null
    ) {
    }

    /**
     * @param array $return order_id, order_increment_id, store_id, customer_id, customer_name, customer_email, resolution, comment
     * @param array<int, array{order_item_id: int, sku: string, name: string, qty: float, reason: string}> $items
     * @return array The return
     */
    public function create(array $return, array $items): array
    {
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            $connection->insert($this->resource->getTableName(self::TABLE), array_intersect_key($return, array_flip([
                'order_id', 'order_increment_id', 'store_id', 'customer_id', 'customer_name', 'customer_email', 'resolution', 'comment',
            ])) + ['status' => self::PENDING]);
            $id = (int) $connection->lastInsertId($this->resource->getTableName(self::TABLE));
            foreach ($items as $item) {
                $connection->insert($this->resource->getTableName(self::ITEMS), [
                    'return_id' => $id,
                    'order_item_id' => (int) $item['order_item_id'],
                    'sku' => mb_substr((string) $item['sku'], 0, 64),
                    'name' => mb_substr((string) $item['name'], 0, 255),
                    'qty' => (float) $item['qty'],
                    'reason' => mb_substr((string) $item['reason'], 0, 255),
                ]);
            }
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
        $row = $this->get($id);
        // Zero_Webhooks listens (return.created).
        $this->eventManager?->dispatch('zero_returns_created', ['return' => $row, 'items' => $this->items($id), 'store_id' => (int) $row['store_id']]);
        return $row;
    }

    public function get(int $id): ?array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchRow(
            $connection->select()->from($this->resource->getTableName(self::TABLE))->where('return_id = ?', $id)
        ) ?: null;
    }

    /**
     * @return array<int, array>
     */
    public function items(int $returnId): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $connection->select()->from($this->resource->getTableName(self::ITEMS))->where('return_id = ?', $returnId)->order('item_id')
        );
    }

    /**
     * @return array<int, array> Oldest first
     */
    public function comments(int $returnId): array
    {
        $connection = $this->resource->getConnection();

        return $connection->fetchAll(
            $connection->select()->from($this->resource->getTableName(self::COMMENTS))->where('return_id = ?', $returnId)->order('comment_id')
        );
    }

    /**
     * Quantities of an order's items in returns that still count (OPEN statuses).
     *
     * @return array<int, float> order item id => quantity
     */
    public function quantitiesInReturns(int $orderId): array
    {
        $connection = $this->resource->getConnection();
        $rows = $connection->fetchPairs(
            $connection->select()
                ->from(['i' => $this->resource->getTableName(self::ITEMS)], ['order_item_id', 'qty' => 'SUM(i.qty)'])
                ->join(['r' => $this->resource->getTableName(self::TABLE)], 'r.return_id = i.return_id', [])
                ->where('r.order_id = ?', $orderId)
                ->where('r.status IN (?)', self::OPEN)
                ->group('i.order_item_id')
        );

        return array_map('floatval', $rows);
    }

    /**
     * Newest first.
     *
     * @return array<int, array> With `lines` (items) and `last_comment_at`
     */
    public function all(string $status = '', string $search = '', ?int $customerId = null, int $limit = 200): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from(['r' => $this->resource->getTableName(self::TABLE)])
            ->joinLeft(['i' => $this->resource->getTableName(self::ITEMS)], 'i.return_id = r.return_id', ['lines' => 'COUNT(DISTINCT i.item_id)', 'qty' => 'SUM(i.qty)'])
            ->group('r.return_id')
            ->order('r.return_id DESC')
            ->limit($limit);
        if ($status !== '') {
            $select->where('r.status = ?', $status);
        }
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $select->where('r.customer_email LIKE ? OR r.customer_name LIKE ? OR r.order_increment_id LIKE ?', $like);
        }
        if ($customerId !== null) {
            $select->where('r.customer_id = ?', $customerId);
        }

        return $connection->fetchAll($select);
    }

    /**
     * A comment, and the status it comes with (admin), on the return.
     */
    public function comment(int $returnId, string $text, bool $isAdmin, ?string $status = null): void
    {
        $connection = $this->resource->getConnection();
        $previous = $status !== null ? (string) ($this->get($returnId)['status'] ?? '') : '';
        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $connection->update($this->resource->getTableName(self::TABLE), ['status' => $status], ['return_id = ?' => $returnId]);
        }
        if ($text !== '' || $status !== null) {
            $connection->insert($this->resource->getTableName(self::COMMENTS), [
                'return_id' => $returnId,
                'is_admin' => $isAdmin ? 1 : 0,
                'status' => $status,
                'text' => $text,
            ]);
        }
        if ($status !== null && $status !== $previous && ($row = $this->get($returnId))) {
            // Zero_Webhooks listens (return.status_changed).
            $this->eventManager?->dispatch('zero_returns_status_changed', [
                'return' => $row,
                'changes' => ['status' => ['from' => $previous, 'to' => $status]],
                'store_id' => (int) $row['store_id'],
            ]);
        }
    }

    public static function number(array $return): string
    {
        return sprintf('R%06d', (int) $return['return_id']);
    }

    public static function isOpen(array $return): bool
    {
        return !in_array($return['status'], [self::REJECTED, self::CLOSED], true);
    }
}
