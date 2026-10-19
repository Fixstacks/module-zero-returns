<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Controller\Request;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Zero\Returns\Model\Config;
use Zero\Returns\Model\Eligibility;
use Zero\Returns\Model\Mailer;
use Zero\Returns\Model\Returns;
use Zero\Returns\ViewModel\Storefront;

/**
 * The request form's POST: the lines asked for (items[<order item id>] =
 * qty, reason[<order item id>]), the resolution and a comment become a
 * pending return; the store and the customer get an email. The form key is
 * checked by Magento's CSRF validator.
 */
class Save implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly Storefront $storefront,
        private readonly Eligibility $eligibility,
        private readonly Returns $returns,
        private readonly Mailer $mailer,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        $order = $this->storefront->order();
        if (!$this->config->isEnabled() || !$order) {
            return $redirect->setPath('sales/order/history');
        }
        try {
            if (!$this->eligibility->canRequest($order)) {
                throw new LocalizedException(__('This order has nothing left to return, or its return window has passed.'));
            }
            $returnable = $this->eligibility->returnable($order);
            $reasons = $this->config->reasons((int) $order->getStoreId());
            $items = [];
            foreach ((array) $this->request->getParam('items', []) as $orderItemId => $qty) {
                $orderItemId = (int) $orderItemId;
                $qty = (float) str_replace(',', '.', (string) $qty);
                if ($qty <= 0 || !isset($returnable[$orderItemId])) {
                    continue;
                }
                $line = $returnable[$orderItemId];
                if ($qty > $line['qty']) {
                    throw new LocalizedException(__('Only %1 of %2 can be returned.', $line['qty'] + 0, $line['item']->getName()));
                }
                $reason = trim((string) ($this->request->getParam('reason')[$orderItemId] ?? ''));
                if ($reasons && !in_array($reason, $reasons, true)) {
                    throw new LocalizedException(__('Pick a reason for %1.', $line['item']->getName()));
                }
                $items[] = [
                    'order_item_id' => $orderItemId,
                    'sku' => (string) $line['item']->getSku(),
                    'name' => (string) $line['item']->getName(),
                    'qty' => $qty,
                    'reason' => $reason,
                ];
            }
            if (!$items) {
                throw new LocalizedException(__('Pick at least one item to return.'));
            }
            $resolution = (string) $this->request->getParam('resolution', Returns::REFUND);
            $return = $this->returns->create([
                'order_id' => (int) $order->getEntityId(),
                'order_increment_id' => (string) $order->getIncrementId(),
                'store_id' => (int) $order->getStoreId(),
                'customer_id' => (int) $order->getCustomerId(),
                'customer_name' => trim($order->getCustomerFirstname() . ' ' . $order->getCustomerLastname()),
                'customer_email' => (string) $order->getCustomerEmail(),
                'resolution' => in_array($resolution, Returns::RESOLUTIONS, true) ? $resolution : Returns::REFUND,
                'comment' => mb_substr(trim((string) $this->request->getParam('comment')), 0, 4000) ?: null,
            ], $items);
            $this->mailer->newRequest($return);
            $this->messageManager->addSuccessMessage(__('Your return request %1 is sent. We review it and email you.', Returns::number($return)));

            return $redirect->setPath('returns/view/index', ['id' => (int) $return['return_id']]);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('returns/request/index', ['_query' => ['order' => (int) $order->getEntityId()]]);
        }
    }
}
