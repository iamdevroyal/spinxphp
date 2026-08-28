<?php

declare(strict_types=1);

namespace Spinx\Auth\Token;

/**
 * NewAccessToken — Ephemeral value object returned to the application after
 * a new Personal Access Token has been created.
 *
 * The `$plainTextToken` is the only opportunity the application has to expose
 * the unhashed bearer string to the user. It is NEVER stored in the database.
 *
 * Usage in a controller:
 *
 *   $newToken = Token::createToken($user, 'iPhone 15 Pro', ['projects:read']);
 *
 *   return Response::json([
 *       'access_token' => $newToken->plainTextToken,   // "spinx_pat_1|abc123..."
 *       'token_type'   => 'Bearer',
 *       'abilities'    => $newToken->accessToken->abilities,
 *   ], 201);
 */
final class NewAccessToken
{
    /**
     * @param PersonalAccessTokenInterface|PersonalAccessToken $accessToken The persisted token record (no plaintext)
     * @param string $plainTextToken The one-time plaintext bearer string
     */
    public function __construct(
        public readonly PersonalAccessTokenInterface|PersonalAccessToken $accessToken,
        public readonly string $plainTextToken,
    ) {
    }
}

