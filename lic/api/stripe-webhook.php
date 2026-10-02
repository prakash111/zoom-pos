<?php

require __DIR__.'/../lib/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(405, ['error' => 'Method not allowed.']);
}
$secret = (string) setting('stripe_webhook_secret', '');
if ($secret === '') {
    json_out(503, ['error' => 'Webhook signing secret is not configured.']);
}
$payload = (string) file_get_contents('php://input');
if (!lm_valid_stripe_signature($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), $secret, time())) {
    json_out(400, ['error' => 'Invalid signature.']);
}
$event = json_decode($payload, true);
if (!is_array($event)) json_out(400, ['error' => 'Invalid event.']);
if (!in_array($event['type'] ?? '', ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
    json_out(200, ['received' => true]);
}
$session = $event['data']['object'] ?? [];
// A Stripe account may also serve other stores. Ignore their events and unpaid async sessions.
if (empty($session['metadata']['order_token']) || ($session['payment_status'] ?? '') !== 'paid') {
    json_out(200, ['received' => true]);
}
try {
    $result = lm_verify_stripe_order(db(), $session);
    lm_deliver_order($result);
    json_out(200, ['received' => true]);
} catch (Throwable $ex) {
    error_log('License Stripe webhook fulfillment failed: '.$ex->getMessage());
    json_out(500, ['error' => 'Order fulfillment needs retry.']);
}
