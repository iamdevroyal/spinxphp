<?php

declare(strict_types=1);

namespace Spinx\Mail;

/**
 * Base class for every generated Mailable (see `spinx make:mail`).
 * Subclasses implement build() to configure subject/view/recipient —
 * called once by Mailer::send() right before the email is actually
 * rendered and dispatched, not at construction time, so a Mailable can
 * safely be constructed with just the data it needs (an Order, a User)
 * and have its presentation concerns resolved later.
 */
abstract class Mailable
{
    protected string $subjectLine = '';
    protected string $view = '';

    /** @var array<string, mixed> */
    protected array $viewData = [];

    protected ?string $toAddress = null;
    protected ?string $toName = null;

    /** Configure subject/view/recipient here — called once, right before sending. */
    abstract public function build(): static;

    public function to(string $address, ?string $name = null): static
    {
        $this->toAddress = $address;
        $this->toName = $name;

        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subjectLine = $subject;

        return $this;
    }

    /** @param array<string, mixed> $data */
    public function view(string $view, array $data = []): static
    {
        $this->view = $view;
        $this->viewData = $data;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subjectLine;
    }

    public function getView(): string
    {
        return $this->view;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function getToAddress(): ?string
    {
        return $this->toAddress;
    }

    public function getToName(): ?string
    {
        return $this->toName;
    }
}
