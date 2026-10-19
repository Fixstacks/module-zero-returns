<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Model;

use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Escaper;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * The emails of a return: a new request to the store and a receipt to the
 * customer; a status change or a store comment to the customer; a customer
 * comment to the store. Templates and senders: Stores > Configuration >
 * Zero > Returns. A failure is logged, never shown.
 */
class Mailer
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Config $config,
        private readonly Returns $returns,
        private readonly Labels $labels,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function newRequest(array $return): void
    {
        $this->send('template_new', $return, $this->storeEmail($return), (string) __('Store'));
        $this->send('template_received', $return, (string) $return['customer_email'], (string) $return['customer_name']);
    }

    /**
     * The store changed the status or wrote to the customer.
     */
    public function updated(array $return, string $message): void
    {
        $this->send('template_update', $return, (string) $return['customer_email'], (string) $return['customer_name'], [
            'message' => $message,
            'instructions' => $return['status'] === Returns::APPROVED ? $this->config->instructions((int) $return['store_id']) : '',
        ]);
    }

    /**
     * The customer wrote on the return: the store gets the new-request template with the comment.
     */
    public function customerCommented(array $return, string $message): void
    {
        $this->send('template_new', $return, $this->storeEmail($return), (string) __('Store'), ['comment' => $message]);
    }

    private function storeEmail(array $return): string
    {
        $identity = $this->config->value('notify_identity', (int) $return['store_id']);

        return (string) $this->scopeConfig->getValue('trans_email/ident_' . $identity . '/email', ScopeInterface::SCOPE_STORE, (int) $return['store_id']);
    }

    private function send(string $template, array $return, string $to, string $toName, array $extra = []): void
    {
        if ($to === '') {
            return;
        }
        $storeId = (int) $return['store_id'];
        $store = $this->storeManager->getStore($storeId);
        $this->inlineTranslation->suspend();
        try {
            $this->transportBuilder
                ->setTemplateIdentifier($this->config->value($template, $storeId))
                ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => $storeId])
                ->setTemplateVars($extra + [
                    'number' => Returns::number($return),
                    'order' => (string) $return['order_increment_id'],
                    'name' => (string) $return['customer_name'],
                    'email' => (string) $return['customer_email'],
                    'status' => $this->labels->status((string) $return['status']),
                    'resolution' => $this->labels->resolution((string) $return['resolution']),
                    'comment' => (string) $return['comment'],
                    'message' => '',
                    'instructions' => '',
                    'items_html' => $this->itemsHtml($return),
                    'url' => $store->getUrl('returns/view/index', ['id' => (int) $return['return_id']]),
                    'admin_url' => $store->getBaseUrl() . 'admin/zero_returns/return/index/id/' . (int) $return['return_id'],
                    'store_name' => (string) $store->getFrontendName(),
                ])
                ->setFromByScope($this->config->value('identity', $storeId), $storeId)
                ->addTo($to, $toName)
                ->getTransport()
                ->sendMessage();
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Zero_Returns: %s for %s not sent: %s', $template, Returns::number($return), $e->getMessage()));
        } finally {
            $this->inlineTranslation->resume();
        }
    }

    /**
     * The lines as an HTML table (escaped here; the templates print it raw).
     */
    private function itemsHtml(array $return): string
    {
        $cell = 'style="padding:6px 8px;border-bottom:1px solid #eee;"';
        $rows = '';
        foreach ($this->returns->items((int) $return['return_id']) as $line) {
            $rows .= '<tr><td ' . $cell . '>' . $this->escaper->escapeHtml($line['name']) . '<br><small>' . $this->escaper->escapeHtml($line['sku']) . '</small></td>'
                . '<td ' . $cell . ' align="right">' . (float) $line['qty'] . '</td>'
                . '<td ' . $cell . '>' . $this->escaper->escapeHtml($line['reason']) . '</td></tr>';
        }

        return '<table style="width:100%;border-collapse:collapse;"><tr>'
            . '<th style="text-align:left;padding:6px 8px;">' . $this->escaper->escapeHtml(__('Item')) . '</th>'
            . '<th style="text-align:right;padding:6px 8px;">' . $this->escaper->escapeHtml(__('Qty')) . '</th>'
            . '<th style="text-align:left;padding:6px 8px;">' . $this->escaper->escapeHtml(__('Reason')) . '</th></tr>' . $rows . '</table>';
    }
}
