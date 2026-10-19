<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Model;

/**
 * How statuses and resolutions read to people, translated.
 */
class Labels
{
    public function status(string $status): string
    {
        return (string) match ($status) {
            Returns::PENDING => __('Pending review'),
            Returns::APPROVED => __('Approved: send the items back'),
            Returns::REJECTED => __('Rejected'),
            Returns::RECEIVED => __('Items received'),
            Returns::REFUNDED => __('Refunded'),
            Returns::CLOSED => __('Closed'),
            default => ucfirst($status),
        };
    }

    public function resolution(string $resolution): string
    {
        return (string) match ($resolution) {
            Returns::REPLACEMENT => __('Replacement'),
            default => __('Refund'),
        };
    }

    /**
     * @return array<string, string> status => label
     */
    public function statuses(): array
    {
        $labels = [];
        foreach (Returns::STATUSES as $status) {
            $labels[$status] = $this->status($status);
        }

        return $labels;
    }

    /**
     * @return array<string, string> resolution => label
     */
    public function resolutions(): array
    {
        $labels = [];
        foreach (Returns::RESOLUTIONS as $resolution) {
            $labels[$resolution] = $this->resolution($resolution);
        }

        return $labels;
    }
}
