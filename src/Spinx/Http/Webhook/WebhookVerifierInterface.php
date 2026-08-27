<?php

declare(strict_types=1);

namespace Spinx\Http\Webhook;

/**
 * Universal contract for verifying incoming HTTP webhook signatures from any payment gateway or API provider.
 */
interface WebhookVerifierInterface
{
    /**
     * Verify that the raw payload matches the expected cryptographic signature.
     *
     * @param string $rawPayload
     * @param string $signatureHeader
     * @param string $secretKey
     * @return bool
     */
    public function verify(string $rawPayload, string $signatureHeader, string $secretKey): bool;
}
