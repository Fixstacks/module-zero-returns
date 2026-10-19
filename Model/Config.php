<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > Zero > Returns (defaults in etc/config.xml).
 */
class Config
{
    private const PATH = 'zero_returns/general/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Days after the order was placed in which a return can be asked for.
     */
    public function windowDays(?int $storeId = null): int
    {
        return max(1, (int) $this->value('window_days', $storeId) ?: 30);
    }

    /**
     * @return string[]
     */
    public function reasons(?int $storeId = null): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $this->value('reasons', $storeId)) ?: []), 'strlen'));
    }

    public function instructions(?int $storeId = null): string
    {
        return trim($this->value('instructions', $storeId));
    }

    public function value(string $field, ?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::PATH . $field, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
