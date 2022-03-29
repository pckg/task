<?php

namespace Pckg\Task\Event;

use Pckg\Concept\Context;
use Pckg\Task\Form\Hook;
use Pckg\Task\Record\Task;
use Pckg\Task\Service\Webhook;

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
    
    public function getTask(): ?Task
    {
        return context()->getOrDefault(Task::class);
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

    public function getShortOrigin()
    {
        $originKey = $this->getOrigin();

        return collect(config('pckg.hook.origins'))
                ->filter(fn($origin, $key) => $key === $originKey || $origin['alias'] === $originKey)->keys()[0] ?? $originKey;
    }

    public function getFullEventName()
    {
        return $this->event . '@' . $this->getShortOrigin();
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
