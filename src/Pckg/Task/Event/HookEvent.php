<?php

namespace Pckg\Task\Event;

use Pckg\Task\Form\Hook;

class HookEvent
{
    protected string $origin;
    protected string $event;
    protected array $body;
    protected array $context;
    protected int $retry = 0;

    public function __construct(string $origin, string $event, array $body, array $context, int $retry = 0)
    {
        $this->origin = $origin;
        $this->event = $event;
        $this->body = $body;
        $this->context = $context;
        $this->retry = $retry;
    }

    public function handle()
    {
        $origin = collect(config('pckg.hook.origins', []))
            ->first(fn($origin, $key) => $origin['alias'] === $this->origin);

        if (!$origin) {
            error_log('Non-registered origin ' . $this->origin);
            return;
        }

        $event = $origin['triggers'][$this->event] ?? null;
        if (!$event) {
            error_log('Non-registered trigger ' . $this->event . ' for origin ' . $this->origin);
            return;
        }

        /**
         * We probably want to queue all events?
         */
        (new $event($this))->handle();
    }

    public function getOrigin(): string
    {
        return $this->origin;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getRetry(): int
    {
        return $this->retry;
    }

    public function toArray(): array
    {
        return [
            'origin' => $this->origin,
            'event' => $this->event,
            'body' => $this->body,
            'context' => $this->context,
            'retry' => $this->retry,
        ];
    }
}
