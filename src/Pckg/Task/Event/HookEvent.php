<?php

namespace Pckg\Task\Event;

use Pckg\Concept\Context;
use Pckg\Task\Form\Hook;
use Pckg\Task\Record\Task;

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

        // queue('hook-events', ['event' => $this->toArray()]);
        // this should be queued?
        $handler = (new $event($this));

        // allow wrapping
        trigger(HookEvent::class . '.handling', [$handler, $this]);

        // handle the event
        $handler->handle();

        // allow after-events
        trigger(HookEvent::class . '.handled', [$handler, $this]);
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
