<?php

declare(strict_types=1);

namespace Spinx\Mail;

use Spinx\Support\Config;
use Symfony\Component\Mailer\Mailer as SymfonyMailerImplementation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport;

/**
 * Translates config/mail.php's driver setting into a real Symfony Mailer
 * DSN and transport. DSN schemes verified against the actual bridge
 * packages' own documentation (symfony/mailgun-mailer,
 * symfony/resend-mailer) rather than assumed — see composer.json for
 * the exact package versions this was checked against.
 */
final class MailerFactory
{
    public function create(): MailerInterface
    {
        return new SymfonyMailerImplementation(Transport::fromDsn($this->buildDsn()));
    }

    private function buildDsn(): string
    {
        return match (Config::instance()->get('mail.driver', 'smtp')) {
            'resend' => $this->resendDsn(),
            'mailgun' => $this->mailgunDsn(),
            default => $this->smtpDsn(),
        };
    }

    private function smtpDsn(): string
    {
        $host = Config::instance()->get('mail.host', 'localhost');
        $port = Config::instance()->get('mail.port', 1025);
        $username = Config::instance()->get('mail.username');
        $password = Config::instance()->get('mail.password');

        $auth = ($username !== null && $password !== null)
            ? rawurlencode((string) $username) . ':' . rawurlencode((string) $password) . '@'
            : '';

        return "smtp://{$auth}{$host}:{$port}";
    }

    private function resendDsn(): string
    {
        $key = Config::instance()->get('services.resend.key');

        if ($key === null) {
            throw new \RuntimeException('mail.driver is "resend" but services.resend.key is not set — check RESEND_API_KEY in .env.');
        }

        // resend+api:// — verified against symfony/resend-mailer's own docs.
        return 'resend+api://' . rawurlencode((string) $key) . '@default';
    }

    private function mailgunDsn(): string
    {
        $secret = Config::instance()->get('services.mailgun.secret');
        $domain = Config::instance()->get('services.mailgun.domain');

        if ($secret === null || $domain === null) {
            throw new \RuntimeException('mail.driver is "mailgun" but services.mailgun.secret/domain are not set — check MAILGUN_SECRET/MAILGUN_DOMAIN in .env.');
        }

        // mailgun+api://KEY:DOMAIN@default — verified against symfony/mailgun-mailer's own docs.
        return 'mailgun+api://' . rawurlencode((string) $secret) . ':' . rawurlencode((string) $domain) . '@default';
    }
}
