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

    public function handle()
    {
        $origin = collect(config('pckg.hook.origins', []))
            ->first(fn($origin, $key) => $origin['alias'] === $this->origin);

        if (!$origin) {
            error_log('Non-registered origin ' . $this->origin);
            return;
        }

        if (isset($origin['triggers'][$this->event])) {
            $this->handleTriggers($origin['triggers'][$this->event]);
        }

        if (isset($origin['forwarders'][$this->event])) {
            $this->handleForwarders($origin['forwarders'][$this->event]);
        }

        if (!($origin['triggers'] ?? []) && !($origin['forwarders'] ?? [])) {
            error_log('Non-registered trigger/forwarder ' . $this->event . ' for origin ' . $this->origin);
        }
    }

    protected function handleTriggers(string|array $events)
    {
        if (!is_array($events)) {
            $events = [$events];
        }

        foreach ($events as $event) {
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
    }

    protected function handleForwarders(array $forwards)
    {
        if (!is_associative_array($forwards)) {
            $forwards = [$forwards];
        }

        foreach ($forwards as $forward) {

            Task::named('Forwarding ' . $this->event)
                ->make(function ($task) use ($forward) {
                    $genericEvent = new GenericHookEvent($this);

                    // allow wrapping
                    trigger(HookEvent::class . '.forwarding', [$genericEvent, $this]);

                    // is task in context?
                    Webhook::notification(
                        $task,
                        $forward['to'],
                        $forward['body']
                    );

                    // allow after-events
                    trigger(HookEvent::class . '.forwarded', [$genericEvent, $this]);
                });
        }
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
