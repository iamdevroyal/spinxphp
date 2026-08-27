<?php

declare(strict_types=1);

namespace Spinx\Broadcasting;

use Spinx\Auth\Auth;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles incoming WebSocket channel authentication requests (Pusher protocol).
 */
final class BroadcastAuthController
{
    public function authenticate(Request $request): Response
    {
        $channelName = (string) $request->request->get('channel_name', $request->query->get('channel_name', ''));
        $socketId    = (string) $request->request->get('socket_id', $request->query->get('socket_id', ''));

        if ($channelName === '' || $socketId === '') {
            return new JsonResponse(['error' => 'Missing channel_name or socket_id'], 400);
        }

        $user = Auth::user();

        // For private and presence channels, authenticate
        $authData = Broadcast::getManager()->authenticate($channelName, $socketId, $user);

        if ($authData === false) {
            return new JsonResponse(['error' => 'Forbidden'], 403);
        }

        return new JsonResponse($authData);
    }
}
