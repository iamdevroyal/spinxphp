<?php

declare(strict_types=1);

namespace Spinx\Http\Webhook;

/**
 * Standard HMAC webhook signature verifier.
 * Supports standard hex signatures, prefix-stripped signatures (e.g. "sha256=..."),
 * and Stripe-style timestamped signatures ("t=123,v1=...").
 */
final class HmacWebhookVerifier implements WebhookVerifierInterface
{
    public function __construct(
        private readonly string $algo = 'sha256',
    ) {
    }

    public function verify(string $rawPayload, string $signatureHeader, string $secretKey): bool
    {
        if ($rawPayload === '' || $signatureHeader === '' || $secretKey === '') {
            return false;
        }

        // 1. Stripe timestamped signature format: "t=1492774577,v1=5257a869e7ecebea925140b19d2a6873b6171972..."
        if (str_contains($signatureHeader, 't=') && str_contains($signatureHeader, 'v1=')) {
            return $this->verifyStripeSignature($rawPayload, $signatureHeader, $secretKey);
        }

        // 2. Standard prefixed format: "sha256=abcd..." or "sha1=abcd..."
        $cleanSignature = $signatureHeader;
        if (str_contains($signatureHeader, '=')) {
            $parts = explode('=', $signatureHeader, 2);
            $cleanSignature = $parts[1] ?? $signatureHeader;
        }

        $expected = hash_hmac($this->algo, $rawPayload, $secretKey);

        return hash_equals($expected, $cleanSignature);
    }

    private function verifyStripeSignature(string $rawPayload, string $header, string $secret): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if ($timestamp === null || empty($signatures)) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$rawPayload}";
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }

        return false;
    }
}
