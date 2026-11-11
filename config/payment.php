<?php
/**
 * Fee payment configuration.
 *
 * Manual/offline payments (cash, bank transfer) always work — admin records
 * them directly, no setup needed.
 *
 * Online payments use Paystack (https://paystack.com). To enable the "Pay
 * Online" button for students, get your keys from your Paystack dashboard
 * (Settings → API Keys & Webhooks) and set them below, or as environment
 * variables PAYSTACK_PUBLIC_KEY / PAYSTACK_SECRET_KEY. Use the `pk_test_...`
 * / `sk_test_...` pair first and switch to live keys only once you've made
 * a real test payment successfully.
 *
 * Leaving these blank is completely safe — the app detects that online
 * payment isn't configured and simply hides the "Pay Online" button,
 * leaving manual recording as the only (fully working) option.
 */

define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: '');
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: '');
define('ONLINE_PAYMENTS_ENABLED', PAYSTACK_PUBLIC_KEY !== '' && PAYSTACK_SECRET_KEY !== '');

define('APP_CURRENCY_SYMBOL', '₦');
define('CURRENCY_CODE', 'NGN'); // ISO 4217 — Paystack needs this exact code

/** Format a Naira amount for display, e.g. money(150000) -> "₦150,000.00" */
function money(float $amount): string {
    return APP_CURRENCY_SYMBOL . number_format($amount, 2);
}
