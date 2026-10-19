<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Controller\Adminhtml\Return;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Zero\Returns\Model\Mailer;
use Zero\Returns\Model\Returns;
use Zero\Returns\ViewModel\Admin;

/**
 * A status change and/or a message to the customer on a return; the
 * customer gets one email with both.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Zero_Returns::returns';

    public function __construct(
        Context $context,
        private readonly Returns $returns,
        private readonly Mailer $mailer,
        private readonly Admin $admin
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int) $this->getRequest()->getParam('id');
        try {
            $return = $this->returns->get($id) ?? throw new LocalizedException(__('The return no longer exists.'));
            $status = (string) $this->getRequest()->getParam('status', '');
            $message = mb_substr(trim((string) $this->getRequest()->getParam('message')), 0, 4000);
            if ($status !== '' && !isset($this->admin->nextStatuses($return)[$status])) {
                throw new LocalizedException(__('A return can\'t go from %1 to %2.', $return['status'], $status));
            }
            if ($status === '' && $message === '') {
                throw new LocalizedException(__('Pick a new status or write a message.'));
            }
            $this->returns->comment($id, $message, true, $status !== '' ? $status : null);
            $this->mailer->updated($this->returns->get($id), $message);
            $this->messageManager->addSuccessMessage(
                $status !== '' ? __('The return is now %1; the customer is emailed.', $this->admin->statusLabel($status)) : __('The message is sent to the customer.')
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('zero_returns/return/index', ['id' => $id]);
    }
}
