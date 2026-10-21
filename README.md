# Zero_Returns

Returns (RMA) for the Zero storefront: a customer asks to return items
of a delivered order from My Account; the store approves, receives and
refunds in Sales > Returns, with messages both ways and an email at each
step.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4 with the GD extension built with WebP (`php -r 'var_dump(function_exists("imagewebp"));'` says `true`; Magento's own PHP requirements cover the rest). Without WebP in GD, Zero skips its WebP image copies and serves the originals.

```bash
composer require zerofront/module-zero-returns
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Composer installs `zerofront/module-zero-core` with it.

Part of [`zerofront/zero`](https://zerofront.dev), which installs the Zero theme and every Zero module at once.

## Storefront

- **My Orders > order**: "Request a return" among the order's buttons
  while the order has something to return inside the return window.
- **The request** (`/returns/request?order=…`): the order's shipped lines
  with a quantity each (what was shipped, less what was refunded or is
  already in an open return), a reason per item from the configured list,
  refund or replacement, a note. One plain POST.
- **My Returns** (`/returns/account`): every return with its status; a
  return's page shows the items, the status, the store's instructions once
  approved, the messages, and a form to write to the store while the
  return is open.

Customers only: a guest order has no account to show returns in (the
store's contact form is the way for guests).

## Admin

Sales > Returns (resource `Zero_Returns::returns`): the list with a status
filter and a search; a return's page with the items, the conversation and
one form: a new status (pending → approved or rejected; approved → received,
rejected or closed; received → refunded or closed; refunded → closed) and/or
a message. The customer gets one email with both. The money itself is a
credit memo on the order (a link is on the page); the return's status is
the store's bookkeeping of the physical return.

## Emails

Three templates (Stores > Configuration > Zero > Returns): a new request to
the store (also used for a customer's message), a receipt to the customer,
and a status/message email to the customer. The approval email carries the
return instructions from configuration.

## Configuration

Enabled, Return Window (days from the order date), Reasons (one per line),
Instructions Once Approved, which store address gets new requests, sender,
templates.

## Tests

- `Test/Unit/Model/EligibilityTest.php`: what an order can still return and
  the window.
- `dev/tests/zero-e2e/tests/returns-admin.spec.ts`: a customer's order is
  shipped in the admin, the customer requests a return, the store approves
  it, the customer sees the status.

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

<a href="https://vilmate.com"><picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo-dark.svg">
  <img src="https://raw.githubusercontent.com/zerofrontdev/zero/main/docs/images/vilmate-logo.svg" alt="Vilmate" height="34" align="right">
</picture></a>

Zero is made by [Fixstacks](https://fixstacks.com) in partnership with [Vilmate](https://vilmate.com), a software development company.
Documentation: https://zerofront.dev/docs. Demo store: https://demo.zerofront.dev.
