<?php

declare(strict_types=1);

namespace Spinx\Session;

use Spinx\Database\DB;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Framework-level middleware that boots the session for every request.
 *
 * Lifecycle per request:
 *   1. Read session ID from cookie (or generate a new one if absent/unknown).
 *   2. Load session data from the backing store.
 *   3. Hydrate the SessionInterface instance with the ID + data.
 *   4. Inject the session into request attributes so controllers can resolve it.
 *   5. Call the next handler.
 *   6. After the response: persist session data back to the backing store.
 *   7. Set the session cookie on the response (always refreshes expiry).
 *
 * Applied globally by ContainerFactory (not as a per-route middleware alias)
 * because every request needs a session — even unauthenticated ones, so that
 * the CSRF token and flash messages work without opting in.
 */
final class SessionMiddleware
{
    private const COOKIE_NAME = 'spinx_session';

    public function __construct(
        private readonly SessionInterface $session,
        private readonly FileSession|null $fileSession = null,
    ) {
    }

    public function process(Request $request, \Closure $next): Response
    {
        // 1–4: Boot the session.
        $cookieId = $request->cookies->get(self::COOKIE_NAME, '');
        $sessionId = (is_string($cookieId) && $cookieId !== '') ? $cookieId : $this->generateId();

        $data = $this->loadData($sessionId);
        $this->session->hydrate($sessionId, $data);

        // Inject into request attributes so any controller can type-hint
        // SessionInterface and receive it via the container or request.
        $request->attributes->set(SessionInterface::class, $this->session);

        // 5: Dispatch the request.
        $response = $next($request);

        // 6–7: Persist and set cookie.
        $this->persistData();
        $this->setSessionCookie($response, $this->session->getId());

        return $response;
    }

    /** @return array<string, mixed> */
    private function loadData(string $sessionId): array
    {
        if ($this->fileSession !== null) {
            return $this->fileSession->load($sessionId);
        }

        return [];
    }

    private function persistData(): void
    {
        if ($this->fileSession !== null) {
            $this->fileSession->persist();
        }
    }

    private function setSessionCookie(Response $response, string $sessionId): void
    {
        $config   = \Spinx\Support\Config::get('session', []);
        $lifetime = (int) ($config['lifetime'] ?? 120);
        $secure   = (bool) ($config['secure'] ?? false);
        $sameSite = (string) ($config['same_site'] ?? 'Lax');

        $cookie = sprintf(
            '%s=%s; Path=/; Max-Age=%d; HttpOnly; SameSite=%s%s',
            self::COOKIE_NAME,
            $sessionId,
            $lifetime * 60,
            $sameSite,
            $secure ? '; Secure' : ''
        );

        $response->headers->set('Set-Cookie', $cookie);
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(32));
    }
}
