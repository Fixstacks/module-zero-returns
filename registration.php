<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Zero_Returns: returns (RMA). A customer asks to return items of a
 * delivered order from My Account, with a reason per item; the store
 * approves, receives and refunds in Sales > Returns, with comments both
 * ways and an email at each step. See README.md.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Zero_Returns', __DIR__);
