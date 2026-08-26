<?php

declare(strict_types=1);

namespace Spinx\Http;

/**
 * Spinx Redirect Response — extends Spinx\Http\Response for full type compatibility.
 *
 * Usage:
 *   return Response::redirect('/dashboard');
 *   return redirect('/login');
 *   return new RedirectResponse('/home', 301);
 *   return RedirectResponse::to('/home')->withHeader('X-Reason', 'auth');
 */
class RedirectResponse extends Response
{
    protected string $targetUrl;

    public function __construct(string $url, int $status = 302, array $headers = [])
    {
        parent::__construct('', $status, $headers);

        $this->setTargetUrl($url);

        if (!$this->isRedirect()) {
            throw new \InvalidArgumentException(sprintf('The HTTP status code is not a redirect ("%s" given).', $status));
        }
    }

    public static function to(string $url, int $status = 302, array $headers = []): static
    {
        return new static($url, $status, $headers);
    }

    public static function permanent(string $url, array $headers = []): static
    {
        return new static($url, 301, $headers);
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(string $url): static
    {
        if ($url === '') {
            throw new \InvalidArgumentException('Cannot redirect to an empty URL.');
        }

        $this->targetUrl = $url;

        $this->setContent(
            sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="0;url=\'%1$s\'" />
        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8'))
        );

        $this->headers->set('Location', $url);

        return $this;
    }
}
