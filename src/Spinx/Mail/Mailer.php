<?php

declare(strict_types=1);

namespace Spinx\Mail;

use Spinx\Support\Config;
use Spinx\Templating\TemplateRenderer;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class Mailer
{
    public function __construct(
        private readonly MailerInterface $symfonyMailer,
        private readonly TemplateRenderer $renderer,
    ) {
    }

    public function send(Mailable $mailable): void
    {
        $mailable->build();

        if ($mailable->getToAddress() === null) {
            throw new \RuntimeException('Mailable has no recipient — call ->to() inside build() before it can be sent.');
        }

        if ($mailable->getView() === '') {
            throw new \RuntimeException('Mailable has no view — call ->view() inside build() before it can be sent.');
        }

        $html = $this->renderer->render($mailable->getView(), $mailable->getViewData());

        $fromAddress = (string) Config::instance()->get('mail.from.address', 'hello@example.com');
        $fromName = (string) Config::instance()->get('mail.from.name', '');

        $email = (new Email())
            ->from($fromName !== '' ? "{$fromName} <{$fromAddress}>" : $fromAddress)
            ->to($mailable->getToName() !== null
                ? "{$mailable->getToName()} <{$mailable->getToAddress()}>"
                : (string) $mailable->getToAddress())
            ->subject($mailable->getSubject())
            ->html($html);

        $this->symfonyMailer->send($email);
    }
}
